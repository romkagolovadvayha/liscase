<?php
/**
 * PHP script to apply Ratchet PHP 8+ compatibility patch (fixed version)
 * Run: php apply-ratchet-patch-fixed.php
 */

$ratchetFile = __DIR__ . '/vendor/cboden/ratchet/src/Ratchet/Http/Guzzle/Http/Message/RequestFactory.php';

if (!file_exists($ratchetFile)) {
    echo "Error: Ratchet RequestFactory.php not found at $ratchetFile\n";
    echo "Please run 'composer install' first\n";
    exit(1);
}

// Restore from backup if exists
$backupFile = $ratchetFile . '.backup';
if (file_exists($backupFile)) {
    copy($backupFile, $ratchetFile);
    echo "Restored from backup\n";
}

// Check if patch is already applied
$content = file_get_contents($ratchetFile);
if (strpos($content, 'Fix for PHP 8+ compatibility') !== false) {
    echo "Patch already applied to $ratchetFile\n";
    exit(0);
}

// Create backup
copy($ratchetFile, $backupFile);
echo "Backup created: $backupFile\n";

// Simple string replacement - find the exact old code and replace it
$oldCode = <<<'OLD'
    public function create($method, $url, array $headers = array())
    {
        $url = Url::factory($url);
        return parent::create($method, $url, $headers);
    }
OLD;

$newCode = <<<'NEW'
    public function create($method, $url, array $headers = array())
    {
        // Fix for PHP 8+ compatibility: handle QueryString properly
        if (is_string($url)) {
            $url = Url::factory($url);
        }
        
        // Ensure QueryString is an object, not a string
        if ($url instanceof \Guzzle\Http\Url) {
            $query = $url->getQuery();
            if (is_string($query)) {
                $queryString = \Guzzle\Http\QueryString::fromString($query);
                $url->setQuery($queryString);
            }
        }
        
        return parent::create($method, $url, $headers);
    }
NEW;

// Try exact replacement
if (strpos($content, $oldCode) !== false) {
    $newContent = str_replace($oldCode, $newCode, $content);
} else {
    // Try with different whitespace variations
    $oldCodeVariations = [
        "    public function create(\$method, \$url, array \$headers = array())\n    {\n        \$url = Url::factory(\$url);\n        return parent::create(\$method, \$url, \$headers);\n    }",
        "    public function create(\$method, \$url, array \$headers = array())\n    {\n        \$url = Url::factory(\$url);\n        return parent::create(\$method, \$url, \$headers);\n    }\n",
    ];
    
    $newContent = $content;
    foreach ($oldCodeVariations as $variation) {
        if (strpos($newContent, $variation) !== false) {
            $newContent = str_replace($variation, $newCode, $newContent);
            break;
        }
    }
    
    // If still not found, use regex
    if ($newContent === $content) {
        $pattern = '/(\s+public function create\([^)]+\)\s*\{[^\n]*\n\s+\$url\s*=\s*Url::factory\(\$url\);[^\n]*\n\s+return parent::create\(\$method,\s*\$url,\s*\$headers\);[^\n]*\n\s+\})/';
        $newContent = preg_replace($pattern, $newCode, $content);
    }
}

// Write the patched file
file_put_contents($ratchetFile, $newContent);

// Validate PHP syntax
$output = [];
$returnCode = 0;
exec("php -l $ratchetFile 2>&1", $output, $returnCode);

if ($returnCode !== 0) {
    echo "Error: PHP syntax check failed!\n";
    echo implode("\n", $output) . "\n";
    echo "Restoring backup...\n";
    copy($backupFile, $ratchetFile);
    exit(1);
}

echo "Patch applied successfully to $ratchetFile\n";
echo "PHP syntax validated successfully\n";
echo "You can now run: ./yii server-ws/start 4888\n";

