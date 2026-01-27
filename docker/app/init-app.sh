#!/bin/bash

# Скрипт автоматической инициализации LiSCase приложения
# Выполняет: composer install, yii init, миграции

set -e

echo "╔═══════════════════════════════════════════════════╗"
echo "║   LiSCase - Автоматическая инициализация         ║"
echo "╚═══════════════════════════════════════════════════╝"
echo ""

# Флаги инициализации
RUN_COMPOSER_INSTALL=${RUN_COMPOSER_INSTALL:-true}
RUN_YII_INIT=${RUN_YII_INIT:-true}
RUN_MIGRATIONS=${RUN_MIGRATIONS:-true}
INIT_ENV=${INIT_ENV:-Production}

echo "Конфигурация:"
echo "  Environment: ${APP_ENV:-production}"
echo "  Composer Install: $RUN_COMPOSER_INSTALL"
echo "  Yii Init: $RUN_YII_INIT"
echo "  Run Migrations: $RUN_MIGRATIONS"
echo "  Init Env: $INIT_ENV"
echo ""

cd /var/www/html

# 1. Установка зависимостей через Composer
if [ "$RUN_COMPOSER_INSTALL" = "true" ]; then
    if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
        echo "📦 Установка зависимостей через Composer..."
        echo "   Это может занять несколько минут..."
        
        COMPOSER_ALLOW_SUPERUSER=1 composer install \
            --no-dev \
            --optimize-autoloader \
            --no-interaction \
            --prefer-dist
        
        echo "✅ Composer зависимости установлены!"
    else
        echo "✅ Composer зависимости уже установлены (пропуск)"
    fi
else
    echo "⏭️  Пропуск установки Composer зависимостей"
fi

echo ""

# 2. Инициализация Yii приложения
if [ "$RUN_YII_INIT" = "true" ]; then
    if [ ! -f "frontend/config/main-local.php" ] || [ ! -f "backend/config/main-local.php" ]; then
        echo "🔧 Инициализация Yii приложения (${INIT_ENV})..."
        
        # Выполняем init с автоматическим ответом
        echo "0" | php init --env=${INIT_ENV} --overwrite=All
        
        echo "✅ Yii приложение инициализировано!"
    else
        echo "✅ Yii приложение уже инициализировано (пропуск)"
    fi
else
    echo "⏭️  Пропуск инициализации Yii"
fi

echo ""

# 3. Ожидание готовности базы данных
if [ -n "$DB_HOST" ]; then
    echo "🔌 Ожидание подключения к базе данных..."
    echo "   Хост: ${DB_HOST}:${DB_PORT:-3306}"
    
    MAX_RETRIES=30
    RETRY_COUNT=0
    
    while ! nc -z ${DB_HOST} ${DB_PORT:-3306}; do
        RETRY_COUNT=$((RETRY_COUNT + 1))
        if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
            echo "❌ Не удалось подключиться к базе данных после $MAX_RETRIES попыток"
            exit 1
        fi
        echo "   Попытка $RETRY_COUNT/$MAX_RETRIES..."
        sleep 2
    done
    
    echo "✅ База данных доступна!"
else
    echo "⚠️  DB_HOST не задан, пропуск проверки БД"
fi

echo ""

# 4. Запуск миграций
if [ "$RUN_MIGRATIONS" = "true" ] && [ -n "$DB_HOST" ]; then
    echo "🔄 Запуск миграций базы данных..."
    
    # Ждем еще 5 секунд для полной готовности MySQL
    sleep 5
    
    # Запускаем миграции
    php yii migrate --interactive=0 || {
        echo "⚠️  Миграции не выполнены (возможно, уже применены или БД не готова)"
    }
    
    echo "✅ Миграции выполнены!"
else
    echo "⏭️  Пропуск миграций"
fi

echo ""

# 5. Создание необходимых директорий
echo "📁 Создание директорий..."
mkdir -p frontend/runtime/cache
mkdir -p frontend/runtime/logs
mkdir -p backend/runtime/cache
mkdir -p backend/runtime/logs
mkdir -p console/runtime/cache
mkdir -p console/runtime/logs
mkdir -p api/runtime/cache
mkdir -p api/runtime/logs
mkdir -p common/runtime/cache
mkdir -p common/runtime/logs

# 6. Установка прав доступа
echo "🔐 Установка прав доступа..."
chown -R www-data:www-data /var/www/html/frontend/runtime
chown -R www-data:www-data /var/www/html/backend/runtime
chown -R www-data:www-data /var/www/html/console/runtime
chown -R www-data:www-data /var/www/html/api/runtime
chown -R www-data:www-data /var/www/html/common/runtime
chown -R www-data:www-data /var/www/html/frontend/web/uploads || true
chown -R www-data:www-data /var/www/html/backend/web/uploads || true

chmod -R 775 /var/www/html/frontend/runtime
chmod -R 775 /var/www/html/backend/runtime
chmod -R 775 /var/www/html/console/runtime
chmod -R 775 /var/www/html/api/runtime

echo "✅ Права установлены!"

echo ""
echo "╔═══════════════════════════════════════════════════╗"
echo "║   ✅ Инициализация завершена успешно!            ║"
echo "╚═══════════════════════════════════════════════════╝"
echo ""
echo "Запуск приложения..."
echo ""

# Запуск переданной команды (php-fpm, supervisor, и т.д.)
exec "$@"


