# Multi-stage Dockerfile for LiSCase
# Single-container deployment with MySQL + Nginx + PHP-FPM

FROM php:7.4-fpm AS base

# Установка системных зависимостей  
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    nginx \
    supervisor \
    gettext-base \
    wget \
    netcat \
    sqlite3 \
    && rm -rf /var/lib/apt/lists/*

# Установка PHP расширений
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Установка Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Рабочая директория
WORKDIR /var/www/html

# Копирование composer файлов
COPY composer.json composer.lock ./

# Установка зависимостей (включая dev для debugging)
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --optimize-autoloader --prefer-dist --no-interaction

# Копирование всего кода
COPY . .

# Создание необходимых директорий
RUN mkdir -p /var/www/html/frontend/runtime/cache /var/www/html/frontend/runtime/logs \
    && mkdir -p /var/www/html/backend/runtime/cache /var/www/html/backend/runtime/logs \
    && mkdir -p /var/www/html/api/runtime/cache /var/www/html/api/runtime/logs \
    && mkdir -p /var/www/html/console/runtime/cache /var/www/html/console/runtime/logs \
    && mkdir -p /var/www/html/runtime \
    && mkdir -p /var/www/html/frontend/web/uploads /var/www/html/backend/web/uploads \
    && mkdir -p /var/www/html/frontend/web/minify /var/www/html/backend/web/minify /var/www/html/api/web/minify \
    && mkdir -p /var/www/html/frontend/web/assets /var/www/html/backend/web/assets /var/www/html/api/web/assets

# Установка прав доступа
RUN chmod -R 777 /var/www/html/frontend/runtime /var/www/html/backend/runtime /var/www/html/api/runtime /var/www/html/console/runtime /var/www/html/runtime \
    && chmod -R 777 /var/www/html/frontend/web/assets /var/www/html/frontend/web/uploads /var/www/html/frontend/web/minify \
    && chmod -R 777 /var/www/html/backend/web/assets /var/www/html/backend/web/uploads /var/www/html/backend/web/minify \
    && chmod -R 777 /var/www/html/api/web/assets /var/www/html/api/web/minify \
    && chown -R www-data:www-data /var/www/html

# Production stage
FROM base AS production

# Копирование конфигурации Nginx
# Используем статический конфиг (без envsubst) для Production
COPY docker/nginx/default-static.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf

# Тестируем Nginx конфигурацию
RUN nginx -t

# Копирование конфигурации Supervisor
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Копирование entrypoint скрипта (упрощенная версия для Production)
COPY docker/entrypoint-simple.sh /usr/local/bin/entrypoint.sh

# Права на выполнение
RUN chmod +x /usr/local/bin/entrypoint.sh

# Создание директорий для логов и MySQL
RUN mkdir -p /var/log/supervisor /var/log/nginx /var/lib/mysql /run/mysqld \
    && chown -R mysql:mysql /var/lib/mysql /run/mysqld

# Volume для сохранения данных MySQL
VOLUME ["/var/lib/mysql"]

# Открываем порты
EXPOSE 80 9000

# Entrypoint для генерации конфигов из env и инициализации
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Запуск Supervisor (управляет MySQL + Nginx + PHP-FPM + Queue workers + Cron)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
