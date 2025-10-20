#!/bin/bash

# Entrypoint скрипт для генерации конфигураций из переменных окружения

set -e

echo "🚀 Starting LiSCase container..."
echo "Environment: ${APP_ENV:-production}"

# Установка значений по умолчанию
export FRONTEND_DOMAIN="${FRONTEND_DOMAIN:-prostoj.store}"
export BACKEND_DOMAIN="${BACKEND_DOMAIN:-e.prostoj.store}"
export API_DOMAIN="${API_DOMAIN:-api.prostoj.store}"
export FRONTEND_EN_DOMAIN="${FRONTEND_EN_DOMAIN:-en.prostoj.store}"
export WS_DOMAIN="${WS_DOMAIN:-ws.prostoj.store}"

export PHP_FPM_HOST="${PHP_FPM_HOST:-127.0.0.1}"
export PHP_FPM_PORT="${PHP_FPM_PORT:-9000}"
export UPLOAD_MAX_SIZE="${UPLOAD_MAX_SIZE:-128M}"
export STATIC_CACHE_TIME="${STATIC_CACHE_TIME:-30d}"
export PHP_TIMEOUT="${PHP_TIMEOUT:-300}"

export WS_BACKEND_HOST="${WS_BACKEND_HOST:-127.0.0.1}"
export WS_BACKEND_PORT="${WS_BACKEND_PORT:-4888}"
export WS_READ_TIMEOUT="${WS_READ_TIMEOUT:-2147483647s}"
export WS_SEND_TIMEOUT="${WS_SEND_TIMEOUT:-2147483647s}"
export WS_CONNECT_TIMEOUT="${WS_CONNECT_TIMEOUT:-60s}"

export RADIO1_HOST="${RADIO1_HOST:-127.0.0.1}"
export RADIO1_PORT="${RADIO1_PORT:-3007}"
export RADIO2_HOST="${RADIO2_HOST:-127.0.0.1}"
export RADIO2_PORT="${RADIO2_PORT:-3008}"

# Database configuration
export DB_HOST="${DB_HOST:-mysql-service}"
export DB_PORT="${DB_PORT:-3306}"
export DB_NAME="${DB_NAME:-prostoj4}"
export DB_USERNAME="${DB_USERNAME:-root}"
export DB_PASSWORD="${DB_PASSWORD:-password}"

echo "📝 Configuration loaded:"
echo "  Frontend: ${FRONTEND_DOMAIN}"
echo "  Backend:  ${BACKEND_DOMAIN}"
echo "  API:      ${API_DOMAIN}"
echo "  WebSocket: ${WS_DOMAIN}"
echo "  Database: ${DB_HOST}:${DB_PORT}/${DB_NAME}"

# Генерация Nginx конфигурации из шаблона
echo "⚙️ Generating Nginx configuration..."

if [ -f "/etc/nginx/conf.d/default.template.conf" ]; then
    envsubst '
        $FRONTEND_DOMAIN
        $BACKEND_DOMAIN
        $API_DOMAIN
        $FRONTEND_EN_DOMAIN
        $PHP_FPM_HOST
        $PHP_FPM_PORT
        $UPLOAD_MAX_SIZE
        $STATIC_CACHE_TIME
        $PHP_TIMEOUT
    ' < /etc/nginx/conf.d/default.template.conf > /etc/nginx/sites-available/default
    
    echo "✅ Main Nginx config generated"
fi

if [ -f "/etc/nginx/conf.d/websocket.template.conf" ]; then
    envsubst '
        $WS_DOMAIN
        $WS_BACKEND_HOST
        $WS_BACKEND_PORT
        $WS_READ_TIMEOUT
        $WS_SEND_TIMEOUT
        $WS_CONNECT_TIMEOUT
        $RADIO1_HOST
        $RADIO1_PORT
        $RADIO2_HOST
        $RADIO2_PORT
    ' < /etc/nginx/conf.d/websocket.template.conf > /etc/nginx/sites-available/websocket
    
    echo "✅ WebSocket Nginx config generated"
fi

# Создание symlinks для sites-enabled
ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default || true
ln -sf /etc/nginx/sites-available/websocket /etc/nginx/sites-enabled/websocket || true

# Проверка Nginx конфигурации
echo "🔍 Testing Nginx configuration..."
nginx -t

# Создание директорий для логов
mkdir -p /var/log/supervisor
mkdir -p /var/log/nginx

# Права доступа
chown -R www-data:www-data /var/www/html/frontend/runtime || true
chown -R www-data:www-data /var/www/html/backend/runtime || true
chown -R www-data:www-data /var/www/html/api/runtime || true
chown -R www-data:www-data /var/www/html/console/runtime || true
chown -R www-data:www-data /var/www/html/common/runtime || true

# Проверка подключения к базе данных
echo "🔌 Waiting for database..."
until nc -z ${DB_HOST} ${DB_PORT} 2>/dev/null; do
    echo "Waiting for database at ${DB_HOST}:${DB_PORT}..."
    sleep 2
done
echo "✅ Database is available!"

# Запуск миграций (если требуется)
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "🔄 Running database migrations..."
    php /var/www/html/yii migrate --interactive=0 || echo "⚠️ Migration failed or already up to date"
fi

echo "🎉 Container initialization complete!"
echo "Starting services..."

# Запуск переданной команды
exec "$@"




