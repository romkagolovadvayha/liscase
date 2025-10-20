@echo off
REM Setup hosts file for Docker development
REM Run as Administrator!

echo ================================================================
echo    Docker LiSCase - Hosts Setup
echo ================================================================
echo.

set HOSTS_FILE=%SystemRoot%\System32\drivers\etc\hosts

echo Checking administrator privileges...
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo.
    echo [ERROR] This script requires administrator privileges!
    echo         Right-click and select "Run as administrator"
    echo.
    pause
    exit /b 1
)

echo [OK] Running as administrator
echo.
echo Adding entries to hosts file...
echo File: %HOSTS_FILE%
echo.

REM Проверяем и добавляем записи
findstr /C:"backend.localhost" "%HOSTS_FILE%" >nul
if %errorLevel% neq 0 (
    echo 127.0.0.1 backend.localhost >> "%HOSTS_FILE%"
    echo [+] Added: backend.localhost
) else (
    echo [*] Already exists: backend.localhost
)

findstr /C:"api.localhost" "%HOSTS_FILE%" >nul
if %errorLevel% neq 0 (
    echo 127.0.0.1 api.localhost >> "%HOSTS_FILE%"
    echo [+] Added: api.localhost
) else (
    echo [*] Already exists: api.localhost
)

echo.
echo ================================================================
echo    Setup Complete!
echo ================================================================
echo.
echo Available URLs:
echo   Frontend: http://localhost:3025/
echo   Backend:  http://backend.localhost:3025/
echo   API:      http://api.localhost:3025/
echo.
pause

