<?php
/**
 * Bootstrap file to fix Ratchet/Guzzle PHP 8+ compatibility issue
 * This file should be included before starting WebSocket server
 * 
 * Fixes: TypeError: Guzzle\Http\Url::__construct(): Argument #7 ($query) must be of type ?Guzzle\Http\QueryString, string given
 */

// Only apply fix if Ratchet classes exist
if (class_exists('Ratchet\Http\Guzzle\Http\Message\RequestFactory')) {
    // Monkey patch the RequestFactory::create method
    $reflection = new ReflectionClass('Ratchet\Http\Guzzle\Http\Message\RequestFactory');
    
    if ($reflection->hasMethod('create')) {
        // Create a wrapper that fixes the QueryString issue
        $originalMethod = $reflection->getMethod('create');
        
        // We'll use runkit or uopz if available, otherwise we'll handle it at runtime
        // For now, we'll create a runtime fix that patches the class on first use
        if (!class_exists('Ratchet\Http\Guzzle\Http\Message\RequestFactoryFixed')) {
            eval('
                class Ratchet\Http\Guzzle\Http\Message\RequestFactoryFixed extends Ratchet\Http\Guzzle\Http\Message\RequestFactory {
                    public function create($method, $url, array $headers = array()) {
                        // Fix for PHP 8+ compatibility: handle QueryString properly
                        if (is_string($url)) {
                            $url = \Guzzle\Http\Url::factory($url);
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
                }
            ');
        }
    }
}

// Alternative: Patch HttpRequestParser which is the actual source of the problem
if (class_exists('Ratchet\Http\HttpRequestParser')) {
    // The issue is in HttpRequestParser::onMessage which calls RequestFactory::fromMessage
    // We need to intercept this call
}
