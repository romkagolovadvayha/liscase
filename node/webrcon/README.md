# WebRCON - Панель управления серверами

Веб-интерфейс для управления игровыми серверами через RCON протокол.

## Возможности

- 📊 Просмотр списка серверов со статусом подключения
- 🎮 Отправка RCON команд на серверы
- 📜 История выполненных команд
- 🔄 Автоматическое переподключение при разрыве соединения
- ⚡ Очередь команд для предотвращения конфликтов
- 🎨 Современный веб-интерфейс

## Установка

1. Установите зависимости:
```bash
npm install
```

2. Создайте файл `.env` в корне проекта:
```env
DB_HOST=localhost
DB_USER=your_db_user
DB_PASSWORD=your_db_password
DB_NAME=your_database_name
PORT=3010
```

3. Запустите сервис:
```bash
npm start
```

## Использование

После запуска откройте в браузере:
```
http://localhost:3010
```

### API Endpoints

- `GET /api/servers` - Получить список серверов со статусом
- `GET /api/status` - Получить статус соединений
- `GET /api/history?limit=50&server=tag` - Получить историю команд
- `POST /send` - Отправить команду на сервер

Пример отправки команды:
```bash
curl -X POST http://localhost:3010/send \
  -H "Content-Type: application/json" \
  -d '{"server": "server_tag", "command": "status"}'
```

## Структура проекта

```
webrcon/
├── src/
│   ├── rcon-service.js  # Основной сервис RCON
│   └── send.js          # Старый скрипт (legacy)
├── public/
│   ├── index.html       # Веб-интерфейс
│   ├── css/
│   │   └── style.css    # Стили
│   └── js/
│       └── app.js       # JavaScript логика
├── package.json
└── README.md
```

## Требования к базе данных

Приложение использует таблицы:
- `servers` - список серверов с полями: `tag`, `ip`, `rcon`, `rcon_password`, `status`
- `rcon_tasks` - история команд с полями: `server_tag`, `command`, `result`, `status`, `created_at`

## Особенности

- Автоматическое переподключение при разрыве WebSocket соединения
- Очередь команд для каждого сервера (последовательное выполнение)
- Сохранение истории всех выполненных команд в базу данных
- CORS поддержка для API

