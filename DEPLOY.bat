@echo off
REM LiSCase - One-Click Kubernetes Deployment for Windows
REM Просто двойной клик по этому файлу!

title LiSCase - Kubernetes Deployment

echo.
echo ╔══════════════════════════════════════════════════════╗
echo ║                                                      ║
echo ║              LiSCase Deployment                      ║
echo ║          Kubernetes One-Click Install                ║
echo ║                                                      ║
echo ╚══════════════════════════════════════════════════════╝
echo.

echo 🎯 Запуск развертывания...
echo.

REM Проверка PowerShell
where powershell >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo ❌ PowerShell не найден!
    pause
    exit /b 1
)

REM Запуск PowerShell скрипта
powershell -ExecutionPolicy Bypass -File "%~dp0ONE_CLICK_DEPLOY.ps1"

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ✅ Развертывание завершено успешно!
    echo.
    echo 🌐 Доступ к приложению:
    echo   Frontend: https://prostoj.store
    echo   Backend:  https://backend.prostoj.store
    echo.
) else (
    echo.
    echo ❌ Произошла ошибка при развертывании
    echo.
)

pause



