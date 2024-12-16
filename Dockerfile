# Используем официальный образ php:7.4-fpm как базовый
FROM php:7.4-fpm

# Установка зависимостей и Supervisor
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libmariadb-dev-compat \
    libmariadb-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    supervisor \
    && rm -rf /var/lib/apt/lists/*  # Очистим кеш после установки

# Устанавливаем расширения PHP
RUN docker-php-ext-install pdo pdo_mysql zip gd

# Установка расширения Redis
RUN pecl install redis \
    && docker-php-ext-enable redis

# Копируем проект в контейнер
COPY . /application

# Устанавливаем рабочую директорию
WORKDIR /application

# Устанавливаем зависимости Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
#COPY composer.json composer.lock /application/
#RUN composer install --no-dev --optimize-autoloader  --ignore-platform-req=php

# Копируем конфигурацию Supervisor, если она существует
#COPY ./docker/supervisor/supervisord.conf /etc/supervisord.conf

# Запускаем Supervisor как основной процесс
#CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]