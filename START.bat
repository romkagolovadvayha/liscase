@echo off
REM LiSCase - Автоматический запуск через Docker Compose
REM Двойной клик для запуска!

title LiSCase - Production Deployment

echo.
echo ╔══════════════════════════════════════════════════════╗
echo ║                                                      ║
echo ║              LiSCase Production Start                ║
echo ║          Полная автоматизация запуска                ║
echo ║                                                      ║
echo ╚══════════════════════════════════════════════════════╝
echo.

echo 🎯 Запуск автоматического развертывания...
echo.

REM Проверка Docker
where docker >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo ❌ Docker не найден! Установите Docker Desktop.
    pause
    exit /b 1
)

REM Проверка docker-compose
where docker-compose >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo ❌ docker-compose не найден! Установите Docker Desktop.
    pause
    exit /b 1
)

echo ✅ Docker найден
echo.

REM Запуск PowerShell скрипта
powershell -ExecutionPolicy Bypass -File "%~dp0start.ps1"

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ✅ Приложение успешно запущено!
    echo.
) else (
    echo.
    echo ❌ Произошла ошибка
    echo.
)

pause


