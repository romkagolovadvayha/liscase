# Cloud Deployment Guide

## 🌐 Универсальный docker-compose.yml

Один файл `docker-compose.yml` работает и локально, и в облаке через переменные окружения.

## ☁️ Деплой в облако (Timeweb Cloud)

### Шаг 1: Подключитесь к серверу

```bash
ssh user@your-server.com
cd /path/to/project
```

### Шаг 2: Создайте .env файл

```bash
cat > .env <<'EOF'
APP_PORT=80
ADMIN_STEAM_ID=76561198012345678
APACHE_LOG_DIR=/var/log/apache2
EOF
```

**Важно**: 
- `APP_PORT=80` - обязательно для облака
- Замените Steam ID на ваш (получить на https://steamid.io/)

### Шаг 3: Запустите Docker Compose

```bash
docker-compose down
docker-compose up -d
```

### Шаг 4: Проверьте статус

```bash
# Статус контейнеров
docker-compose ps

# Логи PHP
docker-compose logs php --tail 100

# Health check
curl http://localhost/health.php
```

Ожидаемый ответ:
```json
{
  "status": "OK",
  "timestamp": 1729467890,
  "datetime": "2025-10-21 03:00:00",
  "php_version": "7.4.33",
  "server": "Apache/2.4.54 (Debian)"
}
```

## 🏠 Локальная разработка

### Шаг 1: Создайте .env

```bash
# Windows PowerShell
@"
APP_PORT=3025
ADMIN_STEAM_ID=76561198012345678
APACHE_LOG_DIR=/var/log/apache2
"@ | Out-File -FilePath .env -Encoding utf8

# Linux/Mac
cat > .env <<'EOF'
APP_PORT=3025
ADMIN_STEAM_ID=76561198012345678
APACHE_LOG_DIR=/var/log/apache2
EOF
```

### Шаг 2: Настройте hosts

```bash
# Windows (от имени администратора)
.\SETUP_HOSTS.bat

# Linux/Mac
sudo ./setup-hosts.sh
```

### Шаг 3: Запустите

```bash
# Windows
START.bat

# Или напрямую
docker-compose up -d
```

### Доступ

- Frontend: http://localhost:3025/
- Backend: http://backend.localhost:3025/
- API: http://api.localhost:3025/
- phpMyAdmin: http://localhost:8080/

## 🔍 Troubleshooting

### 502 Bad Gateway в облаке

**Причина**: Неправильный порт в .env

**Решение**:
```bash
# На облачном сервере
echo "APP_PORT=80" > .env
docker-compose down && docker-compose up -d
```

### phpMyAdmin недоступен (порт 8080)

**Причина**: Порт 8080 может быть закрыт облачным firewall

**Решение А**: Откройте порт в firewall облачной платформы

**Решение Б**: SSH tunnel
```bash
ssh -L 8080:localhost:8080 user@server
# Откройте: http://localhost:8080
```

**Решение В**: Используйте настройки облачного Nginx для проксирования на порт 8080

### Долгая инициализация

Если контейнер долго стартует (из-за init.sql импорта):

```bash
# Проверьте логи
docker-compose logs mysql --tail 50

# MySQL импортирует дамп (11MB) ~2-3 минуты при первом запуске
```

### Deploy error при клонировании

**Причина**: Большой репозиторий (init.sql 11MB)

**Решение**: См. [DEPLOY_OPTIMIZATION.md](DEPLOY_OPTIMIZATION.md)

## 📊 Автоматические действия при деплое

При запуске `docker-compose up -d` автоматически выполняется:

1. ✅ Composer install (если нужно)
2. ✅ Yii init (Development или Production)
3. ✅ Создание runtime директорий
4. ✅ Установка прав доступа
5. ✅ Ожидание готовности MySQL
6. ✅ Импорт БД из `docker/mysql/init.sql`
7. ✅ Компиляция SCSS (`php yii scss/compile 1`)
8. ✅ Обновление настроек (`php yii settings/update`)
9. ✅ Создание администратора (`php yii admin/create` если задан ADMIN_STEAM_ID)
10. ✅ Настройка Apache виртуальных хостов
11. ✅ Запуск Apache

## 🎯 Checklist для облачного деплоя

- [ ] Создан файл `.env` с `APP_PORT=80`
- [ ] Добавлен `ADMIN_STEAM_ID` в .env
- [ ] Выполнено `docker-compose down && docker-compose up -d`
- [ ] Проверен статус: `docker-compose ps` (все контейнеры Up)
- [ ] Проверен health: `curl http://localhost/health.php` возвращает JSON
- [ ] Проверен доступ извне: сайт открывается без 502

## 📞 Если проблема остается

Предоставьте:
```bash
# 1. Статус контейнеров
docker-compose ps

# 2. Логи PHP (последние 100 строк)
docker-compose logs php --tail 100

# 3. Переменные окружения
cat .env

# 4. Health check изнутри
docker-compose exec php curl -I http://localhost/

# 5. Health check извне
curl -I http://your-domain.com/health.php
```

## 🚀 Быстрый старт для облака

```bash
# Одной командой
echo "APP_PORT=80" > .env && docker-compose up -d && docker-compose logs -f php
```

Следите за логами - должны увидеть все этапы инициализации и "Setup complete! Starting Apache..."

