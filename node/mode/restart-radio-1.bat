@echo off
echo ============================================================
echo Restarting Radio Station #1 on port 8081...
echo ============================================================
echo.

REM Убиваем процесс на порту 8081
echo Killing existing Node.js process on port 8081...
for /f "tokens=5" %%a in ('netstat -aon ^| find ":8081" ^| find "LISTENING"') do (
    echo Found process PID: %%a
    taskkill /F /PID %%a
)

echo.
echo Waiting 2 seconds...
timeout /t 2 /nobreak > nul

echo.
echo Starting Radio Station #1...
echo.
node app.js 8081 "../../frontend/web/uploads/radio/1"

