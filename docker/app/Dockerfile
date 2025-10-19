FROM php:7.4-fpm

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
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libmcrypt-dev \
    netcat \
    supervisor \
    cron \
    && rm -rf /var/lib/apt/lists/*

# Установка PHP расширений
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    sockets

# Установка Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Рабочая директория
WORKDIR /var/www/html

# Копирование скрипта инициализации
COPY docker/app/init-app.sh /usr/local/bin/init-app.sh
RUN chmod +x /usr/local/bin/init-app.sh

# Entrypoint для автоматической инициализации
ENTRYPOINT ["/usr/local/bin/init-app.sh"]

# По умолчанию запускаем PHP-FPM
CMD ["php-fpm"]

# Production stage
FROM php:7.4-fpm AS production

# Копируем все из базового образа
COPY --from=0 / /

WORKDIR /var/www/html

# Права доступа
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/init-app.sh"]
CMD ["php-fpm"]



