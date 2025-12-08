@echo off
REM Скрипт для запуска радио-сервера на Windows

REM Установите PORT и DIR_MUSIC перед запуском
REM Пример: set PORT=8080
REM Пример: set DIR_MUSIC=C:\Music

if "%PORT%"=="" (
    echo Ошибка: Переменная PORT не установлена
    echo Использование: set PORT=8080 && set DIR_MUSIC=C:\Music && start.bat
    pause
    exit /b 1
)

if "%DIR_MUSIC%"=="" (
    echo Ошибка: Переменная DIR_MUSIC не установлена
    echo Использование: set PORT=8080 && set DIR_MUSIC=C:\Music && start.bat
    pause
    exit /b 1
)

echo Запуск радио-сервера на порту %PORT%
echo Директория с музыкой: %DIR_MUSIC%
echo.

python main.py --port %PORT% --dir "%DIR_MUSIC%"

pause

