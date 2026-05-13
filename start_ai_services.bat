@echo off
setlocal EnableDelayedExpansion
cd /d "%~dp0"

if not exist "%CD%\start_ai_services.py" (
    echo ERROR: start_ai_services.py not found in %CD%
    pause
    exit /b 1
)

set "PYEXE="
set "PYARGS="

if exist "%CD%\.venv\Scripts\python.exe" (
    "%CD%\.venv\Scripts\python.exe" -c "import sys" 1>nul 2>nul
    if not errorlevel 1 (
        set "PYEXE=%CD%\.venv\Scripts\python.exe"
        echo Using venv: !PYEXE!
        goto run_py
    )
)

where py >nul 2>nul
if not errorlevel 1 (
    py -3 -c "import sys" 1>nul 2>nul
    if not errorlevel 1 (
        set "PYEXE=py"
        set "PYARGS=-3"
        echo Using: py -3
        goto run_py
    )
)

where python >nul 2>nul
if not errorlevel 1 (
    python -c "import sys" 1>nul 2>nul
    if not errorlevel 1 (
        set "PYEXE=python"
        echo Using: python from PATH
        goto run_py
    )
)

echo ERROR: No working Python. Run setup_ai.bat to create .venv or install Python.
pause
exit /b 1

:run_py
echo Starting Orion AI services... ^(Ctrl+C stops all^)
echo.
if defined PYARGS (
    "%PYEXE%" %PYARGS% "%CD%\start_ai_services.py"
) else (
    "%PYEXE%" "%CD%\start_ai_services.py"
)
echo.
pause
endlocal
