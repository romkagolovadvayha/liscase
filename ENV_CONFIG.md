# Environment Configuration (.env)

## 📋 Конфигурация для разных окружений

`docker-compose.yml` универсален и работает в локальной разработке и в облаке. Используйте `.env` файл для настройки.

## 🏠 Локальная разработка

Создайте `.env` файл:

```env
# Local Development Configuration

APP_PORT=3025
ADMIN_STEAM_ID=76561198012345678
APACHE_LOG_DIR=/var/log/apache2
```

Запуск:
```bash
docker-compose up -d
```

Доступ:
- Frontend: http://localhost:3025/
- Backend: http://backend.localhost:3025/
- API: http://api.localhost:3025/
- phpMyAdmin: http://localhost:8080/

## ☁️ Облачный деплой (Production)

На облачном сервере создайте `.env` файл:

```env
# Cloud/Production Configuration

APP_PORT=80
ADMIN_STEAM_ID=ВАШ_STEAM_ID
APACHE_LOG_DIR=/var/log/apache2
DB_PASSWORD=БЕЗОПАСНЫЙ_ПАРОЛЬ
```

**Важно**: Порт 80 необходим для облачного Nginx

Запуск:
```bash
docker-compose up -d
```

Доступ:
- Frontend: https://ваш-домен.com/
- Backend: Настройте через облачный Nginx
- API: Настройте через облачный Nginx
- phpMyAdmin: http://IP:8080/ (если порт открыт)

## 🔧 Переменные окружения

### Обязательные для локальной разработки

| Переменная | Значение по умолчанию | Описание |
|------------|----------------------|----------|
| `APP_PORT` | `80` | Порт для доступа к приложению |

### Опциональные

| Переменная | Значение по умолчанию | Описание |
|------------|----------------------|----------|
| `ADMIN_STEAM_ID` | (пусто) | Steam ID для автосоздания администратора |
| `APACHE_LOG_DIR` | (нет) | Директория логов Apache |
| `DB_HOST` | `mysql` | Хост MySQL |
| `DB_NAME` | `liscase` | Имя базы данных |
| `DB_USER` | `root` | Пользователь MySQL |
| `DB_PASSWORD` | `root` | Пароль MySQL |
| `REDIS_HOST` | `redis` | Хост Redis |

## 📝 Примеры .env

### Минимальный (локальная разработка)

```env
APP_PORT=3025
```

### Полный (локальная разработка)

```env
# Application
APP_PORT=3025
ADMIN_STEAM_ID=76561198012345678
APACHE_LOG_DIR=/var/log/apache2

# Database (defaults are OK for local)
DB_HOST=mysql
DB_NAME=liscase
DB_USER=root
DB_PASSWORD=root

# Redis
REDIS_HOST=redis
```

### Облачный (production)

```env
# Application
APP_PORT=80
ADMIN_STEAM_ID=76561198012345678

# Database (production credentials)
DB_HOST=mysql
DB_NAME=liscase_prod
DB_USER=liscase_user
DB_PASSWORD=STRONG_PASSWORD_HERE

# Redis
REDIS_HOST=redis

# Apache
APACHE_LOG_DIR=/var/log/apache2
```

## 🚀 Использование

### Локально (Windows)

```bash
# 1. Создать .env
@"
APP_PORT=3025
ADMIN_STEAM_ID=76561198012345678
"@ | Out-File -FilePath .env -Encoding utf8

# 2. Запустить
docker-compose up -d
```

### Локально (Linux/Mac)

```bash
# 1. Создать .env
cat > .env <<EOF
APP_PORT=3025
ADMIN_STEAM_ID=76561198012345678
EOF

# 2. Запустить
docker-compose up -d
```

### На облачном сервере

```bash
# 1. Создать .env для продакшена
cat > .env <<EOF
APP_PORT=80
ADMIN_STEAM_ID=ВАШ_STEAM_ID
DB_PASSWORD=БЕЗОПАСНЫЙ_ПАРОЛЬ
EOF

# 2. Запустить
docker-compose up -d
```

## ⚠️ Важно

- `.env` файл **НЕ КОММИТИТСЯ** в git (в .gitignore)
- Для локальной разработки используйте `APP_PORT=3025`
- Для облака **ОБЯЗАТЕЛЬНО** используйте `APP_PORT=80`
- Всегда используйте **безопасные пароли** для продакшена

## 🔍 Проверка

```bash
# Проверить какие переменные используются
docker-compose config | grep APP_PORT

# Проверить какой порт прослушивается
docker-compose ps
```

## 📚 Связанная документация

- [DOCKER_SETUP_README.md](DOCKER_SETUP_README.md) - Основная документация
- [CLOUD_DEPLOY_FIX.md](CLOUD_DEPLOY_FIX.md) - Исправление 502 в облаке
- [QUICK_FIX_502.md](QUICK_FIX_502.md) - Быстрое решение

