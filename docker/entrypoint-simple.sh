#!/bin/bash

# Простой entrypoint для отладки

set -e

echo "🚀 Starting LiSCase container..."
echo "Environment: ${APP_ENV:-production}"

# Инициализация MySQL (если не инициализирован)
if [ ! -d "/var/lib/mysql/liscase" ]; then
    echo "🗄️ Initializing MySQL 8.0 database..."
    
    # Создание директорий
    mkdir -p /var/run/mysqld
    chown -R mysql:mysql /var/run/mysqld
    chown -R mysql:mysql /var/lib/mysql
    
    # Инициализация MySQL 8.0
    mysqld --initialize-insecure --user=mysql --datadir=/var/lib/mysql
    
    # Временный запуск MySQL
    mysqld --user=mysql --datadir=/var/lib/mysql --skip-networking --socket=/tmp/mysql_temp.sock &
    MYSQL_PID=$!
    
    # Ожидание запуска MySQL
    echo "⏳ Waiting for MySQL to start..."
    for i in {1..60}; do
        if mysqladmin ping --socket=/tmp/mysql_temp.sock --silent 2>/dev/null; then
            echo "✅ MySQL started!"
            break
        fi
        sleep 1
    done
    
    # Создание базы данных и пользователя
    echo "📝 Creating database and user..."
    mysql --socket=/tmp/mysql_temp.sock -u root <<EOF
CREATE DATABASE IF NOT EXISTS liscase CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root';
CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED WITH mysql_native_password BY 'root';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EOF
    
    # Импорт init.sql если существует
    if [ -f "/var/www/html/docker/mysql/init.sql" ]; then
        echo "📥 Importing database from init.sql (this may take a while)..."
        mysql --socket=/tmp/mysql_temp.sock -u root -proot liscase < /var/www/html/docker/mysql/init.sql || echo "⚠️ Import failed"
        echo "✅ Database imported"
    fi
    
    # Остановка временного MySQL
    mysqladmin --socket=/tmp/mysql_temp.sock -u root -proot shutdown 2>/dev/null || kill $MYSQL_PID
    wait $MYSQL_PID 2>/dev/null || true
    
    echo "✅ MySQL initialized"
else
    echo "✅ MySQL already initialized, skipping"
fi

# Инициализация Yii приложения (если не выполнена)
if [ ! -f "/var/www/html/frontend/config/main-local.php" ]; then
    echo "🔧 Initializing Yii application..."
    cd /var/www/html
    # Для Production выбираем опцию 1
    echo '1' | php init --env=Production --overwrite=All || echo "⚠️ Init failed"
    echo "✅ Yii initialized"
else
    echo "✅ Yii already initialized, skipping"
fi

# Компиляция SCSS
echo "🎨 Compiling SCSS..."
cd /var/www/html
php yii scss/compile 1 || echo "⚠️ SCSS compilation failed (continuing...)"

# Обновление настроек
echo "🔧 Updating settings..."
php yii settings/update || echo "⚠️ Settings update failed (continuing...)"

# Создание администратора (если задан ADMIN_STEAM_ID)
if [ -n "$ADMIN_STEAM_ID" ]; then
    echo "👤 Creating admin user..."
    php yii admin/create "$ADMIN_STEAM_ID" || echo "⚠️ Admin creation failed (continuing...)"
fi

echo "🎉 Container initialization complete!"
echo "Starting services..."

# Запуск переданной команды
exec "$@"

