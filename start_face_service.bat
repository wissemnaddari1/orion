@echo off
REM Start the Python face recognition service (required for face enrollment).
REM Run this before using face enrollment in the app.
cd /d "%~dp0"
echo Starting face recognition service on http://127.0.0.1:8002 ...
python -m uvicorn ai_face_service.main:app --host 127.0.0.1 --port 8002
pause
