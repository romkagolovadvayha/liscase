#!/bin/bash
# Script to apply Ratchet PHP 8+ compatibility patch manually

RATCHET_FILE="vendor/cboden/ratchet/src/Ratchet/Http/Guzzle/Http/Message/RequestFactory.php"

if [ ! -f "$RATCHET_FILE" ]; then
    echo "Error: Ratchet RequestFactory.php not found at $RATCHET_FILE"
    echo "Please run 'composer install' first"
    exit 1
fi

# Check if patch is already applied
if grep -q "Fix for PHP 8+ compatibility" "$RATCHET_FILE"; then
    echo "Patch already applied to $RATCHET_FILE"
    exit 0
fi

# Backup original file
cp "$RATCHET_FILE" "${RATCHET_FILE}.backup"

# Apply patch
sed -i '/public function create($method, $url, array $headers = array())/,/return parent::create($method, $url, $headers);/c\
    public function create($method, $url, array $headers = array())\
    {\
        // Fix for PHP 8+ compatibility: handle QueryString properly\
        if (is_string($url)) {\
            $url = Url::factory($url);\
        }\
        \
        // Ensure QueryString is an object, not a string\
        if ($url instanceof \\Guzzle\\Http\\Url) {\
            $query = $url->getQuery();\
            if (is_string($query)) {\
                $queryString = \\Guzzle\\Http\\QueryString::fromString($query);\
                $url->setQuery($queryString);\
            }\
        }\
        \
        return parent::create($method, $url, $headers);\
    }' "$RATCHET_FILE"

if [ $? -eq 0 ]; then
    echo "Patch applied successfully to $RATCHET_FILE"
    echo "Backup saved to ${RATCHET_FILE}.backup"
else
    echo "Error applying patch. Restoring backup..."
    mv "${RATCHET_FILE}.backup" "$RATCHET_FILE"
    exit 1
fi

