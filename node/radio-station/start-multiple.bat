@echo off
REM Скрипт для запуска нескольких радио-серверов на Windows

REM Пример использования:
REM start-multiple.bat 8081 C:\Music1 8082 C:\Music2 8083 C:\Music3

if "%~1"=="" (
    echo Использование: start-multiple.bat PORT1 DIR1 [PORT2 DIR2] [PORT3 DIR3] ...
    echo Пример: start-multiple.bat 8081 C:\Music1 8082 C:\Music2
    pause
    exit /b 1
)

setlocal enabledelayedexpansion

set count=0
:loop
if "%~1"=="" goto :end
set /a count+=1
set PORT=%~1
set DIR_MUSIC=%~2
shift
shift

if "!PORT!"=="" goto :end
if "!DIR_MUSIC!"=="" (
    echo Ошибка: Не указана директория для порта !PORT!
    goto :end
)

echo Запуск сервера #!count! на порту !PORT! с директорией !DIR_MUSIC!
start "Radio Server !PORT!" cmd /k "set PORT=!PORT! && set DIR_MUSIC=!DIR_MUSIC! && python main.py --port !PORT! --dir \"!DIR_MUSIC!\""

goto :loop

:end
echo.
echo Запущено серверов: !count!
echo Для остановки закройте окна с серверами
pause

