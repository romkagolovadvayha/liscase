# Инструкция по деплою на продакшн

## Подготовка к деплою

### 1. Установка зависимостей

```bash
cd frontend-new
npm install --production=false
```

### 2. Настройка переменных окружения

Создайте файл `.env.production` в корне проекта `frontend-new/`:

```env
# База данных
DB_HOST=localhost
DB_USER=your_db_user
DB_PASSWORD=your_db_password
DB_NAME=your_db_name

# CDN для статических файлов
CDN_URL=https://cdn.yourdomain.com

# CSRF токен
CSRF_TOKEN=your_csrf_token_here

# URL сайта (для SSR запросов)
NEXT_PUBLIC_SITE_URL=https://yourdomain.com

# WebSocket сервер
NEXT_PUBLIC_WS_URL=wss://yourdomain.com:4888
WS_PORT=4888

# URL старого фронтенда (для проксирования статики)
OLD_FRONTEND_URL=https://old.yourdomain.com

# API базовый URL
API_BASE_URL=https://api.yourdomain.com

# Node окружение
NODE_ENV=production
```

**Важно:** 
- `NEXT_PUBLIC_*` переменные доступны в браузере
- Остальные переменные доступны только на сервере
- Не коммитьте `.env.production` в git!

### 3. Сборка приложения

```bash
npm run build
```

Это создаст оптимизированную продакшн сборку в папке `.next/`.

## Запуск на продакшне

**ВАЖНО:** Перед запуском продакшн-сервера обязательно выполните сборку:
```bash
npm run build
```

Если вы попытаетесь запустить `npm run start` без сборки, вы получите ошибку:
```
Error: Could not find a production build in the '.next' directory.
```

**Примечание:** Во время сборки вы можете увидеть предупреждения о динамических маршрутах (например, "Route couldn't be rendered statically because it used `cookies`"). Это нормально - эти страницы будут рендериться динамически при запросе, что и требуется для страниц с аутентификацией и пользовательскими данными. Сборка завершится успешно, даже если некоторые страницы не могут быть предрендерены статически.

### Вариант 1: Запуск через PM2 (рекомендуется)

#### Установка PM2

```bash
npm install -g pm2
```

#### Создание конфигурации PM2

Создайте файл `ecosystem.config.js` в корне проекта:

```javascript
module.exports = {
  apps: [
    {
      name: 'liscase-frontend',
      script: 'node_modules/next/dist/bin/next',
      args: 'start',
      cwd: './frontend-new',
      instances: 2, // Количество инстансов (по количеству CPU)
      exec_mode: 'cluster',
      env: {
        NODE_ENV: 'production',
        PORT: 3000,
      },
      error_file: './logs/frontend-error.log',
      out_file: './logs/frontend-out.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      merge_logs: true,
      autorestart: true,
      watch: false,
      max_memory_restart: '1G',
    },
    {
      name: 'liscase-websocket',
      script: 'tsx',
      args: 'src/server/index.ts',
      cwd: './frontend-new',
      instances: 1,
      exec_mode: 'fork',
      env: {
        NODE_ENV: 'production',
        WS_PORT: 4888,
      },
      error_file: './logs/ws-error.log',
      out_file: './logs/ws-out.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      merge_logs: true,
      autorestart: true,
      watch: false,
      max_memory_restart: '500M',
    },
  ],
};
```

#### Запуск через PM2

```bash
# Запуск всех процессов
pm2 start ecosystem.config.js

# Или запуск отдельно
pm2 start ecosystem.config.js --only liscase-frontend
pm2 start ecosystem.config.js --only liscase-websocket

# Сохранение конфигурации для автозапуска
pm2 save
pm2 startup
```

#### Управление процессами

```bash
# Статус
pm2 status

# Логи
pm2 logs

# Перезапуск
pm2 restart all

# Остановка
pm2 stop all

# Удаление
pm2 delete all
```

### Вариант 2: Запуск через systemd

#### Создание сервиса для Next.js

Создайте файл `/etc/systemd/system/liscase-frontend.service`:

```ini
[Unit]
Description=Liscase Frontend Next.js App
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/path/to/liscase/frontend-new
Environment=NODE_ENV=production
EnvironmentFile=/path/to/liscase/frontend-new/.env.production
ExecStart=/usr/bin/npm run start
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

#### Создание сервиса для WebSocket

Создайте файл `/etc/systemd/system/liscase-websocket.service`:

```ini
[Unit]
Description=Liscase WebSocket Server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/path/to/liscase/frontend-new
Environment=NODE_ENV=production
EnvironmentFile=/path/to/liscase/frontend-new/.env.production
ExecStart=/usr/bin/npm run start:ws
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

#### Управление сервисами

```bash
# Перезагрузка конфигурации
sudo systemctl daemon-reload

# Запуск
sudo systemctl start liscase-frontend
sudo systemctl start liscase-websocket

# Включение автозапуска
sudo systemctl enable liscase-frontend
sudo systemctl enable liscase-websocket

# Статус
sudo systemctl status liscase-frontend
sudo systemctl status liscase-websocket

# Логи
sudo journalctl -u liscase-frontend -f
sudo journalctl -u liscase-websocket -f
```

### Вариант 3: Запуск напрямую

```bash
# Запуск Next.js сервера
npm run start

# В другом терминале - запуск WebSocket сервера
npm run start:ws

# Или одновременно (через concurrently)
npm run start:all
```

## Настройка Nginx (реверс-прокси)

Создайте конфигурацию `/etc/nginx/sites-available/liscase`:

```nginx
upstream nextjs {
    server localhost:3000;
}

upstream websocket {
    server localhost:4888;
}

server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    
    # Редирект на HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;

    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/key.pem;

    # Логи
    access_log /var/log/nginx/liscase-access.log;
    error_log /var/log/nginx/liscase-error.log;

    # Максимальный размер загружаемых файлов
    client_max_body_size 10M;

    # Проксирование на Next.js
    location / {
        proxy_pass http://nextjs;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
        
        # Таймауты
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }

    # WebSocket проксирование
    location /ws {
        proxy_pass http://websocket;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        # Таймауты для WebSocket
        proxy_connect_timeout 7d;
        proxy_send_timeout 7d;
        proxy_read_timeout 7d;
    }

    # Статические файлы (кэширование)
    location /_next/static {
        proxy_pass http://nextjs;
        proxy_cache_valid 200 60m;
        add_header Cache-Control "public, immutable";
    }

    # Favicon и другие статические файлы
    location ~* \.(ico|jpg|jpeg|png|gif|svg|woff|woff2|ttf|eot)$ {
        proxy_pass http://nextjs;
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

Активация конфигурации:

```bash
sudo ln -s /etc/nginx/sites-available/liscase /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## Проверка работы

1. **Проверка Next.js сервера:**
   ```bash
   curl http://localhost:3000
   ```

2. **Проверка WebSocket сервера:**
   ```bash
   curl http://localhost:4888
   ```

3. **Проверка через браузер:**
   - Откройте `https://yourdomain.com`
   - Проверьте консоль браузера на ошибки
   - Проверьте работу WebSocket соединения

## Мониторинг

### PM2 Monitoring

```bash
# Установка PM2 Plus (опционально)
pm2 link <secret_key> <public_key>

# Мониторинг в реальном времени
pm2 monit
```

### Логи

```bash
# PM2 логи
pm2 logs

# Nginx логи
tail -f /var/log/nginx/liscase-access.log
tail -f /var/log/nginx/liscase-error.log

# Systemd логи
journalctl -u liscase-frontend -f
journalctl -u liscase-websocket -f
```

## Обновление приложения

```bash
# 1. Остановить процессы
pm2 stop all
# или
sudo systemctl stop liscase-frontend liscase-websocket

# 2. Обновить код
git pull origin main

# 3. Установить зависимости (если изменились)
npm install

# 4. Пересобрать приложение
npm run build

# 5. Запустить процессы
pm2 start all
# или
sudo systemctl start liscase-frontend liscase-websocket
```

## Troubleshooting

### Проблема: Приложение не запускается

1. Проверьте логи:
   ```bash
   pm2 logs
   # или
   journalctl -u liscase-frontend -n 50
   ```

2. Проверьте переменные окружения:
   ```bash
   pm2 env 0
   ```

3. Проверьте порты:
   ```bash
   netstat -tulpn | grep :3000
   netstat -tulpn | grep :4888
   ```

### Проблема: Ошибки подключения к БД

1. Проверьте настройки БД в `.env.production`
2. Проверьте доступность БД:
   ```bash
   mysql -h DB_HOST -u DB_USER -p DB_NAME
   ```

### Проблема: WebSocket не работает

1. Проверьте, что WebSocket сервер запущен
2. Проверьте настройки Nginx для `/ws`
3. Проверьте переменную `NEXT_PUBLIC_WS_URL`

## Оптимизация производительности

1. **Включите кэширование в Next.js:**
   - Используйте `revalidate` в страницах
   - Настройте CDN для статических файлов

2. **Настройте PM2 кластер:**
   - Увеличьте `instances` до количества CPU ядер

3. **Оптимизируйте базу данных:**
   - Добавьте индексы
   - Используйте connection pooling

4. **Настройте Nginx кэширование:**
   - Кэшируйте статические файлы
   - Используйте gzip сжатие

