#!/bin/bash

# Генерация конфигурации Nginx из шаблонов с подстановкой переменных окружения

echo "Generating Nginx configuration from templates..."

# Заменяем переменные в шаблонах
envsubst '${FRONTEND_DOMAIN} ${BACKEND_DOMAIN} ${API_DOMAIN} ${WS_DOMAIN} ${PHP_FPM_HOST} ${PHP_FPM_PORT} ${UPLOAD_MAX_SIZE} ${WS_BACKEND_HOST} ${WS_BACKEND_PORT} ${WS_READ_TIMEOUT} ${WS_SEND_TIMEOUT} ${WS_CONNECT_TIMEOUT}' \
    < /etc/nginx/conf.d/default.template.conf \
    > /etc/nginx/conf.d/default.conf

envsubst '${WS_DOMAIN} ${WS_BACKEND_HOST} ${WS_BACKEND_PORT} ${WS_READ_TIMEOUT} ${WS_SEND_TIMEOUT} ${WS_CONNECT_TIMEOUT}' \
    < /etc/nginx/conf.d/websocket.template.conf \
    > /etc/nginx/conf.d/websocket.conf

echo "Nginx configuration generated successfully!"

# Проверка конфигурации
nginx -t
