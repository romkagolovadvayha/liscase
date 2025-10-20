# Docker Development URLs

## ⚡ Быстрый старт

### 1. Настройте hosts файл (один раз)

**Windows** (от имени администратора):
```powershell
.\SETUP_HOSTS.bat
```
или
```powershell
.\setup-hosts.ps1
```

**Linux/Mac**:
```bash
sudo ./setup-hosts.sh
```

### 2. Запустите Docker

```bash
docker-compose up -d
```

### 3. Откройте в браузере

- **Frontend**: http://localhost:3025/
- **Backend**: http://backend.localhost:3025/
- **API**: http://api.localhost:3025/

---

## 📍 Доступные URL адреса

После запуска `docker-compose up -d` и настройки hosts доступны следующие сервисы:

### Frontend (Основной сайт)
- **URL**: http://localhost:3025/
- **Описание**: Главная страница сайта
- **DocumentRoot**: `/app/frontend/web`
- **ServerName**: `localhost`

### Backend (Админ-панель)
- **URL**: http://backend.localhost:3025/
- **Описание**: Административная панель
- **DocumentRoot**: `/app/backend/web`
- **ServerName**: `backend.localhost`

### API
- **URL**: http://api.localhost:3025/
- **Описание**: REST API эндпоинты
- **DocumentRoot**: `/app/api/web`
- **ServerName**: `api.localhost`

### phpMyAdmin
- **URL**: http://localhost:8080/
- **Описание**: Веб-интерфейс для управления MySQL
- **Логин**: `root`
- **Пароль**: `root`
- **База данных**: `liscase`

### MySQL (прямое подключение)
- **Host**: `localhost`
- **Port**: `3307`
- **User**: `root`
- **Password**: `root`
- **Database**: `liscase`

### Redis
- **Host**: `localhost`
- **Port**: `6379`

## 🔧 Конфигурация Apache

Apache настроен с использованием **виртуальных хостов**:

```apache
# Frontend VirtualHost
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /app/frontend/web
</VirtualHost>

# Backend VirtualHost
<VirtualHost *:80>
    ServerName backend.localhost
    DocumentRoot /app/backend/web
</VirtualHost>

# API VirtualHost
<VirtualHost *:80>
    ServerName api.localhost
    DocumentRoot /app/api/web
</VirtualHost>
```

### Важно: Настройка hosts файла

Чтобы поддомены работали, добавьте следующие строки в файл hosts:

**Windows**: `C:\Windows\System32\drivers\etc\hosts`
**Linux/Mac**: `/etc/hosts`

```
127.0.0.1 localhost
127.0.0.1 backend.localhost
127.0.0.1 api.localhost
```

## 📝 Примеры использования

### Frontend маршруты
```
http://localhost:3025/                    → Frontend главная
http://localhost:3025/site/about          → Frontend О нас
http://localhost:3025/servers             → Frontend Серверы
```

### Backend маршруты
```
http://backend.localhost:3025/            → Backend главная (логин)
http://backend.localhost:3025/site/index  → Backend Dashboard
http://backend.localhost:3025/user/index  → Backend Пользователи
http://backend.localhost:3025/template/index → Backend Шаблоны
http://backend.localhost:3025/servers-tags/index → Backend Теги серверов
```

### API маршруты
```
http://api.localhost:3025/                → API главная
http://api.localhost:3025/servers         → API список серверов
http://api.localhost:3025/user/profile    → API профиль пользователя
```

## 🚀 Быстрый старт

```bash
# Запуск всех сервисов
docker-compose up -d

# Проверка статуса
docker-compose ps

# Просмотр логов
docker-compose logs -f php

# Остановка
docker-compose down
```

## 🔍 Troubleshooting

### Backend/API возвращают 404

1. Проверьте, что контейнер запущен:
   ```bash
   docker-compose ps
   ```

2. Проверьте конфигурацию Apache:
   ```bash
   docker-compose exec php cat /etc/apache2/sites-available/000-default.conf
   ```

3. Проверьте логи Apache:
   ```bash
   docker-compose logs php
   ```

4. Перезапустите контейнер:
   ```bash
   docker-compose restart php
   ```

### Права доступа

Если возникают проблемы с правами:

```bash
# Установить права для web директорий
docker-compose exec php chmod -R 777 /app/frontend/web/assets
docker-compose exec php chmod -R 777 /app/backend/web/assets
docker-compose exec php chmod -R 777 /app/api/web/assets

# Установить владельца
docker-compose exec php chown -R www-data:www-data /app/frontend/web
docker-compose exec php chown -R www-data:www-data /app/backend/web
docker-compose exec php chown -R www-data:www-data /app/api/web
```

### Очистка кеша

```bash
# Очистка кеша Yii2
docker-compose exec php php yii cache/flush-all

# Очистка runtime
docker-compose exec php rm -rf /app/frontend/runtime/cache/*
docker-compose exec php rm -rf /app/backend/runtime/cache/*
docker-compose exec php rm -rf /app/api/runtime/cache/*
```

## 📊 Структура портов

| Сервис | Внутренний порт | Внешний порт |
|--------|----------------|--------------|
| PHP (Apache) | 80 | 3025 |
| MySQL | 3306 | 3307 |
| Redis | 6379 | 6379 |
| phpMyAdmin | 80 | 8080 |


## 📚 Связанная документация

- [DOCKER_SETUP_README.md](DOCKER_SETUP_README.md) - Основная документация по Docker
- [SCSS_COMPILATION.md](SCSS_COMPILATION.md) - Компиляция SCSS стилей
- [docker-compose.yml](docker-compose.yml) - Конфигурация Docker Compose

## 🔄 Обновление конфигурации

После изменения `docker-compose.yml`:

```bash
# Пересоздать контейнер
docker-compose up -d --force-recreate php

# ИЛИ полный перезапуск
docker-compose down
docker-compose up -d
```

