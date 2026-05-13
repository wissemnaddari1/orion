@echo off
REM Windows installation script for Orion Face Recognition Service
REM Uses dlib-bin (pre-built wheel) to avoid CMake requirement

echo Installing Orion Face Recognition Service for Windows...
echo.

REM Install dlib-bin first (pre-built wheel, no CMake needed)
echo [1/4] Installing dlib-bin (pre-built wheel)...
pip install dlib-bin
if errorlevel 1 (
    echo ERROR: Failed to install dlib-bin
    pause
    exit /b 1
)

REM Install face-recognition without dependencies to avoid dlib build
echo [2/4] Installing face-recognition...
pip install --no-deps face-recognition==1.3.0
if errorlevel 1 (
    echo ERROR: Failed to install face-recognition
    pause
    exit /b 1
)

REM Install face-recognition-models
echo [3/4] Installing face-recognition-models...
pip install face-recognition-models
if errorlevel 1 (
    echo ERROR: Failed to install face-recognition-models
    pause
    exit /b 1
)

REM Install remaining dependencies
echo [4/4] Installing remaining dependencies...
pip install fastapi==0.109.2 "uvicorn[standard]==0.27.1" "numpy>=2.0.0" "Pillow>=10.2.0"
if errorlevel 1 (
    echo ERROR: Failed to install dependencies
    pause
    exit /b 1
)

echo.
echo ========================================
echo Installation completed successfully!
echo ========================================
echo.
echo To start the service, run:
echo   uvicorn main:app --host 0.0.0.0 --port 8002
echo.
pause
