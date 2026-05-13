@echo off
setlocal EnableDelayedExpansion
REM Installs AI Python deps, then opens a second window running start_ai_services.py (all Orion microservices).
REM For daily use without reinstall: double-click start_ai_services.bat or run python start_ai_services.py

cd /d "%~dp0"
if errorlevel 1 (
    echo ERROR: Could not cd to script directory.
    pause
    exit /b 1
)

echo ======================================================
echo  ORION AI SERVICES - WINDOWS SETUP
echo ======================================================
echo  Folder: %CD%
echo.

REM --- Pick a working Python (goto must not run inside (...) blocks in cmd.exe) ---
set "PYEXE="
set "PYARGS="

if exist "%CD%\.venv\Scripts\python.exe" goto chk_venv
goto after_venv
:chk_venv
"%CD%\.venv\Scripts\python.exe" -c "import sys" 1>nul 2>nul
if not errorlevel 1 goto venv_works
echo WARNING: .venv\Scripts\python.exe exists but does not run.
echo Recreate with:  python -m venv .venv
echo.
goto after_venv
:venv_works
set "PYEXE=%CD%\.venv\Scripts\python.exe"
echo Using project venv: !PYEXE!
goto py_ok
:after_venv

where py >nul 2>nul
if errorlevel 1 goto try_plain_python
py -3 -c "import sys" 1>nul 2>nul
if errorlevel 1 goto try_plain_python
set "PYEXE=py"
set "PYARGS=-3"
echo Using Python launcher: py -3
goto py_ok

:try_plain_python
where python >nul 2>nul
if errorlevel 1 goto no_python
python -c "import sys" 1>nul 2>nul
if errorlevel 1 goto no_python
set "PYEXE=python"
echo Using: python from PATH
goto py_ok

:no_python
echo ERROR: No working Python found.
echo Fix one of:
echo   1^) Recreate venv in this folder:  python -m venv .venv
echo   2^) Install Python 3 from python.org and ensure "py" or "python" is on PATH
pause
exit /b 1

:py_ok
echo.

echo [1/5] Installing core AI dependencies (requirements_all.txt^)...
if not exist "%CD%\requirements_all.txt" (
    echo ERROR: requirements_all.txt not found in %CD%
    pause
    exit /b 1
)

if defined PYARGS (
    "%PYEXE%" %PYARGS% -m pip install --upgrade pip setuptools wheel
) else (
    "%PYEXE%" -m pip install --upgrade pip setuptools wheel
)
if errorlevel 1 echo WARNING: pip upgrade had issues. Continuing...

if defined PYARGS (
    "%PYEXE%" %PYARGS% -m pip install -r "%CD%\requirements_all.txt"
) else (
    "%PYEXE%" -m pip install -r "%CD%\requirements_all.txt"
)
if errorlevel 1 (
    echo.
    echo WARNING: Some packages from requirements_all.txt failed. Continuing with face stack...
)

echo.
echo [2/5] Installing dlib-bin (pre-built Windows wheels^)...
if defined PYARGS (
    "%PYEXE%" %PYARGS% -m pip install dlib-bin
) else (
    "%PYEXE%" -m pip install dlib-bin
)
if errorlevel 1 (
    echo ERROR: Could not install dlib-bin.
    pause
    exit /b 1
)

echo.
echo [3/5] Installing face-recognition (no-deps^)...
if defined PYARGS (
    "%PYEXE%" %PYARGS% -m pip install --no-deps face-recognition==1.3.0
) else (
    "%PYEXE%" -m pip install --no-deps face-recognition==1.3.0
)
if errorlevel 1 (
    echo ERROR: Could not install face-recognition.
    pause
    exit /b 1
)

echo.
echo [4/5] Installing face-recognition-models...
if defined PYARGS (
    "%PYEXE%" %PYARGS% -m pip install face-recognition-models
) else (
    "%PYEXE%" -m pip install face-recognition-models
)
if errorlevel 1 (
    echo ERROR: Could not install face-recognition-models.
    pause
    exit /b 1
)

echo.
echo ======================================================
echo  SETUP COMPLETE
echo ======================================================
echo.

if not exist "%CD%\start_ai_services.py" (
    echo ERROR: start_ai_services.py not found in %CD%
    echo Run this script from the CodeVeins / Orion project root.
    pause
    endlocal
    exit /b 1
)

echo [5/5] Starting Orion AI stack in a new window...
echo       ^(cert/CV 8001, offer 8002, matchmaking 8003, contract 8004,
echo        ticket AI 8015, face 5000, request Flask 5010 - see start_ai_services.py^)
echo       Close that window or press Ctrl+C there to stop all services.
echo.

REM New console so pip output above stays readable; same Python as setup (venv / py -3 / python).
if defined PYARGS (
    start "Orion AI services" /D "%CD%" cmd /k "%PYEXE% %PYARGS% start_ai_services.py"
) else (
    start "Orion AI services" /D "%CD%" cmd /k ""%PYEXE%" start_ai_services.py"
)

echo A window titled "Orion AI services" should open. This setup window can be closed.
echo To run the stack later without reinstalling deps:  python start_ai_services.py
echo   or double-click start_ai_services.bat in this folder.
echo.
pause
endlocal
