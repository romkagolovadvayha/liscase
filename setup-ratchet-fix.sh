#!/bin/bash
# Complete setup script: install dependencies with PHP 7.4 and apply Ratchet patch

set -e

PHP74="/opt/php74/bin/php"

if [ ! -f "$PHP74" ]; then
    echo "Error: PHP 7.4 not found at $PHP74"
    exit 1
fi

echo "=== Step 1: Setting up Composer ==="

# Check if composer.phar exists locally
if [ ! -f "composer.phar" ]; then
    echo "composer.phar not found. Checking for global composer..."
    
    if command -v composer &> /dev/null; then
        COMPOSER_CMD="composer"
        echo "Using global composer: $COMPOSER_CMD"
    else
        echo "Installing composer locally..."
        cd /tmp
        $PHP74 -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
        $PHP74 composer-setup.php --install-dir=/usr/local/bin --filename=composer
        $PHP74 -r "unlink('composer-setup.php');"
        COMPOSER_CMD="composer"
        cd - > /dev/null
    fi
else
    COMPOSER_CMD="$PHP74 composer.phar"
    echo "Using local composer.phar"
fi

# Verify composer works
echo "Verifying composer..."
$PHP74 $(which $COMPOSER_CMD 2>/dev/null || echo $COMPOSER_CMD) --version || {
    echo "Composer not working. Installing locally..."
    $PHP74 -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    $PHP74 composer-setup.php
    $PHP74 -r "unlink('composer-setup.php');"
    COMPOSER_CMD="$PHP74 composer.phar"
}

echo ""
echo "=== Step 2: Installing/updating dependencies with PHP 7.4 ==="
echo "Using: $PHP74 with $COMPOSER_CMD"

# Update composer.lock first (if needed)
if [ -f "composer.lock" ]; then
    echo "Updating composer.lock..."
    $COMPOSER_CMD update --lock --ignore-platform-reqs --no-interaction 2>&1 | grep -v "Do not run Composer as root" || true
fi

# Install dependencies
echo "Installing dependencies..."
$COMPOSER_CMD install --ignore-platform-reqs --no-interaction 2>&1 | grep -v "Do not run Composer as root" || true

echo ""
echo "=== Step 3: Applying Ratchet PHP 8+ compatibility patch ==="

# Apply the patch
if [ -f "apply-ratchet-patch-fixed.php" ]; then
    php apply-ratchet-patch-fixed.php
else
    echo "Error: apply-ratchet-patch-fixed.php not found!"
    exit 1
fi

echo ""
echo "=== Setup complete! ==="
echo "You can now run: ./yii server-ws/start 4888"
