#!/bin/bash
# Script to find PHP 7.4 installation

echo "Searching for PHP 7.4 in /opt..."
find /opt -name "php74" -o -name "php7.4" -o -name "php-7.4" 2>/dev/null | head -5

echo ""
echo "Searching for PHP binaries in /opt..."
find /opt -type f -name "php" -executable 2>/dev/null | while read php_path; do
    version=$($php_path -v 2>/dev/null | head -1 | grep -oP 'PHP \K[0-9]+\.[0-9]+')
    if [ "$version" = "7.4" ]; then
        echo "Found PHP 7.4: $php_path"
    fi
done

echo ""
echo "Checking common locations:"
for path in /opt/php74/bin/php /opt/php7.4/bin/php /opt/remi/php74/root/usr/bin/php /usr/bin/php74; do
    if [ -f "$path" ]; then
        version=$($path -v 2>/dev/null | head -1 | grep -oP 'PHP \K[0-9]+\.[0-9]+')
        echo "$path -> PHP $version"
    fi
done

