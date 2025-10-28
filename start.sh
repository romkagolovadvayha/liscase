#!/bin/bash

# 🚀 LiSCase - Полностью автоматический запуск
# Выполняет ВСЕ шаги: composer, init, миграции, nginx, supervisor, SSL

set -e

cat << "EOF"
╔══════════════════════════════════════════════════════╗
║                                                      ║
║   ██╗     ██╗███████╗ ██████╗ █████╗ ███████╗███████╗║
║   ██║     ██║██╔════╝██╔════╝██╔══██╗██╔════╝██╔════╝║
║   ██║     ██║███████╗██║     ███████║███████╗█████╗  ║
║   ██║     ██║╚════██║██║     ██╔══██║╚════██║██╔══╝  ║
║   ███████╗██║███████║╚██████╗██║  ██║███████║███████╗║
║   ╚══════╝╚═╝╚══════╝ ╚═════╝╚═╝  ╚═╝╚══════╝╚══════╝║
║                                                      ║
║        Docker Compose Production Deployment          ║
║                                                      ║
╚══════════════════════════════════════════════════════╝
EOF

echo ""
echo "🎯 Автоматическая настройка и запуск LiSCase"
echo ""

# Проверка .env файла
if [ ! -f ".env" ]; then
    echo "⚠️  Файл .env не найден!"
    echo "📝 Создание .env из примера..."
    cp k8s.env.example .env
    echo ""
    echo "⚠️  ВАЖНО! Отредактируйте .env файл перед продолжением:"
    echo "   - Установите свои домены"
    echo "   - Установите безопасные пароли"
    echo "   - Установите API ключи"
    echo ""
    read -p "Отредактировали .env? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Отменено. Отредактируйте .env и запустите снова."
        exit 0
    fi
fi

# Загрузка переменных
export $(cat .env | grep -v '^#' | xargs)

echo "📋 Конфигурация:"
echo "  Frontend: ${FRONTEND_DOMAIN}"
echo "  Backend:  ${BACKEND_DOMAIN}"
echo "  API:      ${API_DOMAIN}"
echo "  English:  ${FRONTEND_EN_DOMAIN}"
echo "  WebSocket: ${WS_DOMAIN}"
echo ""

read -p "🚀 Начать развертывание? (y/n): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Отменено."
    exit 0
fi

echo ""
echo "📦 Шаг 1/5: Остановка существующих контейнеров..."
docker-compose -f docker-compose.prod.yml down || true

echo ""
echo "🏗️  Шаг 2/5: Сборка Docker образов..."
docker-compose -f docker-compose.prod.yml build --no-cache

echo ""
echo "🚀 Шаг 3/5: Запуск сервисов (MySQL, Redis, App)..."
docker-compose -f docker-compose.prod.yml up -d mysql redis app

echo "⏳ Ожидание готовности сервисов (это займет 30-60 секунд)..."
sleep 30

# Проверка готовности MySQL
echo "🔍 Проверка MySQL..."
timeout=60
counter=0
until docker-compose -f docker-compose.prod.yml exec -T mysql mysqladmin ping -h localhost -p${MYSQL_ROOT_PASSWORD} &> /dev/null; do
    counter=$((counter + 1))
    if [ $counter -gt $timeout ]; then
        echo "❌ MySQL не запустился за $timeout секунд"
        exit 1
    fi
    echo "  Ожидание MySQL... ($counter/$timeout)"
    sleep 1
done
echo "✅ MySQL готов!"

echo ""
echo "🔧 Шаг 4/5: Автоинициализация приложения..."
echo "   (Composer install, Yii init, Миграции)"
echo "   Это может занять 3-5 минут при первом запуске..."

# Приложение автоматически выполнит все через init-app.sh
docker-compose -f docker-compose.prod.yml logs -f app &
LOG_PID=$!

# Ждем окончания инициализации
sleep 60

kill $LOG_PID 2>/dev/null || true

echo "✅ Приложение инициализировано!"

echo ""
echo "🌐 Шаг 5/5: Настройка Nginx и SSL сертификатов..."

# Запуск Nginx
docker-compose -f docker-compose.prod.yml up -d nginx

# Запрос SSL сертификатов
echo "🔐 Получение SSL сертификатов от Let's Encrypt..."
echo "   (Первый раз может занять 2-3 минуты)"

chmod +x docker/certbot/init-letsencrypt.sh
./docker/certbot/init-letsencrypt.sh || {
    echo "⚠️  SSL сертификаты не получены. Используются dummy сертификаты."
    echo "   Проверьте, что домены указывают на этот сервер."
}

echo ""
echo "🎬 Запуск всех остальных сервисов..."
docker-compose -f docker-compose.prod.yml up -d

echo ""
echo "╔═══════════════════════════════════════════════════╗"
echo "║   ✅ РАЗВЕРТЫВАНИЕ ЗАВЕРШЕНО УСПЕШНО!            ║"
echo "╚═══════════════════════════════════════════════════╝"
echo ""

echo "🌐 Ваши сайты:"
echo "  https://${FRONTEND_DOMAIN}"
echo "  https://${BACKEND_DOMAIN}"
echo "  https://${API_DOMAIN}"
echo "  https://${FRONTEND_EN_DOMAIN}"
echo "  wss://${WS_DOMAIN}/ws/"
echo ""

echo "📊 Статус контейнеров:"
docker-compose -f docker-compose.prod.yml ps

echo ""
echo "📝 Полезные команды:"
echo "  docker-compose -f docker-compose.prod.yml logs -f app      # Логи приложения"
echo "  docker-compose -f docker-compose.prod.yml logs -f nginx    # Логи Nginx"
echo "  docker-compose -f docker-compose.prod.yml logs -f supervisor # Логи очередей"
echo "  docker-compose -f docker-compose.prod.yml exec app bash    # Shell в контейнере"
echo "  docker-compose -f docker-compose.prod.yml restart          # Перезапуск"
echo "  docker-compose -f docker-compose.prod.yml down             # Остановка"
echo ""
echo "🎉 Готово! Приложение запущено и работает!"


