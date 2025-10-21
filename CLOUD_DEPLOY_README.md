# ☁️ Облачный деплой с MySQL и phpMyAdmin

## 📋 Описание

Этот проект настроен для деплоя в облаке с полным стеком:
- **Nginx + PHP-FPM** - основное приложение
- **MySQL 8.0** - база данных
- **phpMyAdmin** - веб-интерфейс для управления БД

## 🚀 Быстрый старт

### 1. Использование docker-compose.cloud.yml

Для облачного деплоя используйте файл `docker-compose.cloud.yml`:

```bash
docker-compose -f docker-compose.cloud.yml up -d
```

### 2. Переменные окружения

Создайте файл `.env` с настройками:

```env
# Порт приложения
APP_PORT=80

# Пароль MySQL (ОБЯЗАТЕЛЬНО измените!)
DB_PASSWORD=your_secure_password_here

# Steam ID администратора (опционально)
ADMIN_STEAM_ID=76561198012345678
```

### 3. Доступ к сервисам

После запуска:
- **Приложение**: http://localhost
- **phpMyAdmin**: http://localhost:8080
  - Пользователь: `root`
  - Пароль: из `DB_PASSWORD`

## 📊 Архитектура

```
┌─────────────────┐
│   Nginx:80      │ ← Основное приложение
│   PHP-FPM       │
└────────┬────────┘
         │
         ├─────────┐
         │         │
    ┌────▼───┐  ┌─▼──────────┐
    │ MySQL  │  │ phpMyAdmin │
    │  :3306 │  │    :8080   │
    └────────┘  └────────────┘
```

## 🔧 Что происходит при запуске

1. ✅ **MySQL** стартует и инициализирует БД из `docker/mysql/init.sql`
2. ✅ **App** ждет готовности MySQL
3. ✅ **Yii** инициализируется (`php init`)
4. ✅ **SCSS** компилируется
5. ✅ **Настройки** обновляются
6. ✅ **Администратор** создается (если задан `ADMIN_STEAM_ID`)
7. ✅ **Nginx + PHP-FPM** запускаются через Supervisor

## 📦 Volumes

Данные сохраняются в Docker volumes:
- `mysql-data` - база данных MySQL
- `app-runtime` - runtime файлы приложения
- `app-uploads` - загруженные файлы

## 🛠️ Управление

### Просмотр логов
```bash
docker-compose -f docker-compose.cloud.yml logs -f app
docker-compose -f docker-compose.cloud.yml logs -f mysql
```

### Перезапуск
```bash
docker-compose -f docker-compose.cloud.yml restart
```

### Остановка
```bash
docker-compose -f docker-compose.cloud.yml down
```

### Полная очистка (включая volumes)
```bash
docker-compose -f docker-compose.cloud.yml down -v
```

## 🔐 Безопасность

⚠️ **ВАЖНО для production:**

1. Измените `DB_PASSWORD` на сильный пароль
2. Настройте файрвол для ограничения доступа к phpMyAdmin
3. Используйте HTTPS (SSL сертификаты)
4. Регулярно делайте бэкапы базы данных

## 📝 Примечания

- База данных инициализируется автоматически из `docker/mysql/init.sql`
- При первом запуске инициализация может занять 1-2 минуты
- phpMyAdmin доступен только при запущенном MySQL

