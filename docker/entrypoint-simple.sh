#!/bin/bash

# Простой entrypoint для отладки

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

# Ожидание готовности MySQL (если задан DB_HOST)
if [ -n "$DB_HOST" ]; then
    echo "⏳ Waiting for MySQL at $DB_HOST..."
    for i in {1..30}; do
        if php -r "new PDO('mysql:host=$DB_HOST;port=3306', '$DB_USER', '$DB_PASSWORD');" 2>/dev/null; then
            echo "✅ MySQL is ready!"
            break
        fi
        echo "   Waiting... ($i/30)"
        sleep 2
    done
fi

# Компиляция SCSS
echo "🎨 Compiling SCSS..."
cd /var/www/html
php yii scss/compile 1 || echo "⚠️ SCSS compilation failed (continuing...)"

# Обновление настроек
echo "🔧 Updating settings..."
php yii settings/update || echo "⚠️ Settings update failed (continuing...)"

# Создание администратора (если задан ADMIN_STEAM_ID)
if [ -n "$ADMIN_STEAM_ID" ]; then
    echo "👤 Creating admin user..."
    php yii admin/create "$ADMIN_STEAM_ID" || echo "⚠️ Admin creation failed (continuing...)"
fi

echo "🎉 Container initialization complete!"
echo "Starting services..."

# Запуск переданной команды
exec "$@"

