#!/bin/bash

# Скрипт для генерации Nginx конфигурации из шаблона и переменных окружения

set -e

TEMPLATE_FILE="/etc/nginx/conf.d/default.template.conf"
OUTPUT_FILE="/etc/nginx/conf.d/default.conf"

echo "Generating Nginx configuration from template..."
echo "Template: $TEMPLATE_FILE"
echo "Output: $OUTPUT_FILE"

# Установка значений по умолчанию
export FRONTEND_DOMAIN="${FRONTEND_DOMAIN:-prostoj.store}"
export BACKEND_DOMAIN="${BACKEND_DOMAIN:-e.prostoj.store}"
export API_DOMAIN="${API_DOMAIN:-api.prostoj.store}"
export FRONTEND_EN_DOMAIN="${FRONTEND_EN_DOMAIN:-en.prostoj.store}"
export PHP_FPM_HOST="${PHP_FPM_HOST:-127.0.0.1}"
export PHP_FPM_PORT="${PHP_FPM_PORT:-9000}"
export UPLOAD_MAX_SIZE="${UPLOAD_MAX_SIZE:-128M}"
export STATIC_CACHE_TIME="${STATIC_CACHE_TIME:-30d}"
export PHP_TIMEOUT="${PHP_TIMEOUT:-300}"

echo "Configuration:"
echo "  FRONTEND_DOMAIN: $FRONTEND_DOMAIN"
echo "  BACKEND_DOMAIN: $BACKEND_DOMAIN"
echo "  API_DOMAIN: $API_DOMAIN"
echo "  PHP_FPM_HOST: $PHP_FPM_HOST"

# Замена переменных в шаблоне
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
' < $TEMPLATE_FILE > $OUTPUT_FILE

echo "✅ Nginx configuration generated successfully!"

# Проверка конфигурации
nginx -t

echo "✅ Nginx configuration is valid!"

