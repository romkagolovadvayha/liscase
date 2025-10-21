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

echo "🎉 Container initialization complete!"
echo "Starting services..."

# Запуск переданной команды
exec "$@"

