<?php
/**
 * PHP script to apply Ratchet PHP 8+ compatibility patch
 * Run: php apply-ratchet-patch.php
 */

$ratchetFile = __DIR__ . '/vendor/cboden/ratchet/src/Ratchet/Http/Guzzle/Http/Message/RequestFactory.php';

if (!file_exists($ratchetFile)) {
    echo "Error: Ratchet RequestFactory.php not found at $ratchetFile\n";
    echo "Please run 'composer install' first\n";
    exit(1);
}

// Check if patch is already applied
$content = file_get_contents($ratchetFile);
if (strpos($content, 'Fix for PHP 8+ compatibility') !== false) {
    echo "Patch already applied to $ratchetFile\n";
    exit(0);
}

// Backup original file
$backupFile = $ratchetFile . '.backup';
copy($ratchetFile, $backupFile);
echo "Backup created: $backupFile\n";

// Read the file
$lines = file($ratchetFile);

// Find the create method and replace it
$newLines = [];
$inCreateMethod = false;
$methodIndent = '';
$braceCount = 0;

foreach ($lines as $i => $line) {
    if (preg_match('/public function create\(/', $line)) {
        $inCreateMethod = true;
        $methodIndent = str_repeat(' ', strspn($line, ' '));
        $braceCount = 0;
        
        // Add the fixed method
        $newLines[] = $line;
        $newLines[] = $methodIndent . "    {\n";
        $newLines[] = $methodIndent . "        // Fix for PHP 8+ compatibility: handle QueryString properly\n";
        $newLines[] = $methodIndent . "        if (is_string(\$url)) {\n";
        $newLines[] = $methodIndent . "            \$url = Url::factory(\$url);\n";
        $newLines[] = $methodIndent . "        }\n";
        $newLines[] = $methodIndent . "        \n";
        $newLines[] = $methodIndent . "        // Ensure QueryString is an object, not a string\n";
        $newLines[] = $methodIndent . "        if (\$url instanceof \\Guzzle\\Http\\Url) {\n";
        $newLines[] = $methodIndent . "            \$query = \$url->getQuery();\n";
        $newLines[] = $methodIndent . "            if (is_string(\$query)) {\n";
        $newLines[] = $methodIndent . "                \$queryString = \\Guzzle\\Http\\QueryString::fromString(\$query);\n";
        $newLines[] = $methodIndent . "                \$url->setQuery(\$queryString);\n";
        $newLines[] = $methodIndent . "            }\n";
        $newLines[] = $methodIndent . "        }\n";
        $newLines[] = $methodIndent . "        \n";
        $newLines[] = $methodIndent . "        return parent::create(\$method, \$url, \$headers);\n";
        continue;
    }
    
    if ($inCreateMethod) {
        // Count braces to know when method ends
        $braceCount += substr_count($line, '{') - substr_count($line, '}');
        
        if ($braceCount <= 0 && strpos($line, '}') !== false) {
            $inCreateMethod = false;
            $newLines[] = $methodIndent . "    }\n";
            continue;
        }
        
        // Skip original method body
        continue;
    }
    
    $newLines[] = $line;
}

// Write the patched file
file_put_contents($ratchetFile, implode('', $newLines));

echo "Patch applied successfully to $ratchetFile\n";
echo "You can now run: ./yii server-ws/start 4888\n";

