#!/bin/bash
# Quick script to install composer

PHP74="/opt/php74/bin/php"

if [ ! -f "$PHP74" ]; then
    echo "Error: PHP 7.4 not found at $PHP74"
    exit 1
fi

echo "Installing Composer..."
cd /tmp
$PHP74 -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
$PHP74 composer-setup.php
$PHP74 -r "unlink('composer-setup.php');"

# Move to project directory if we're in one
if [ -f "/tmp/composer.phar" ]; then
    if [ -d "/var/www/www-root/data/www/todayrust.ru" ]; then
        mv /tmp/composer.phar /var/www/www-root/data/www/todayrust.ru/composer.phar
        echo "Composer installed to: /var/www/www-root/data/www/todayrust.ru/composer.phar"
    else
        mv /tmp/composer.phar ./composer.phar
        echo "Composer installed to: ./composer.phar"
    fi
fi

echo "Done! You can now use: /opt/php74/bin/php composer.phar"

