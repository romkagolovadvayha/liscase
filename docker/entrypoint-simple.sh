#!/bin/bash

# Упрощенный entrypoint для одноконтейнерного деплоя с SQLite

set -e

echo "🚀 Starting LiSCase container..."
echo "Environment: ${APP_ENV:-production}"

# Инициализация Yii приложения (если не выполнена)
if [ ! -f "/var/www/html/frontend/config/main-local.php" ]; then
    echo "🔧 Initializing Yii application..."
    cd /var/www/html
    # Для Production выбираем опцию 1
    echo '1' | php init --env=Production --overwrite=All || echo "⚠️ Init failed"
    echo "✅ Yii initialized"
else
    echo "✅ Yii already initialized, skipping"
fi

# Запуск миграций для создания схемы SQLite
echo "🗄️ Running database migrations..."
cd /var/www/html
php yii migrate --interactive=0 || echo "⚠️ Migrations failed (continuing...)"

# Компиляция SCSS
echo "🎨 Compiling SCSS..."
php yii scss/compile 1 || echo "⚠️ SCSS compilation failed (continuing...)"

# Обновление настроек
echo "🔧 Updating settings..."
php yii settings/update || echo "⚠️ Settings update failed (continuing...)"

# Создание администратора (если задан ADMIN_STEAM_ID)
if [ -n "$ADMIN_STEAM_ID" ]; then
    echo "👤 Creating admin user..."
    php yii admin/create "$ADMIN_STEAM_ID" || echo "⚠️ Admin creation failed (continuing...)"
else
    echo "ℹ️  ADMIN_STEAM_ID not set, skipping admin creation"
fi

echo "🎉 Container initialization complete!"
echo "Starting services..."

# Запуск переданной команды
exec "$@"
