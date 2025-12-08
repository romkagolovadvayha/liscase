# 🎵 Radio Station - Музыкальный потоковый сервер

Современное решение для потоковой передачи музыки с REST API для управления очередью и плейлистом.

## ✨ Возможности

- 🎧 Потоковая передача аудио в форматах MP3, M4A, FLAC, OGG, WAV, AAC
- 📋 Управление очередью треков через REST API
- 🔀 Поддержка перемешивания треков
- 📊 Получение метаданных треков (артист, название, длительность)
- 👥 Отслеживание количества слушателей
- 🚀 Запуск нескольких серверов на разных портах
- 🔄 Автоматическое переключение треков
- 📡 Простой REST API

## 📦 Установка

### Требования

- Python 3.8 или выше
- pip (менеджер пакетов Python)

### Установка зависимостей

```bash
cd node/radio-station
pip install -r requirements.txt
```

Или используйте виртуальное окружение:

```bash
python -m venv venv
source venv/bin/activate  # На Windows: venv\Scripts\activate
pip install -r requirements.txt
```

## 🚀 Использование

### Базовый запуск

```bash
python main.py --port 8080 --dir /path/to/music
```

### С перемешиванием

```bash
python main.py --port 8080 --dir /path/to/music --shuffle
```

### Через переменные окружения

```bash
PORT=8080 DIR_MUSIC=/path/to/music python main.py
```

### Запуск нескольких серверов

```bash
# Терминал 1
python main.py --port 8081 --dir /path/to/music1

# Терминал 2
python main.py --port 8082 --dir /path/to/music2

# Терминал 3
python main.py --port 8083 --dir /path/to/music3 --shuffle
```

### Параметры командной строки

- `--port, -p` - Порт для прослушивания (по умолчанию: 8080 или из переменной PORT)
- `--dir, -d` - Директория с музыкой (по умолчанию: текущая директория или из переменной DIR_MUSIC)
- `--shuffle, -s` - Включить перемешивание треков
- `--host` - Хост для прослушивания (по умолчанию: 0.0.0.0)
- `--debug` - Включить режим отладки

## 📡 API Endpoints

### Потоковая передача

#### GET /stream
Получить аудио поток

```bash
curl http://localhost:8080/stream
# Или откройте в браузере/плеере
http://localhost:8080/stream
```

### Информация

#### GET /api/status
Получить полный статус сервера

```bash
curl http://localhost:8080/api/status
```

**Response:**
```json
{
  "status": "running",
  "port": 8080,
  "music_directory": "/path/to/music",
  "listeners": 5,
  "current": {
    "name": "track1.mp3",
    "title": "Song Title",
    "artist": "Artist Name",
    "duration": 240,
    "file_path": "/path/to/music/track1.mp3"
  },
  "queue": [
    {
      "name": "track2.mp3",
      "title": "Another Song",
      "artist": "Another Artist",
      "duration": 180
    }
  ],
  "queue_length": 1,
  "playlist": [...],
  "total_tracks": 10,
  "shuffle": false
}
```

#### GET /api/current
Получить текущий трек

```bash
curl http://localhost:8080/api/current
```

#### GET /api/queue
Получить текущую очередь

```bash
curl http://localhost:8080/api/queue
```

#### GET /api/playlist
Получить полный плейлист

```bash
curl http://localhost:8080/api/playlist
```

#### GET /api/listeners
Получить количество слушателей

```bash
curl http://localhost:8080/api/listeners
```

### Управление очередью

#### POST /api/queue
Добавить трек в очередь

```bash
curl -X POST http://localhost:8080/api/queue \
  -H "Content-Type: application/json" \
  -d '{"track_name": "track1.mp3"}'
```

Добавить трек на определенную позицию:

```bash
curl -X POST http://localhost:8080/api/queue \
  -H "Content-Type: application/json" \
  -d '{"track_name": "track1.mp3", "position": 0}'
```

#### DELETE /api/queue/{position}
Удалить трек из очереди

```bash
curl -X DELETE http://localhost:8080/api/queue/0
```

#### POST /api/queue/clear
Очистить очередь

```bash
curl -X POST http://localhost:8080/api/queue/clear
```

#### POST /api/queue/shuffle
Перемешать очередь

```bash
curl -X POST http://localhost:8080/api/queue/shuffle
```

#### POST /api/next
Перейти к следующему треку

```bash
curl -X POST http://localhost:8080/api/next
```

### Управление плейлистом

#### POST /api/playlist/reload
Перезагрузить плейлист из директории

```bash
curl -X POST http://localhost:8080/api/playlist/reload
```

## 🎯 Примеры использования

### Запуск с автоматическим перемешиванием

```bash
python main.py --port 8080 --dir ~/Music --shuffle
```

### Управление очередью через API

```bash
# Получить текущую очередь
curl http://localhost:8080/api/queue

# Добавить трек в очередь
curl -X POST http://localhost:8080/api/queue \
  -H "Content-Type: application/json" \
  -d '{"track_name": "my_song.mp3"}'

# Перемешать очередь
curl -X POST http://localhost:8080/api/queue/shuffle

# Перейти к следующему треку
curl -X POST http://localhost:8080/api/next
```

### Прослушивание потока

```bash
# В VLC
vlc http://localhost:8080/stream

# В браузере
# Откройте http://localhost:8080/stream

# Через ffplay
ffplay http://localhost:8080/stream

# Через mplayer
mplayer http://localhost:8080/stream
```

## 🏗️ Архитектура

Проект состоит из следующих модулей:

- **main.py** - Точка входа, обработка аргументов командной строки
- **server.py** - Основной сервер Flask с маршрутами API
- **queue_manager.py** - Менеджер очереди и плейлиста
- **config.py** - Конфигурация и настройки

## 🔧 Технические детали

- Использует Flask для HTTP сервера и REST API
- Mutagen для чтения метаданных аудио файлов
- Потоковая передача через генераторы Python
- Thread-safe управление очередью с использованием блокировок
- Поддержка множественных клиентов одновременно

## 📝 Форматы аудио

Поддерживаются следующие форматы:
- MP3 (.mp3)
- M4A (.m4a)
- FLAC (.flac)
- OGG (.ogg)
- WAV (.wav)
- AAC (.aac)

## 🐛 Отладка

Для включения режима отладки:

```bash
python main.py --port 8080 --dir /path/to/music --debug
```

## 📄 Лицензия

Этот проект создан для использования в рамках проекта liscase.

## 🤝 Вклад

Если вы хотите улучшить проект, создайте issue или pull request.

