"""
Local face recognition microservice (FastAPI).
Uses the face-recognition library (dlib) to compute embeddings and match faces.
No images are stored; only embeddings are computed and returned.

Integrated auto-capture: POST /auto-capture runs the webcam until optimal face
position is detected, then returns the captured image (base64) and its embedding.
"""

import base64
import re
import sys
from io import BytesIO
from pathlib import Path
from typing import Any

import face_recognition
import numpy as np
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

# Allow importing auto_face_capture from project root (parent of ai_face_service)
_svc_root = Path(__file__).resolve().parent
_project_root = _svc_root.parent
if str(_project_root) not in sys.path:
    sys.path.insert(0, str(_project_root))

from auto_face_capture import AutoCaptureConfig, AutoFaceCapture  # noqa: E402

app = FastAPI(title="Orion Face Service", version="1.0.0")


def decode_image_base64(image_base64: str) -> bytes:
    """Extract raw base64 from data URL or plain base64; decode to bytes."""
    s = (image_base64 or "").strip()
    if not s:
        raise ValueError("Empty image data")
    # Optional data URL prefix: data:image/jpeg;base64,...
    m = re.match(r"^data:image/[a-z]+;base64,(.+)$", s, re.I)
    if m:
        s = m.group(1)
    try:
        return base64.b64decode(s, validate=True)
    except Exception as e:
        raise ValueError(f"Invalid base64: {e}") from e


def load_image_bytes(data: bytes):
    """Load image from bytes into RGB numpy array for face_recognition."""
    try:
        from PIL import Image
        img = Image.open(BytesIO(data)).convert("RGB")
        return np.array(img)
    except Exception as e:
        raise ValueError(f"Bad image: {e}") from e


# --- Request/Response models ---


class EmbedRequest(BaseModel):
    image_base64: str = Field(..., description="Base64 image or data URL (e.g. data:image/jpeg;base64,...)")


class EmbedResponse(BaseModel):
    faces: int
    embedding: list[float]


class MatchCandidate(BaseModel):
    user_id: int
    embedding: list[float]


class MatchRequest(BaseModel):
    image_base64: str
    candidates: list[MatchCandidate]
    threshold: float = Field(default=0.6, ge=0.0, le=2.0, description="Max Euclidean distance to consider a match")


class MatchResponse(BaseModel):
    matched: bool
    user_id: int | None = None
    distance: float | None = None
    threshold: float


class AutoCaptureRequest(BaseModel):
    """Optional overrides for auto-capture when called via API."""
    camera_index: int = Field(default=0, description="Webcam device index")
    timeout_seconds: float = Field(default=60.0, ge=5.0, le=300.0, description="Max wait time for capture")
    output_dir: str = Field(default="captures", description="Folder to save captured image (relative to cwd)")


class AutoCaptureResponse(BaseModel):
    success: bool
    image_base64: str | None = None
    embedding: list[float] | None = None
    path: str | None = None
    error: str | None = None


# --- Endpoints ---


@app.post("/embed", response_model=EmbedResponse)
def embed(req: EmbedRequest) -> Any:
    """
    Compute face embedding from a single image.
    Returns NO_FACE / MULTIPLE_FACES / BAD_IMAGE as HTTP 422 with detail.
    """
    try:
        raw = decode_image_base64(req.image_base64)
    except ValueError as e:
        raise HTTPException(status_code=422, detail={"error": "BAD_IMAGE", "message": str(e)})

    try:
        img = load_image_bytes(raw)
    except ValueError as e:
        raise HTTPException(status_code=422, detail={"error": "BAD_IMAGE", "message": str(e)})

    encodings = face_recognition.face_encodings(img)
    if len(encodings) == 0:
        raise HTTPException(
            status_code=422,
            detail={"error": "NO_FACE", "message": "No face detected in image."},
        )
    if len(encodings) > 1:
        raise HTTPException(
            status_code=422,
            detail={"error": "MULTIPLE_FACES", "message": "Multiple faces detected. Please ensure only one face is visible."},
        )

    embedding = encodings[0].tolist()
    return EmbedResponse(faces=1, embedding=embedding)


@app.post("/match", response_model=MatchResponse)
def match(req: MatchRequest) -> Any:
    """
    Get embedding from image, compare to candidates using Euclidean distance.
    Returns matched=True and user_id of best candidate if best distance <= threshold.
    """
    try:
        raw = decode_image_base64(req.image_base64)
    except ValueError as e:
        raise HTTPException(status_code=422, detail={"error": "BAD_IMAGE", "message": str(e)})

    try:
        img = load_image_bytes(raw)
    except ValueError as e:
        raise HTTPException(status_code=422, detail={"error": "BAD_IMAGE", "message": str(e)})

    encodings = face_recognition.face_encodings(img)
    if len(encodings) == 0:
        raise HTTPException(
            status_code=422,
            detail={"error": "NO_FACE", "message": "No face detected in image."},
        )
    if len(encodings) > 1:
        raise HTTPException(
            status_code=422,
            detail={"error": "MULTIPLE_FACES", "message": "Multiple faces detected."},
        )

    probe = encodings[0]
    threshold = float(req.threshold)
    candidates = req.candidates

    if not candidates:
        return MatchResponse(matched=False, user_id=None, distance=None, threshold=threshold)

    best_user_id: int | None = None
    best_distance: float = float("inf")

    for c in candidates:
        cand_arr = np.array(c.embedding, dtype=np.float64)
        if cand_arr.shape != probe.shape:
            continue
        dist = float(np.linalg.norm(probe - cand_arr))
        if dist < best_distance:
            best_distance = dist
            best_user_id = c.user_id

    matched = best_user_id is not None and best_distance <= threshold
    return MatchResponse(
        matched=matched,
        user_id=best_user_id if matched else None,
        distance=best_distance if best_user_id is not None else None,
        threshold=threshold,
    )


@app.post("/auto-capture", response_model=AutoCaptureResponse)
def auto_capture(req: AutoCaptureRequest | None = None) -> Any:
    """
    Run automatic face capture: open webcam, wait until face is in optimal position
    (centered, frontal, sharp, stable), then capture one image and return it with
    its face embedding. No window is shown (headless); request blocks until capture
    or timeout. Requires a webcam on the server machine.
    """
    req = req or AutoCaptureRequest()
    config = AutoCaptureConfig(
        camera_index=req.camera_index,
        output_dir=req.output_dir,
        display_window=False,
        timeout_seconds=req.timeout_seconds,
        beep_on_capture=False,
    )
    capture = AutoFaceCapture(config)
    path = capture.run()

    if not path:
        return AutoCaptureResponse(
            success=False,
            image_base64=None,
            embedding=None,
            path=None,
            error="Capture failed (timeout, no camera, or no face met conditions).",
        )

    try:
        with open(path, "rb") as f:
            image_bytes = f.read()
        image_base64 = base64.b64encode(image_bytes).decode("ascii")
    except OSError as e:
        return AutoCaptureResponse(
            success=False,
            image_base64=None,
            embedding=None,
            path=path,
            error=f"Could not read captured file: {e}.",
        )

    try:
        img = load_image_bytes(image_bytes)
    except ValueError as e:
        return AutoCaptureResponse(
            success=False,
            image_base64=image_base64,
            embedding=None,
            path=path,
            error=f"Captured image invalid: {e}.",
        )

    encodings = face_recognition.face_encodings(img)
    if len(encodings) == 0:
        return AutoCaptureResponse(
            success=False,
            image_base64=image_base64,
            embedding=None,
            path=path,
            error="No face detected in captured image.",
        )
    if len(encodings) > 1:
        return AutoCaptureResponse(
            success=False,
            image_base64=image_base64,
            embedding=None,
            path=path,
            error="Multiple faces in captured image.",
        )

    embedding = encodings[0].tolist()
    return AutoCaptureResponse(
        success=True,
        image_base64=image_base64,
        embedding=embedding,
        path=path,
        error=None,
    )


@app.get("/health")
def health() -> dict:
    return {"status": "ok"}


if __name__ == "__main__":
    import os
    import uvicorn

    port = int(os.environ.get("PORT", os.environ.get("ORION_PORT_FACE", "5000")))
    uvicorn.run(app, host="127.0.0.1", port=port)
