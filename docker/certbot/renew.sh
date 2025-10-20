#!/bin/sh

# Скрипт автоматического обновления SSL сертификатов
# Запускается каждые 12 часов через certbot контейнер

echo "🔄 Проверка обновления SSL сертификатов..."

# Попытка обновления сертификатов
certbot renew --webroot --webroot-path=/var/www/certbot

# Если сертификаты обновлены, перезагружаем Nginx
if [ $? -eq 0 ]; then
    echo "✅ Сертификаты обновлены (или актуальны)"
    
    # Перезагрузка Nginx (если он запущен)
    if docker-compose ps nginx | grep -q Up; then
        echo "🔄 Перезагрузка Nginx..."
        docker-compose exec nginx nginx -s reload
        echo "✅ Nginx перезагружен"
    fi
else
    echo "⚠️  Ошибка обновления сертификатов"
fi

echo "Следующая проверка через 12 часов"




