# Multi-stage build для оптимизации размера образа
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
    && rm -rf /var/lib/apt/lists/*

# Установка PHP расширений
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Установка Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Рабочая директория
WORKDIR /var/www/html

# Копирование composer файлов
COPY composer.json composer.lock ./

# Установка зависимостей
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Копирование остального кода
COPY . .

# Права доступа
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Production stage
FROM base AS production

# Копирование конфигурации Nginx (шаблоны)
COPY docker/nginx/default.template.conf /etc/nginx/conf.d/default.template.conf
COPY docker/nginx/websocket.template.conf /etc/nginx/conf.d/websocket.template.conf
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/generate-config.sh /usr/local/bin/generate-nginx-config.sh

# Копирование конфигурации Supervisor
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Копирование entrypoint скрипта
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

# Права на выполнение
RUN chmod +x /usr/local/bin/generate-nginx-config.sh && \
    chmod +x /usr/local/bin/entrypoint.sh

# Инициализация приложения
RUN php init --env=Production --overwrite=All

# Открываем порты
EXPOSE 80 9000

# Entrypoint для генерации конфигов из env
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Запуск Supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

