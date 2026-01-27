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

// Restore from backup if exists (in case of previous failed patch)
$backupFile = $ratchetFile . '.backup';
if (file_exists($backupFile)) {
    copy($backupFile, $ratchetFile);
    echo "Restored from backup\n";
}

// Create new backup
copy($ratchetFile, $backupFile);
echo "Backup created: $backupFile\n";

// Read the entire file
$content = file_get_contents($ratchetFile);

// Find and replace the create method
// Pattern: public function create(...) { ... return parent::create(...); }
$pattern = '/(public function create\([^)]+\)\s*\{[^}]*\$url\s*=\s*Url::factory\(\$url\);[^}]*return parent::create\(\$method,\s*\$url,\s*\$headers\);[^}]*\})/s';

$replacement = <<<'PHP'
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
PHP;

// Try pattern replacement first
$newContent = preg_replace($pattern, $replacement, $content);

// If pattern didn't match, try line-by-line replacement
if ($newContent === $content) {
    $lines = file($ratchetFile, FILE_IGNORE_NEW_LINES);
    $newLines = [];
    $inCreateMethod = false;
    $methodStartLine = -1;
    $braceCount = 0;
    
    foreach ($lines as $lineNum => $line) {
        // Check if this is the start of create method
        if (preg_match('/public function create\(/', $line)) {
            $inCreateMethod = true;
            $methodStartLine = $lineNum;
            $braceCount = 0;
            $newLines[] = $line;
            continue;
        }
        
        if ($inCreateMethod) {
            // Count braces
            $braceCount += substr_count($line, '{') - substr_count($line, '}');
            
            // If we find the closing brace and brace count is back to 0, method ended
            if (strpos($line, '}') !== false && $braceCount <= 0) {
                $inCreateMethod = false;
                
                // Get indentation from method signature
                $indent = str_repeat(' ', strspn($lines[$methodStartLine], ' '));
                
                // Add the fixed method body
                $newLines[] = $indent . '    {';
                $newLines[] = $indent . '        // Fix for PHP 8+ compatibility: handle QueryString properly';
                $newLines[] = $indent . '        if (is_string($url)) {';
                $newLines[] = $indent . '            $url = Url::factory($url);';
                $newLines[] = $indent . '        }';
                $newLines[] = $indent . '        ';
                $newLines[] = $indent . '        // Ensure QueryString is an object, not a string';
                $newLines[] = $indent . '        if ($url instanceof \\Guzzle\\Http\\Url) {';
                $newLines[] = $indent . '            $query = $url->getQuery();';
                $newLines[] = $indent . '            if (is_string($query)) {';
                $newLines[] = $indent . '                $queryString = \\Guzzle\\Http\\QueryString::fromString($query);';
                $newLines[] = $indent . '                $url->setQuery($queryString);';
                $newLines[] = $indent . '            }';
                $newLines[] = $indent . '        }';
                $newLines[] = $indent . '        ';
                $newLines[] = $indent . '        return parent::create($method, $url, $headers);';
                $newLines[] = $indent . '    }';
                continue;
            }
            
            // Skip original method body lines
            if ($braceCount > 0 || strpos($line, '{') !== false) {
                continue;
            }
        } else {
            $newLines[] = $line;
        }
    }
    
    $newContent = implode("\n", $newLines) . "\n";
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
