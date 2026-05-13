"""
Auto Face Capture — Production-ready automatic webcam capture when face is in optimal position.

Runs continuously until ALL quality conditions are met for N consecutive frames, then captures
one image, plays a beep, and returns the saved path. Press ESC to cancel.

Usage:
    pip install opencv-python mediapipe numpy
    python auto_face_capture.py

Or import and use:
    from auto_face_capture import AutoFaceCapture, AutoCaptureConfig
    path = AutoFaceCapture(AutoCaptureConfig()).run()
"""

from __future__ import annotations

import sys
import time
from dataclasses import dataclass, field
from pathlib import Path
from typing import NamedTuple

import cv2
import numpy as np

# Optional: MediaPipe for face mesh (landmarks for pose and eyes)
try:
    import mediapipe as _mp
    MEDIAPIPE_AVAILABLE = True
except ImportError:
    _mp = None  # type: ignore[assignment]
    MEDIAPIPE_AVAILABLE = False


# =============================================================================
# CONFIG — Tune these for your camera and lighting
# =============================================================================

@dataclass
class AutoCaptureConfig:
    """All thresholds and settings for auto-capture. Tune for your environment."""

    # Camera
    camera_index: int = 0
    frame_width: int = 640
    frame_height: int = 480
    target_fps: int = 30

    # Target zone (fraction of frame: 0.0–1.0). Face center must be inside this rectangle.
    target_center_x_min: float = 0.35
    target_center_x_max: float = 0.65
    target_center_y_min: float = 0.35
    target_center_y_max: float = 0.65
    center_tolerance: float = 0.08  # extra tolerance as fraction of frame

    # Face size: min/max face width as fraction of frame width (too close = too large, too far = too small)
    face_width_min_ratio: float = 0.20
    face_width_max_ratio: float = 0.55

    # Face detection confidence (MediaPipe / detector)
    face_confidence_min: float = 0.7

    # Head pose (degrees). Frontal = (0, 0, 0); allow small deviations.
    max_yaw_deg: float = 15.0
    max_pitch_deg: float = 15.0
    max_roll_deg: float = 12.0

    # Sharpness (Laplacian variance). Below = blurry.
    laplacian_min: float = 80.0

    # Brightness: mean intensity in face ROI (0–255). Avoid dark and overexposed.
    brightness_min: float = 60.0
    brightness_max: float = 200.0
    overexposure_ratio_max: float = 0.25  # max fraction of pixels near 255

    # Motion: max allowed movement (in normalized coords) over recent frames.
    motion_max_delta: float = 0.015  # per frame, normalized
    motion_history_frames: int = 5

    # Eyes open (optional). EAR above this = open. Set to 0 to disable.
    eyes_open_ear_min: float = 0.20

    # Stability: how many consecutive frames all conditions must pass before capture.
    stability_frames_required: int = 15

    # Output
    output_dir: str = "captures"
    image_filename_prefix: str = "face"
    image_extension: str = "jpg"
    jpeg_quality: int = 95

    # Debug overlay
    debug_mode: bool = False

    # Beep on capture (set False to mute)
    beep_on_capture: bool = True

    # Headless/API mode: no window, no ESC (use timeout to limit wait). For FastAPI integration.
    display_window: bool = True
    timeout_seconds: float = 0.0  # 0 = no timeout; when display_window=False, use e.g. 60.0


class QualityResult(NamedTuple):
    """Result of a single quality check: (passed, numeric_value, label)."""
    passed: bool
    value: float
    label: str


# MediaPipe Face Mesh landmark indices (canonical)
class LandmarkIndices:
    NOSE_TIP = 4
    CHIN = 152
    LEFT_EYE = 33
    RIGHT_EYE = 263
    FOREHEAD = 10
    # EAR: 6 points per eye (left: 33, 160, 158, 133, 153, 144; right: 362, 385, 387, 263, 373, 380)
    LEFT_EYE_EAR = [33, 160, 158, 133, 153, 144]
    RIGHT_EYE_EAR = [362, 385, 387, 263, 373, 380]


def _beep(duration_ms: int = 200, freq_hz: int = 1000) -> None:
    """Play a short beep. Windows: winsound; others: ASCII bell or skip."""
    try:
        import winsound
        winsound.Beep(freq_hz, duration_ms)
    except Exception:
        try:
            print("\a", end="", flush=True)
        except Exception:
            pass


def _eye_aspect_ratio(landmarks, indices: list[int], w: int, h: int) -> float:
    """Compute EAR for one eye from 6 landmark indices. Vertical / horizontal."""
    pts = [(landmarks[i].x * w, landmarks[i].y * h) for i in indices]
    # EAR = (||p2-p6|| + ||p3-p5||) / (2 * ||p1-p4||)
    v1 = np.linalg.norm(np.array(pts[1]) - np.array(pts[5]))
    v2 = np.linalg.norm(np.array(pts[2]) - np.array(pts[4]))
    h_dist = np.linalg.norm(np.array(pts[0]) - np.array(pts[3]))
    if h_dist < 1e-6:
        return 0.0
    return (v1 + v2) / (2.0 * h_dist)


def _estimate_pose_degrees(landmarks, w: int, h: int) -> tuple[float, float, float]:
    """
    Estimate yaw, pitch, roll in degrees from face landmarks (geometric heuristic).
    - Roll: angle of line between eye centers.
    - Yaw: nose X offset from face center (normalized by face width).
    - Pitch: nose Y offset from eye line (normalized by face height).
    """
    lm = landmarks
    left_eye_x = lm[LandmarkIndices.LEFT_EYE].x * w
    left_eye_y = lm[LandmarkIndices.LEFT_EYE].y * h
    right_eye_x = lm[LandmarkIndices.RIGHT_EYE].x * w
    right_eye_y = lm[LandmarkIndices.RIGHT_EYE].y * h
    nose_x = lm[LandmarkIndices.NOSE_TIP].x * w
    nose_y = lm[LandmarkIndices.NOSE_TIP].y * h
    chin_y = lm[LandmarkIndices.CHIN].y * h
    forehead_y = lm[LandmarkIndices.FOREHEAD].y * h

    # Roll: angle of eye line (in degrees)
    dx = right_eye_x - left_eye_x
    dy = right_eye_y - left_eye_y
    roll = np.degrees(np.arctan2(dy, dx)) if (abs(dx) + abs(dy)) > 1e-6 else 0.0

    # Face center X and "width" from eyes
    face_center_x = (left_eye_x + right_eye_x) / 2
    face_width = max(abs(right_eye_x - left_eye_x), 1.0)
    # Nose offset from center -> approximate yaw (scale to degrees)
    nose_offset_x = (nose_x - face_center_x) / face_width
    yaw = -np.clip(nose_offset_x * 45.0, -45.0, 45.0)

    # Pitch: nose position between forehead and chin
    face_height = max(abs(chin_y - forehead_y), 1.0)
    eye_y = (left_eye_y + right_eye_y) / 2
    # Nose below eye line = positive pitch (looking down)
    nose_offset_y = (nose_y - eye_y) / face_height
    pitch = np.clip(nose_offset_y * 40.0, -40.0, 40.0)

    return float(yaw), float(pitch), float(roll)


# =============================================================================
# Auto Face Capture — main class
# =============================================================================

class AutoFaceCapture:
    """Automatic face capture: webcam runs until all quality conditions hold for N frames, then saves one image."""

    def __init__(self, config: AutoCaptureConfig | None = None) -> None:
        self.config = config or AutoCaptureConfig()
        self._cap: cv2.VideoCapture | None = None
        self._face_mesh = None
        self._motion_landmarks: list[list[tuple[float, float]]] = []
        self._stability_count = 0
        self._last_quality: dict[str, QualityResult] = {}

    def _init_camera(self) -> None:
        self._cap = cv2.VideoCapture(self.config.camera_index)
        if not self._cap.isOpened():
            raise RuntimeError(
                f"Cannot open camera index {self.config.camera_index}. "
                "Check device, permissions, or try another camera_index."
            )
        self._cap.set(cv2.CAP_PROP_FRAME_WIDTH, self.config.frame_width)
        self._cap.set(cv2.CAP_PROP_FRAME_HEIGHT, self.config.frame_height)
        self._cap.set(cv2.CAP_PROP_FPS, self.config.target_fps)

    def _init_face_mesh(self) -> None:
        if not MEDIAPIPE_AVAILABLE or _mp is None:
            raise RuntimeError("MediaPipe is required. Install with: pip install mediapipe")
        self._face_mesh = _mp.solutions.face_mesh.FaceMesh(
            static_image_mode=False,
            max_num_faces=1,
            refine_landmarks=True,
            min_detection_confidence=self.config.face_confidence_min,
            min_tracking_confidence=0.5,
        )

    def _get_face_roi(self, frame: np.ndarray, bbox: tuple[float, float, float, float]) -> np.ndarray:
        """Crop face ROI from frame. bbox = (x_min, y_min, x_max, y_max) in pixels."""
        h, w = frame.shape[:2]
        x1 = max(0, int(bbox[0]))
        y1 = max(0, int(bbox[1]))
        x2 = min(w, int(bbox[2]))
        y2 = min(h, int(bbox[3]))
        if x2 <= x1 or y2 <= y1:
            return np.array([])
        return frame[y1:y2, x1:x2]

    def _check_face_present(self, landmarks) -> QualityResult:
        ok = landmarks is not None and len(landmarks) > 0
        return QualityResult(ok, 1.0 if ok else 0.0, "face")

    def _check_centered(
        self, face_center_norm: tuple[float, float]
    ) -> QualityResult:
        cx, cy = face_center_norm
        t = self.config
        in_x = t.target_center_x_min - t.center_tolerance <= cx <= t.target_center_x_max + t.center_tolerance
        in_y = t.target_center_y_min - t.center_tolerance <= cy <= t.target_center_y_max + t.center_tolerance
        passed = in_x and in_y
        # value = distance from center of target (0 = perfect)
        target_cx = (t.target_center_x_min + t.target_center_x_max) / 2
        target_cy = (t.target_center_y_min + t.target_center_y_max) / 2
        dist = np.hypot(cx - target_cx, cy - target_cy)
        return QualityResult(passed, dist, "center")

    def _check_face_size(self, face_width_ratio: float) -> QualityResult:
        t = self.config
        passed = t.face_width_min_ratio <= face_width_ratio <= t.face_width_max_ratio
        return QualityResult(passed, face_width_ratio, "size")

    def _check_pose(self, yaw: float, pitch: float, roll: float) -> tuple[QualityResult, QualityResult, QualityResult]:
        t = self.config
        yaw_ok = abs(yaw) <= t.max_yaw_deg
        pitch_ok = abs(pitch) <= t.max_pitch_deg
        roll_ok = abs(roll) <= t.max_roll_deg
        return (
            QualityResult(yaw_ok, yaw, "yaw"),
            QualityResult(pitch_ok, pitch, "pitch"),
            QualityResult(roll_ok, roll, "roll"),
        )

    def _check_sharpness(self, face_roi: np.ndarray) -> QualityResult:
        if face_roi.size == 0:
            return QualityResult(False, 0.0, "sharp")
        gray = cv2.cvtColor(face_roi, cv2.COLOR_BGR2GRAY)
        lap_var = cv2.Laplacian(gray, cv2.CV_64F).var()
        passed = lap_var >= self.config.laplacian_min
        return QualityResult(passed, lap_var, "sharp")

    def _check_brightness(self, face_roi: np.ndarray) -> QualityResult:
        if face_roi.size == 0:
            return QualityResult(False, 0.0, "bright")
        gray = cv2.cvtColor(face_roi, cv2.COLOR_BGR2GRAY)
        mean_val = float(np.mean(gray))
        # Overexposure: fraction of pixels near 255
        over = np.sum(gray >= 250) / max(gray.size, 1)
        passed = (
            self.config.brightness_min <= mean_val <= self.config.brightness_max
            and over <= self.config.overexposure_ratio_max
        )
        return QualityResult(passed, mean_val, "bright")

    def _check_motion(self, landmarks, w: int, h: int) -> QualityResult:
        """Append current landmark centers to history; compute max delta over recent frames."""
        if landmarks is None or len(landmarks) == 0:
            self._motion_landmarks.append([])
            return QualityResult(False, 999.0, "motion")

        # Use nose + eye centers (normalized 0-1)
        key_indices = [LandmarkIndices.NOSE_TIP, LandmarkIndices.LEFT_EYE, LandmarkIndices.RIGHT_EYE]
        pts = [(landmarks[i].x, landmarks[i].y) for i in key_indices]
        self._motion_landmarks.append(pts)

        n = self.config.motion_history_frames
        while len(self._motion_landmarks) > n:
            self._motion_landmarks.pop(0)

        if len(self._motion_landmarks) < 2:
            return QualityResult(True, 0.0, "motion")

        max_delta = 0.0
        for i in range(1, len(self._motion_landmarks)):
            prev = self._motion_landmarks[i - 1]
            curr = self._motion_landmarks[i]
            if len(prev) != len(curr):
                continue
            for (px, py), (cx, cy) in zip(prev, curr):
                d = np.hypot(cx - px, cy - py)
                max_delta = max(max_delta, d)
        passed = max_delta <= self.config.motion_max_delta
        return QualityResult(passed, max_delta, "motion")

    def _check_eyes_open(self, landmarks, w: int, h: int) -> QualityResult:
        if self.config.eyes_open_ear_min <= 0:
            return QualityResult(True, 1.0, "eyes")
        if landmarks is None or len(landmarks) < max(LandmarkIndices.RIGHT_EYE_EAR):
            return QualityResult(False, 0.0, "eyes")
        left_ear = _eye_aspect_ratio(landmarks, LandmarkIndices.LEFT_EYE_EAR, w, h)
        right_ear = _eye_aspect_ratio(landmarks, LandmarkIndices.RIGHT_EYE_EAR, w, h)
        ear = (left_ear + right_ear) / 2.0
        passed = ear >= self.config.eyes_open_ear_min
        return QualityResult(passed, ear, "eyes")

    def _all_conditions_passed(self, results: dict[str, QualityResult]) -> bool:
        for r in results.values():
            if not r.passed:
                return False
        return True

    def _draw_overlay(
        self,
        frame: np.ndarray,
        face_center_norm: tuple[float, float] | None,
        face_bbox_px: tuple[float, float, float, float] | None,
        results: dict[str, QualityResult],
        stability_count: int,
        green_border: bool = False,
    ) -> None:
        h, w = frame.shape[:2]

        # Target rectangle (guide)
        x1 = int(self.config.target_center_x_min * w)
        y1 = int(self.config.target_center_y_min * h)
        x2 = int(self.config.target_center_x_max * w)
        y2 = int(self.config.target_center_y_max * h)
        color_guide = (0, 255, 255)  # yellow
        cv2.rectangle(frame, (x1, y1), (x2, y2), color_guide, 2)
        cv2.putText(
            frame, "Position your face here",
            (x1, y1 - 8), cv2.FONT_HERSHEY_SIMPLEX, 0.5, color_guide, 1, cv2.LINE_AA
        )

        # Face bbox
        if face_bbox_px:
            fx1, fy1, fx2, fy2 = [int(x) for x in face_bbox_px]
            cv2.rectangle(frame, (fx1, fy1), (fx2, fy2), (0, 255, 0) if green_border else (255, 255, 0), 2)

        # Stability counter
        cv2.putText(
            frame, f"Stable: {stability_count}/{self.config.stability_frames_required}",
            (10, 30), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2, cv2.LINE_AA
        )
        cv2.putText(
            frame, "ESC = cancel",
            (10, h - 10), cv2.FONT_HERSHEY_SIMPLEX, 0.5, (200, 200, 200), 1, cv2.LINE_AA
        )

        if green_border:
            cv2.rectangle(frame, (0, 0), (w - 1, h - 1), (0, 255, 0), 4)
            cv2.putText(frame, "CAPTURING...", (w // 2 - 80, 60), cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0, 255, 0), 2, cv2.LINE_AA)

        if not self.config.debug_mode:
            return

        # Debug: per-metric PASS/FAIL and values
        y_offset = 55
        for key, r in results.items():
            status = "PASS" if r.passed else "FAIL"
            color = (0, 255, 0) if r.passed else (0, 0, 255)
            text = f"{r.label}: {status} ({r.value:.2f})"
            cv2.putText(frame, text, (10, y_offset), cv2.FONT_HERSHEY_SIMPLEX, 0.45, color, 1, cv2.LINE_AA)
            y_offset += 18

    def _process_frame(self, frame: np.ndarray) -> tuple[dict[str, QualityResult], np.ndarray | None, tuple[float, float, float, float] | None]:
        """Run face mesh, compute all quality metrics. Returns (results_dict, face_roi, bbox)."""
        h, w = frame.shape[:2]
        rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
        results: dict[str, QualityResult] = {}
        face_roi = None
        bbox = None
        landmarks_list = None

        if self._face_mesh:
            out = self._face_mesh.process(rgb)
            if out.multi_face_landmarks:
                landmarks_list = out.multi_face_landmarks[0].landmark
                # Bbox from landmarks (normalized)
                xs = [lm.x for lm in landmarks_list]
                ys = [lm.y for lm in landmarks_list]
                x_min, x_max = min(xs), max(xs)
                y_min, y_max = min(ys), max(ys)
                # Slight padding
                pad = 0.05
                x_min = max(0, x_min - pad)
                y_min = max(0, y_min - pad)
                x_max = min(1, x_max + pad)
                y_max = min(1, y_max + pad)
                bbox = (x_min * w, y_min * h, x_max * w, y_max * h)
                face_roi = self._get_face_roi(frame, bbox)
                face_center_norm = ((x_min + x_max) / 2, (y_min + y_max) / 2)
                face_width_ratio = (x_max - x_min)

                results["face"] = self._check_face_present(landmarks_list)
                results["center"] = self._check_centered(face_center_norm)
                results["size"] = self._check_face_size(face_width_ratio)
                yaw, pitch, roll = _estimate_pose_degrees(landmarks_list, w, h)
                r_yaw, r_pitch, r_roll = self._check_pose(yaw, pitch, roll)
                results["yaw"] = r_yaw
                results["pitch"] = r_pitch
                results["roll"] = r_roll
                results["sharp"] = self._check_sharpness(face_roi)
                results["bright"] = self._check_brightness(face_roi)
                results["motion"] = self._check_motion(landmarks_list, w, h)
                results["eyes"] = self._check_eyes_open(landmarks_list, w, h)
            else:
                results["face"] = self._check_face_present(None)
                results["center"] = QualityResult(False, 0.0, "center")
                results["size"] = QualityResult(False, 0.0, "size")
                results["yaw"] = QualityResult(False, 0.0, "yaw")
                results["pitch"] = QualityResult(False, 0.0, "pitch")
                results["roll"] = QualityResult(False, 0.0, "roll")
                results["sharp"] = QualityResult(False, 0.0, "sharp")
                results["bright"] = QualityResult(False, 0.0, "bright")
                results["motion"] = self._check_motion(None, w, h)
                results["eyes"] = QualityResult(False, 0.0, "eyes")
                self._motion_landmarks.append([])
        else:
            results["face"] = QualityResult(False, 0.0, "face")
            results["center"] = QualityResult(False, 0.0, "center")
            results["size"] = QualityResult(False, 0.0, "size")
            results["yaw"] = QualityResult(False, 0.0, "yaw")
            results["pitch"] = QualityResult(False, 0.0, "pitch")
            results["roll"] = QualityResult(False, 0.0, "roll")
            results["sharp"] = QualityResult(False, 0.0, "sharp")
            results["bright"] = QualityResult(False, 0.0, "bright")
            results["motion"] = QualityResult(False, 999.0, "motion")
            results["eyes"] = QualityResult(True, 1.0, "eyes")

        self._last_quality = results
        return results, face_roi, bbox

    def run(self) -> str | None:
        """
        Open webcam, run until all conditions are met for N frames, then capture one image.
        Returns path to saved image, or None if cancelled (ESC), timeout, or error.
        When display_window=False (headless/API), no window is shown and timeout_seconds is used to limit wait.
        """
        Path(self.config.output_dir).mkdir(parents=True, exist_ok=True)

        try:
            self._init_camera()
        except RuntimeError as e:
            print(f"Error: {e}", file=sys.stderr)
            return None

        try:
            self._init_face_mesh()
        except RuntimeError as e:
            print(f"Error: {e}", file=sys.stderr)
            self._cap.release()
            return None

        self._motion_landmarks = []
        self._stability_count = 0
        window_name = "Auto Face Capture — ESC to cancel"
        show_window = self.config.display_window
        timeout_sec = self.config.timeout_seconds
        start_time = time.monotonic()

        try:
            while True:
                if timeout_sec > 0 and (time.monotonic() - start_time) >= timeout_sec:
                    if not show_window:
                        print("Auto-capture timeout.", file=sys.stderr)
                    return None

                ret, frame = self._cap.read()
                if not ret or frame is None:
                    print("Failed to read frame.", file=sys.stderr)
                    break

                results, face_roi, bbox = self._process_frame(frame)
                face_center_norm = None
                if bbox is not None:
                    x_min, y_min, x_max, y_max = bbox
                    face_center_norm = ((x_min + x_max) / 2 / frame.shape[1], (y_min + y_max) / 2 / frame.shape[0])

                all_ok = self._all_conditions_passed(results)
                if all_ok:
                    self._stability_count += 1
                else:
                    self._stability_count = 0

                self._draw_overlay(
                    frame,
                    face_center_norm,
                    bbox,
                    results,
                    self._stability_count,
                    green_border=False,
                )

                if show_window:
                    cv2.imshow(window_name, frame)
                    key = cv2.waitKey(1) & 0xFF
                    if key == 27:  # ESC
                        print("Cancelled by user (ESC).")
                        return None
                else:
                    time.sleep(1.0 / max(1, self.config.target_fps))

                if self._stability_count >= self.config.stability_frames_required:
                    # Capture: green border, beep, save
                    self._draw_overlay(
                        frame,
                        face_center_norm,
                        bbox,
                        results,
                        self._stability_count,
                        green_border=True,
                    )
                    if show_window:
                        cv2.imshow(window_name, frame)
                        cv2.waitKey(100)

                    if self.config.beep_on_capture:
                        _beep(200, 1000)

                    timestamp = time.strftime("%Y%m%d_%H%M%S")
                    filename = f"{self.config.image_filename_prefix}_{timestamp}.{self.config.image_extension}"
                    out_path = Path(self.config.output_dir) / filename
                    out_path = out_path.resolve()
                    cv2.imwrite(
                        str(out_path),
                        frame,
                        [cv2.IMWRITE_JPEG_QUALITY, self.config.jpeg_quality],
                    )
                    print(f"Captured: {out_path}")
                    return str(out_path)

            return None
        finally:
            if self._cap is not None:
                self._cap.release()
            if show_window:
                cv2.destroyAllWindows()
            if self._face_mesh is not None:
                self._face_mesh.close()


# =============================================================================
# Entry point
# =============================================================================

def main() -> int:
    config = AutoCaptureConfig(
        camera_index=0,
        frame_width=640,
        frame_height=480,
        stability_frames_required=15,
        output_dir="captures",
        debug_mode=False,  # Set True to see per-metric PASS/FAIL and values
    )
    capture = AutoFaceCapture(config)
    path = capture.run()
    return 0 if path else 1


if __name__ == "__main__":
    sys.exit(main())
