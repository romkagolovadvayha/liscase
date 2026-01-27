@echo off
echo ============================================================
echo ПРИНУДИТЕЛЬНЫЙ ПЕРЕЗАПУСК РАДИОСТАНЦИИ #1
echo ============================================================
echo.

echo [1/4] Убиваем все процессы Node.js...
taskkill /F /IM node.exe 2>nul
if %errorlevel% == 0 (
    echo ✅ Процессы Node.js убиты
) else (
    echo ⚠️  Node.js процессы не найдены
)

echo.
echo [2/4] Ждем 2 секунды...
timeout /t 2 /nobreak >nul

echo.
echo [3/4] Переходим в папку node/mode...
cd /d "%~dp0node\mode"

echo.
echo [4/4] Запускаем Radio Station #1...
echo.
start "Radio Station 1" node app.js 8081 "../../frontend/web/uploads/radio/1"

echo.
echo ✅ Радиостанция запущена в новом окне!
echo.
echo Ждем 5 секунд перед проверкой...
timeout /t 5 /nobreak >nul

echo.
echo Запускаем проверку...
cd /d "%~dp0"
php test_all_api.php

pause

