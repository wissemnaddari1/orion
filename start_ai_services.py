"""
Orion AI Services Launcher
===========================
Starts the Python AI microservices used by the Orion ecosystem: same MySQL schema `orion`,
Spring Boot in backend/ (not started here), Symfony, and JavaFX can all call these URLs.

Run from the project root:  python start_ai_services.py
Press Ctrl+C to stop all services gracefully.

Quick port table (override with ORION_PORT_* env vars before launch):
  Port   Service                         Notable paths / Symfony & Java env
  ----   ------------------------------   ----------------------------------------
  8001   Certificate + CV (ai_service)    PYTHON_AI_SERVICE_URL — GET /health, CV/cert routes
  8002   Offer intelligence               OFFER_PREDICTION_SERVICE_URL / ORION_AI_OFFER_BASE_URL
                                         POST /predict-offer, /predict-offers, /enhance-offer,
                                         POST /nlp/analyze-message, GET /analytics/ai-impact, GET /health
  8003   Matchmaking (ai_matchmaking)     MATCHMAKING_API_URL — POST /rank, GET /health (Spring calls 8003)
  8004   Contract generator               CONTRACT_GENERATOR_API_URL — POST /generate-contract, GET /health
  8015   Ticket AI (ai_ticket)            TICKET_AI_API_URL / TICKET_SUPPORT_AI_URL — POST /ai/solve, GET /health
  5000   Face (ai_face_service)           FACE_SERVICE_URL
  5010   Request AI Flask (ai_request)    SERVICE_REQUEST_AI_URL — POST /ai-predict, GET /categories, GET /health
                                         (5010 avoids clash with face on 5000; set ORION_PORT_REQUEST_AI=5000 only if face is off)
  8090   Optional cv_ml_service/          CV_ML_SERVICE_URL — POST /parse, GET /health (if folder exists)

Spring Boot is NOT started here. Use ORION_API_BASE_URL (e.g. http://127.0.0.1:8081) and ORION_API_PORT
in .env / application.properties so the desktop app and Symfony agree with Java.

One Python process per port is shared by Symfony and Spring: same base URL from each backend — no
duplicate AI stack unless you isolate by host or port on purpose.
"""

import os
import subprocess
import sys
import signal
import time
import threading
import queue
from pathlib import Path

ROOT = Path(__file__).resolve().parent


def _python_ok(exe: Path | str) -> bool:
    """True if this interpreter runs (avoids broken venvs pointing at another user's store path)."""
    p = Path(exe)
    if not p.is_file():
        return False
    try:
        r = subprocess.run(
            [str(p), "-c", "import sys; sys.exit(0)"],
            cwd=str(ROOT),
            capture_output=True,
            text=True,
            timeout=20,
        )
        return r.returncode == 0
    except (OSError, subprocess.TimeoutExpired, subprocess.SubprocessError):
        return False


def _resolved_interpreter(svc_cwd: Path) -> str:
    """Prefer ORION_PYTHON, then a working per-service or root .venv, else the Python that launched this script."""
    env_py = (os.environ.get("ORION_PYTHON") or "").strip().strip('"')
    if env_py and _python_ok(env_py):
        return str(Path(env_py).resolve())

    for vp in (
        svc_cwd / "venv" / "Scripts" / "python.exe",
        svc_cwd / ".venv" / "Scripts" / "python.exe",
        ROOT / ".venv" / "Scripts" / "python.exe",
    ):
        if _python_ok(vp):
            return str(vp.resolve())

    return sys.executable


def _p(key: str, default: int) -> int:
    try:
        return int(os.environ.get(key, str(default)))
    except ValueError:
        return default


PORT_CERT = _p("ORION_PORT_CERT", 8001)
PORT_OFFER = _p("ORION_PORT_OFFER", 8002)
PORT_MATCH = _p("ORION_PORT_MATCHMAKING", 8003)
PORT_CONTRACT = _p("ORION_PORT_CONTRACT", 8004)
PORT_TICKET = _p("ORION_PORT_TICKET_AI", 8015)
PORT_FACE = _p("ORION_PORT_FACE", 5000)
PORT_REQUEST = _p("ORION_PORT_REQUEST_AI", 5010)
PORT_CV_ML = _p("ORION_PORT_CV_ML", 8090)

SERVICES = [
    {
        "name": "Certificate + CV (ai_service)",
        "cwd": ROOT / "ai_service",
        "cmd": [
            sys.executable,
            "-m",
            "uvicorn",
            "main:app",
            "--host",
            "127.0.0.1",
            "--port",
            str(PORT_CERT),
            "--reload",
        ],
        "port": PORT_CERT,
    },
    {
        "name": "Offer intelligence (predict/enhance/NLP/analytics)",
        "cwd": ROOT / "ai_services" / "offer_predictor",
        "cmd": [
            sys.executable,
            "-m",
            "uvicorn",
            "main:app",
            "--host",
            "127.0.0.1",
            "--port",
            str(PORT_OFFER),
            "--reload",
        ],
        "port": PORT_OFFER,
        "extra_env": {"PORT": str(PORT_OFFER)},
    },
    {
        "name": "Matchmaking (/rank)",
        "cwd": ROOT,
        "cmd": [
            sys.executable,
            "-m",
            "uvicorn",
            "ai_matchmaking.api:app",
            "--host",
            "127.0.0.1",
            "--port",
            str(PORT_MATCH),
            "--reload",
        ],
        "port": PORT_MATCH,
    },
    {
        "name": "Contract generator",
        "cwd": ROOT,
        "cmd": [
            sys.executable,
            "-m",
            "uvicorn",
            "ai_contract_generator.api:app",
            "--host",
            "127.0.0.1",
            "--port",
            str(PORT_CONTRACT),
            "--reload",
        ],
        "port": PORT_CONTRACT,
    },
    {
        "name": "Ticket support AI",
        "cwd": ROOT / "ai_service",
        "cmd": [
            sys.executable,
            "-m",
            "uvicorn",
            "ticket_support_api:app",
            "--host",
            "127.0.0.1",
            "--port",
            str(PORT_TICKET),
            "--reload",
        ],
        "port": PORT_TICKET,
        # So ticket_support_api._listen_port() matches uvicorn when port is overridden.
        "extra_env": {
            "ORION_PORT_TICKET_AI": str(PORT_TICKET),
            "PORT": str(PORT_TICKET),
        },
    },
    {
        "name": "Face service",
        "cwd": ROOT / "ai_face_service",
        "cmd": [
            sys.executable,
            "-m",
            "uvicorn",
            "main:app",
            "--host",
            "127.0.0.1",
            "--port",
            str(PORT_FACE),
            "--reload",
        ],
        "port": PORT_FACE,
    },
    {
        "name": "Request AI (Flask /ai-predict)",
        "cwd": ROOT / "ai_request_service",
        "cmd": [sys.executable, "app.py"],
        "port": PORT_REQUEST,
        "extra_env": {"PORT": str(PORT_REQUEST)},
    },
]

_cv_ml_dir = ROOT / "cv_ml_service"
if _cv_ml_dir.is_dir() and (_cv_ml_dir / "main.py").is_file():
    SERVICES.append(
        {
            "name": "CV ML (optional)",
            "cwd": _cv_ml_dir,
            "cmd": [
                sys.executable,
                "-m",
                "uvicorn",
                "main:app",
                "--host",
                "127.0.0.1",
                "--port",
                str(PORT_CV_ML),
                "--reload",
            ],
            "port": PORT_CV_ML,
        }
    )

COLORS = ["\033[92m", "\033[94m", "\033[93m", "\033[95m", "\033[96m"]
RESET = "\033[0m"
BOLD = "\033[1m"
RED = "\033[91m"
GREEN = "\033[92m"


def print_banner():
    print(f"""
{BOLD}╔══════════════════════════════════════════════════════╗
║          Orion AI services (Python stack)            ║
╚══════════════════════════════════════════════════════╝{RESET}
""")


def print_service_table():
    print(f"  {'Service':<42} {'Port':<8} {'Status'}")
    print(f"  {'─' * 42} {'─' * 8} {'─' * 10}")
    for svc in SERVICES:
        print(f"  {svc['name']:<42} {svc['port']:<8} {GREEN}Starting...{RESET}")
    print()
    print(f"  {BOLD}Spring Boot API:{RESET} not started here — align ORION_API_BASE_URL / ORION_API_PORT with Java (e.g. http://127.0.0.1:8081)")
    print(f"  {BOLD}Matchmaking:{RESET} Spring uses POST http://127.0.0.1:{PORT_MATCH}/rank — keep MATCHMAKING_API_URL in sync")
    print(f"  {BOLD}Offer AI:{RESET} POST ...{PORT_OFFER}/predict-offer | /enhance-offer | /nlp/analyze-message | GET /analytics/ai-impact")
    print(f"  {BOLD}Request AI (Flask):{RESET} POST ...{PORT_REQUEST}/ai-predict — SERVICE_REQUEST_AI_URL (default 5010 = no clash with face 5000)")
    print()


def start_services():
    processes = []

    for i, svc in enumerate(SERVICES):
        color = COLORS[i % len(COLORS)]
        name = svc["name"]
        cwd = str(svc["cwd"])

        if not os.path.isdir(cwd):
            print(f"  {RED}✗ {name}: directory not found → {cwd}{RESET}")
            continue

        env = os.environ.copy()
        env["PYTHONUNBUFFERED"] = "1"
        env["PYTHONUTF8"] = "1"
        for ek, ev in (svc.get("extra_env") or {}).items():
            env[ek] = ev

        python_exe = _resolved_interpreter(Path(svc["cwd"]))
        cmd = [python_exe if c == sys.executable else c for c in svc["cmd"]]

        try:
            proc = subprocess.Popen(
                cmd,
                cwd=cwd,
                env=env,
                stdout=subprocess.PIPE,
                stderr=subprocess.STDOUT,
                text=True,
                encoding="utf-8",
                errors="replace",
                bufsize=1,
            )
            processes.append({"proc": proc, "name": name, "color": color, "port": svc["port"]})
            print(f"  {color}✓ {name:<42}{RESET} PID {proc.pid:<8} → http://127.0.0.1:{svc['port']}")
        except Exception as e:
            print(f"  {RED}✗ {name}: failed to start → {e}{RESET}")

    return processes


def monitor(processes):
    log_queue = queue.Queue()

    def reader_thread(p):
        try:
            for line in iter(p["proc"].stdout.readline, ""):
                if not line:
                    break
                log_queue.put((p, line.rstrip()))
        except Exception:
            pass
        finally:
            p["proc"].stdout.close()

    threads = []
    for p in processes:
        t = threading.Thread(target=reader_thread, args=(p,), daemon=True)
        t.start()
        threads.append(t)

    try:
        while processes:
            for p in processes[:]:
                ret = p["proc"].poll()
                if ret is not None:
                    print(f"\n  {RED}✗ {p['name']} exited with code {ret}{RESET}")
                    processes.remove(p)

            try:
                while True:
                    p, line = log_queue.get_nowait()
                    tag = f"{p['color']}[{p['name']:<38}]{RESET}"
                    print(f"  {tag} {line}")
            except queue.Empty:
                pass

            time.sleep(0.1)
    except KeyboardInterrupt:
        pass


def stop_all(processes):
    print(f"\n{BOLD}Shutting down all services...{RESET}")
    for p in processes:
        try:
            p["proc"].terminate()
        except OSError:
            pass

    deadline = time.time() + 5
    for p in processes:
        remaining = max(0, deadline - time.time())
        try:
            p["proc"].wait(timeout=remaining)
            print(f"  {GREEN}✓ {p['name']} stopped{RESET}")
        except subprocess.TimeoutExpired:
            p["proc"].kill()
            print(f"  {RED}✗ {p['name']} killed{RESET}")


def main():
    if sys.platform == "win32":
        os.system("")
        try:
            sys.stdout.reconfigure(encoding="utf-8")
        except AttributeError:
            import codecs

            sys.stdout = codecs.getwriter("utf-8")(sys.stdout.detach())

    print_banner()
    print_service_table()

    venv_shim = ROOT / ".venv" / "Scripts" / "python.exe"
    if venv_shim.is_file() and not _python_ok(venv_shim):
        print(
            f"  {RED}Note:{RESET} {venv_shim} is not runnable (often copied from another PC/user). "
            f"Child services use: {sys.executable}\n"
            f"  Fix: delete .venv and run  python -m venv .venv  then  setup_ai.bat\n"
            f"  Or set ORION_PYTHON to a full path to python.exe\n"
        )

    processes = start_services()

    if not processes:
        print(f"\n  {RED}No services started. Check paths and dependencies.{RESET}")
        sys.exit(1)

    print(f"\n{BOLD}  All services running. Press Ctrl+C to stop.{RESET}\n")
    print(f"  {'─' * 55}\n")

    try:
        monitor(processes)
    finally:
        stop_all(processes)


if __name__ == "__main__":
    main()
