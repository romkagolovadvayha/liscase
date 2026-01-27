#!/bin/bash
# Script to run composer install with PHP 7.4

# Try to find PHP 7.4
PHP74_PATHS=(
    "/opt/php74/bin/php"
    "/opt/php7.4/bin/php"
    "/opt/remi/php74/root/usr/bin/php"
    "/usr/bin/php74"
    "/usr/local/php74/bin/php"
)

PHP74=""

for path in "${PHP74_PATHS[@]}"; do
    if [ -f "$path" ]; then
        version=$($path -v 2>/dev/null | head -1 | grep -oP 'PHP \K[0-9]+\.[0-9]+')
        if [ "$version" = "7.4" ]; then
            PHP74="$path"
            echo "Found PHP 7.4: $PHP74"
            break
        fi
    fi
done

# If not found, search in /opt
if [ -z "$PHP74" ]; then
    echo "Searching in /opt..."
    found=$(find /opt -type f -name "php" -executable 2>/dev/null | while read php_path; do
        version=$($php_path -v 2>/dev/null | head -1 | grep -oP 'PHP \K[0-9]+\.[0-9]+')
        if [ "$version" = "7.4" ]; then
            echo "$php_path"
            break
        fi
    done | head -1)
    
    if [ -n "$found" ]; then
        PHP74="$found"
        echo "Found PHP 7.4: $PHP74"
    fi
fi

if [ -z "$PHP74" ]; then
    echo "Error: PHP 7.4 not found!"
    echo "Please install PHP 7.4 or specify the path manually"
    exit 1
fi

# Check if composer exists
if [ ! -f "composer.phar" ] && ! command -v composer &> /dev/null; then
    echo "Composer not found. Installing composer..."
    $PHP74 -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    $PHP74 composer-setup.php
    $PHP74 -r "unlink('composer-setup.php');"
    COMPOSER_CMD="$PHP74 composer.phar"
else
    if [ -f "composer.phar" ]; then
        COMPOSER_CMD="$PHP74 composer.phar"
    else
        COMPOSER_CMD="composer"
    fi
fi

echo "Using PHP 7.4 for composer operations..."
echo "Running: $COMPOSER_CMD install"

# Run composer install with PHP 7.4
$COMPOSER_CMD install --ignore-platform-reqs

echo ""
echo "Installation complete!"
echo ""
echo "Note: Even though dependencies were installed with PHP 7.4,"
echo "you still need to apply the Ratchet patch because the server"
echo "runs on PHP 8.3.6. Run: php apply-ratchet-patch-fixed.php"

