#!/bin/bash

# Скрипт автоматического получения SSL сертификатов от Let's Encrypt
# Запускается один раз при первом развертывании

set -e

# Загрузка переменных из .env
if [ -f ".env" ]; then
    export $(cat .env | grep -v '^#' | xargs)
fi

# Домены
domains=(${FRONTEND_DOMAIN} ${BACKEND_DOMAIN} ${API_DOMAIN} ${FRONTEND_EN_DOMAIN} ${WS_DOMAIN})
email="${ADMIN_EMAIL:-admin@${FRONTEND_DOMAIN}}"
staging=${LETSENCRYPT_STAGING:-0} # Установите 1 для тестирования

data_path="./docker/certbot/data"
rsa_key_size=4096

echo "🔐 Инициализация Let's Encrypt SSL сертификатов"
echo ""
echo "Домены для сертификации:"
for domain in "${domains[@]}"; do
    echo "  - $domain"
done
echo ""
echo "Email: $email"
echo "Staging mode: $staging"
echo ""

# Создание dummy сертификатов для первого запуска
if [ ! -e "$data_path/conf/options-ssl-nginx.conf" ]; then
    echo "📥 Скачивание рекомендуемых TLS параметров..."
    mkdir -p "$data_path/conf"
    curl -s https://raw.githubusercontent.com/certbot/certbot/master/certbot-nginx/certbot_nginx/_internal/tls_configs/options-ssl-nginx.conf > "$data_path/conf/options-ssl-nginx.conf"
    curl -s https://raw.githubusercontent.com/certbot/certbot/master/certbot/certbot/ssl-dhparams.pem > "$data_path/conf/ssl-dhparams.pem"
    echo "✅ TLS параметры скачаны"
fi

echo ""
echo "🔨 Создание dummy сертификатов для запуска Nginx..."

for domain in "${domains[@]}"; do
    path="/etc/letsencrypt/live/$domain"
    
    if [ -d "$data_path/conf/live/$domain" ]; then
        echo "⏭️  Сертификат для $domain уже существует"
        continue
    fi
    
    echo "  Создание dummy сертификата для $domain..."
    mkdir -p "$data_path/conf/live/$domain"
    
    docker-compose -f docker-compose.prod.yml run --rm --entrypoint "\
        openssl req -x509 -nodes -newkey rsa:$rsa_key_size -days 1 \
        -keyout '$path/privkey.pem' \
        -out '$path/fullchain.pem' \
        -subj '/CN=localhost'" certbot
    
    echo "✅ Dummy сертификат создан"
done

echo ""
echo "🚀 Запуск Nginx..."
docker-compose -f docker-compose.prod.yml up -d nginx

echo ""
echo "🗑️  Удаление dummy сертификатов..."
for domain in "${domains[@]}"; do
    echo "  Удаление dummy для $domain..."
    docker-compose -f docker-compose.prod.yml run --rm --entrypoint "\
        rm -Rf /etc/letsencrypt/live/$domain && \
        rm -Rf /etc/letsencrypt/archive/$domain && \
        rm -Rf /etc/letsencrypt/renewal/$domain.conf" certbot
done

echo ""
echo "📜 Запрос настоящих сертификатов от Let's Encrypt..."

# Выбор staging или production сервера
if [ $staging != "0" ]; then
    staging_arg="--staging"
    echo "⚠️  STAGING MODE - тестовые сертификаты!"
else
    staging_arg=""
    echo "✅ PRODUCTION MODE - реальные сертификаты"
fi

for domain in "${domains[@]}"; do
    echo ""
    echo "  Запрос сертификата для $domain..."
    
    # Получение сертификата
    docker-compose -f docker-compose.prod.yml run --rm certbot certonly \
        --webroot \
        --webroot-path=/var/www/certbot \
        --email $email \
        --agree-tos \
        --no-eff-email \
        $staging_arg \
        -d $domain \
        -d www.$domain
    
    echo "✅ Сертификат получен для $domain"
done

echo ""
echo "🔄 Перезагрузка Nginx..."
docker-compose -f docker-compose.prod.yml exec nginx nginx -s reload

echo ""
echo "╔═══════════════════════════════════════════════════╗"
echo "║   ✅ SSL сертификаты успешно получены!           ║"
echo "╚═══════════════════════════════════════════════════╝"
echo ""
echo "Ваши сайты теперь доступны по HTTPS:"
for domain in "${domains[@]}"; do
    echo "  https://$domain"
done
echo ""

