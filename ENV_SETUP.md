# Environment Variables Setup

## 📋 Настройка переменных окружения

Для работы некоторых функций Docker окружения требуются переменные окружения.

## 🔐 Создание администратора

### Способ 1: .env файл (рекомендуется)

Создайте файл `.env` в корне проекта:

```bash
# .env
ADMIN_STEAM_ID=76561198012345678
APACHE_LOG_DIR=/var/log/apache2
```

**Где взять Steam ID:**
1. Откройте https://steamid.io/
2. Введите ваш Steam профиль
3. Скопируйте **steamID64** (17 цифр)

### Способ 2: Переменная окружения (Windows)

**PowerShell**:
```powershell
$env:ADMIN_STEAM_ID="76561198012345678"
docker-compose up -d
```

**CMD**:
```cmd
set ADMIN_STEAM_ID=76561198012345678
docker-compose up -d
```

### Способ 3: Переменная окружения (Linux/Mac)

```bash
export ADMIN_STEAM_ID=76561198012345678
docker-compose up -d
```

### Способ 4: Inline при запуске

**Linux/Mac**:
```bash
ADMIN_STEAM_ID=76561198012345678 docker-compose up -d
```

**Windows PowerShell**:
```powershell
$env:ADMIN_STEAM_ID="76561198012345678"; docker-compose up -d
```

## 🚀 Автоматическое создание при деплое

Если `ADMIN_STEAM_ID` задан, то при деплое автоматически:

1. ✅ Создается пользователь с указанным Steam ID (если не существует)
2. ✅ Пользователю назначается роль `admin`
3. ✅ Загружается информация из Steam (имя, аватар)

Лог деплоя:
```
👤 Creating admin user from ADMIN_STEAM_ID...
👤 Создание администратора из Steam ID: 76561198012345678

🔍 Поиск/создание пользователя...
   ✅ Пользователь найден/создан: [2] YourSteamName

🔐 Назначение роли администратора...
   ✅ Роль 'admin' назначена

============================================================
✅ Администратор успешно создан!
============================================================
```

## 🛠️ Ручное создание администратора

### Из Steam ID

```bash
# Создать администратора по Steam ID
docker-compose exec php php yii admin/create 76561198012345678

# Используя переменную окружения
docker-compose exec php bash -c 'php yii admin/create $ADMIN_STEAM_ID'
```

### Для существующего пользователя

```bash
# Выдать права админа пользователю с ID=1
docker-compose exec php php yii admin/grant 1

# Отозвать права
docker-compose exec php php yii admin/revoke 1

# Показать список администраторов
docker-compose exec php php yii admin/list
```

## 📋 Пример .env файла

Создайте файл `.env` в корне проекта:

```env
# ===================================
# Docker Compose Environment
# ===================================

# Администратор (Steam ID)
# Получить: https://steamid.io/
ADMIN_STEAM_ID=76561198012345678

# Apache логи (опционально)
APACHE_LOG_DIR=/var/log/apache2

# ===================================
# Дополнительные настройки (опционально)
# ===================================

# MySQL
# DB_HOST=mysql
# DB_NAME=liscase
# DB_USER=root
# DB_PASSWORD=root

# Redis
# REDIS_HOST=redis
```

## 🔍 Проверка

### Проверить переменные окружения в контейнере

```bash
docker-compose exec php env | grep ADMIN
```

### Проверить список администраторов

```bash
docker-compose exec php php yii admin/list
```

Вывод:
```
👥 Список администраторов:

================================================================================
   [ID:     2] YourSteamName              | Email: 76561198012345678@steam.com
================================================================================

Всего администраторов: 1
```

## 📚 Связанные команды

Полный список консольных команд: [CONSOLE_COMMANDS.md](CONSOLE_COMMANDS.md)

## ⚠️ Важно

- **Steam ID должен быть из 17 цифр** (steamID64 формат)
- При первом запуске пользователь создается автоматически
- При повторном запуске просто проверяется наличие роли admin
- Если роль уже есть - пропускается

## 🎯 Быстрый старт

```bash
# 1. Создайте .env файл
echo "ADMIN_STEAM_ID=76561198012345678" > .env

# 2. Запустите Docker
docker-compose up -d

# 3. Проверьте
docker-compose exec php php yii admin/list
```

Готово! 🚀

