#!/bin/bash
# Скрипт для запуска нескольких радио-серверов на Linux/Mac

# Пример использования:
# ./start-multiple.sh 8081 /path/to/music1 8082 /path/to/music2 8083 /path/to/music3

if [ $# -eq 0 ]; then
    echo "Использование: $0 PORT1 DIR1 [PORT2 DIR2] [PORT3 DIR3] ..."
    echo "Пример: $0 8081 /path/to/music1 8082 /path/to/music2"
    exit 1
fi

if [ $(($# % 2)) -ne 0 ]; then
    echo "Ошибка: Количество аргументов должно быть четным (PORT DIR PORT DIR ...)"
    exit 1
fi

count=0
while [ $# -gt 0 ]; do
    PORT=$1
    DIR_MUSIC=$2
    shift 2
    
    if [ -z "$PORT" ] || [ -z "$DIR_MUSIC" ]; then
        echo "Ошибка: Не указан порт или директория"
        continue
    fi
    
    count=$((count + 1))
    echo "Запуск сервера #$count на порту $PORT с директорией $DIR_MUSIC"
    
    # Запускаем в фоновом режиме
    python3 main.py --port "$PORT" --dir "$DIR_MUSIC" > "radio-$PORT.log" 2>&1 &
    echo "  PID: $!"
done

echo ""
echo "Запущено серверов: $count"
echo "Логи сохраняются в файлы radio-PORT.log"
echo "Для остановки используйте: pkill -f 'python3 main.py'"

