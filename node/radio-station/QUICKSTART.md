# 🚀 Быстрый старт

## Установка

```bash
cd node/radio-station
pip install -r requirements.txt
```

## Запуск одного сервера

### Windows
```cmd
set PORT=8080
set DIR_MUSIC=C:\Music
python main.py --port %PORT% --dir "%DIR_MUSIC%"
```

### Linux/Mac
```bash
export PORT=8080
export DIR_MUSIC=/path/to/music
python3 main.py --port $PORT --dir "$DIR_MUSIC"
```

### Или через скрипты

**Windows:**
```cmd
set PORT=8080
set DIR_MUSIC=C:\Music
start.bat
```

**Linux/Mac:**
```bash
export PORT=8080
export DIR_MUSIC=/path/to/music
chmod +x start.sh
./start.sh
```

## Запуск нескольких серверов

### Windows
```cmd
start-multiple.bat 8081 C:\Music1 8082 C:\Music2 8083 C:\Music3
```

### Linux/Mac
```bash
chmod +x start-multiple.sh
./start-multiple.sh 8081 /path/to/music1 8082 /path/to/music2 8083 /path/to/music3
```

## Прослушивание

Откройте в браузере или плеере:
```
http://localhost:8080/stream
```

## API

Проверка статуса:
```bash
curl http://localhost:8080/api/status
```

Добавить трек в очередь:
```bash
curl -X POST http://localhost:8080/api/queue \
  -H "Content-Type: application/json" \
  -d '{"track_name": "song.mp3"}'
```

Перейти к следующему треку:
```bash
curl -X POST http://localhost:8080/api/next
```

## Примеры

### Запуск с перемешиванием
```bash
python main.py --port 8080 --dir /path/to/music --shuffle
```

### Запуск на конкретном хосте
```bash
python main.py --port 8080 --dir /path/to/music --host 127.0.0.1
```

### Режим отладки
```bash
python main.py --port 8080 --dir /path/to/music --debug
```

