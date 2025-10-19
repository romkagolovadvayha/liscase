#!/bin/sh

# Скрипт генерации Nginx конфигурации из шаблонов с envsubst
# Запускается автоматически при старте Nginx контейнера

set -e

echo "🔧 Генерация Nginx конфигурации из шаблонов..."

# Установка значений по умолчанию
export FRONTEND_DOMAIN="${FRONTEND_DOMAIN:-prostoj.store}"
export BACKEND_DOMAIN="${BACKEND_DOMAIN:-e.prostoj.store}"
export API_DOMAIN="${API_DOMAIN:-api.prostoj.store}"
export FRONTEND_EN_DOMAIN="${FRONTEND_EN_DOMAIN:-en.prostoj.store}"
export WS_DOMAIN="${WS_DOMAIN:-ws.prostoj.store}"
export PHP_FPM_HOST="${PHP_FPM_HOST:-app}"
export PHP_FPM_PORT="${PHP_FPM_PORT:-9000}"

echo "Домены:"
echo "  Frontend: ${FRONTEND_DOMAIN}"
echo "  Backend:  ${BACKEND_DOMAIN}"
echo "  API:      ${API_DOMAIN}"
echo "  English:  ${FRONTEND_EN_DOMAIN}"
echo "  WebSocket: ${WS_DOMAIN}"
echo "  PHP-FPM: ${PHP_FPM_HOST}:${PHP_FPM_PORT}"

# Nginx автоматически обработает шаблоны в /etc/nginx/templates
# и создаст конфиги в /etc/nginx/conf.d/

echo "✅ Nginx конфигурация будет сгенерирована автоматически"



