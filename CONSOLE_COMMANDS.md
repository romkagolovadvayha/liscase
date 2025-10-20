# Console Commands - LiSCase

## 📋 Доступные консольные команды

### 🎨 SCSS Компиляция

#### Компиляция стилей
```bash
# Компиляция для шаблона с ID=1
docker-compose exec php php yii scss/compile 1

# Компиляция без указания шаблона
docker-compose exec php php yii scss/compile

# Альтернативный синтаксис
docker-compose exec php php yii scss/compile --templateId=1
```

#### Список шаблонов
```bash
docker-compose exec php php yii scss/list
```

Пример вывода:
```
📋 Список доступных шаблонов:

   [ID: 1] Default
   [ID: 2] Dark Theme

Использование:
   php yii scss/compile [template_id]
```

#### Массовая компиляция
```bash
# Компилировать SCSS для всех шаблонов
docker-compose exec php php yii scss/compile-all
```

**Документация**: [SCSS_COMPILATION.md](SCSS_COMPILATION.md)

---

### ⚙️ Настройки системы

#### Обновление настроек и генерация цветов
```bash
# Основная команда - выполняет все
docker-compose exec php php yii settings/update
```

Что выполняется:
- Генерация цветов (`genColors()`)
- Обновление кеша настроек (`getSettings(true)`)

Пример вывода:
```
🔧 Обновление настроек системы...

🎨 Генерация цветов...
   ✅ Цвета сгенерированы

⚙️  Обновление настроек (cache refresh)...
   ✅ Настройки обновлены (загружено: 203)

✅ Обновление настроек завершено!
```

#### Только генерация цветов
```bash
docker-compose exec php php yii settings/gen-colors
```

#### Только обновление настроек
```bash
docker-compose exec php php yii settings/refresh
```

Показывает примеры настроек:
```
⚙️  Обновление настроек...
✅ Настройки обновлены
   Загружено параметров: 203

📋 Примеры настроек:
   site_name: 'PROSTOJ.STORE'
   theme_color: '#3498db'
   ...
```

#### Информация о компоненте
```bash
docker-compose exec php php yii settings/info
```

---

### 🗄️ База данных

#### Миграции
```bash
# Применить все новые миграции
docker-compose exec php php yii migrate

# Применить без подтверждения
docker-compose exec php php yii migrate --interactive=0

# Откатить последнюю миграцию
docker-compose exec php php yii migrate/down

# История миграций
docker-compose exec php php yii migrate/history
```

#### Создание миграции
```bash
# Создать новую миграцию
docker-compose exec php php yii migrate/create create_table_name
```

---

### 🔄 Кеш

#### Очистка кеша
```bash
# Очистить весь кеш
docker-compose exec php php yii cache/flush-all

# Очистить конкретный кеш
docker-compose exec php php yii cache/flush cache
docker-compose exec php php yii cache/flush redis
```

---

### 👥 Администраторы

#### Создание администратора из Steam ID
```bash
# Создать администратора (Steam ID из аргумента)
docker-compose exec php php yii admin/create 76561198012345678

# Создать из переменной окружения ADMIN_STEAM_ID
docker-compose exec php bash -c 'php yii admin/create $ADMIN_STEAM_ID'

# С опцией
docker-compose exec php php yii admin/create --steamId=76561198012345678
```

**Что происходит:**
1. Создается пользователь (или находится существующий) по Steam ID
2. Загружается информация из Steam (имя, аватар)
3. Назначается роль `admin`

#### Управление правами
```bash
# Выдать права администратора существующему пользователю
docker-compose exec php php yii admin/grant 1

# Отозвать права администратора
docker-compose exec php php yii admin/revoke 1

# Показать список всех администраторов
docker-compose exec php php yii admin/list
```

Пример вывода `admin/list`:
```
👥 Список администраторов:

================================================================================
   [ID:     2] YourSteamName              | Email: 76561198012345678@steam.com
   [ID:     5] AnotherAdmin               | Email: admin@example.com
================================================================================

Всего администраторов: 2
```

### 👥 Пользователи и RBAC

```bash
# Создать роль
docker-compose exec php php yii rbac/create-role admin

# Назначить роль пользователю
docker-compose exec php php yii rbac/assign admin 1

# Показать разрешения
docker-compose exec php php yii rbac/permissions
```

---

## 🚀 Быстрые команды для деплоя

### Полный цикл деплоя
```bash
# 1. Остановить контейнеры
docker-compose down

# 2. Запустить заново
docker-compose up -d

# Автоматически выполнится:
# - Composer install
# - Yii init
# - Импорт БД
# - Компиляция SCSS
# - Обновление настроек
# - Настройка Apache
```

### Обновление после изменений в коде
```bash
# Перезапустить PHP
docker-compose restart php

# Или пересоздать полностью
docker-compose up -d --force-recreate php
```

### После изменения SCSS
```bash
docker-compose exec php php yii scss/compile 1
```

### После изменения настроек в БД
```bash
docker-compose exec php php yii settings/update
```

---

## 🔍 Отладка

### Просмотр логов
```bash
# Все сервисы в реальном времени
docker-compose logs -f

# Только PHP
docker-compose logs -f php

# Последние 100 строк
docker-compose logs php --tail 100
```

### Выполнение команд внутри контейнера
```bash
# Войти в bash
docker-compose exec php bash

# Выполнить произвольную команду
docker-compose exec php ls -la /app/frontend/web/

# Проверить PHP версию
docker-compose exec php php -v
```

### Проверка конфигурации
```bash
# Проверить конфигурацию Apache
docker-compose exec php cat /etc/apache2/sites-available/000-frontend.conf

# Проверить активные сайты
docker-compose exec php ls -la /etc/apache2/sites-enabled/

# Проверить переменные окружения
docker-compose exec php env | grep -E "DB_|REDIS_"
```

---

## 📦 Полезные команды

### Composer
```bash
# Установить пакет
docker-compose exec php composer require vendor/package

# Обновить зависимости
docker-compose exec php composer update

# Очистить автозагрузку
docker-compose exec php composer dump-autoload
```

### Очистка и сброс
```bash
# Очистить runtime кеш
docker-compose exec php rm -rf /app/frontend/runtime/cache/*
docker-compose exec php rm -rf /app/backend/runtime/cache/*

# Пересоздать с чистой БД
docker-compose down -v
docker-compose up -d
```

### Экспорт/Импорт БД
```bash
# Экспорт базы данных
docker-compose exec mysql mysqldump -uroot -proot liscase > backup_$(date +%Y%m%d).sql

# Импорт базы данных
docker-compose exec -T mysql mysql -uroot -proot liscase < backup.sql
```

---

## 🎯 Быстрый чеклист после деплоя

```bash
# 1. Проверить статус контейнеров
docker-compose ps

# 2. Проверить логи
docker-compose logs php --tail 50

# 3. Скомпилировать SCSS (если не автоматически)
docker-compose exec php php yii scss/compile 1

# 4. Обновить настройки (если не автоматически)
docker-compose exec php php yii settings/update

# 5. Очистить кеш
docker-compose exec php php yii cache/flush-all

# 6. Проверить сайт
curl http://localhost:3025/
curl http://backend.localhost:3025/
curl http://api.localhost:3025/
```

---

## 📚 Связанная документация

- [DOCKER_SETUP_README.md](DOCKER_SETUP_README.md) - Основная документация
- [DOCKER_URLS.md](DOCKER_URLS.md) - Доступные URL
- [SCSS_COMPILATION.md](SCSS_COMPILATION.md) - Компиляция SCSS
- [docker-compose.yml](docker-compose.yml) - Конфигурация

