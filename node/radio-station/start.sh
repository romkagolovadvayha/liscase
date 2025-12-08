#!/bin/bash
# Скрипт для запуска радио-сервера на Linux/Mac

# Установите PORT и DIR_MUSIC перед запуском
# Пример: export PORT=8080
# Пример: export DIR_MUSIC=/path/to/music

if [ -z "$PORT" ]; then
    echo "Ошибка: Переменная PORT не установлена"
    echo "Использование: export PORT=8080 && export DIR_MUSIC=/path/to/music && ./start.sh"
    exit 1
fi

if [ -z "$DIR_MUSIC" ]; then
    echo "Ошибка: Переменная DIR_MUSIC не установлена"
    echo "Использование: export PORT=8080 && export DIR_MUSIC=/path/to/music && ./start.sh"
    exit 1
fi

echo "Запуск радио-сервера на порту $PORT"
echo "Директория с музыкой: $DIR_MUSIC"
echo ""

python3 main.py --port "$PORT" --dir "$DIR_MUSIC"

