<?php
/**
 * Bootstrap file to fix Ratchet/Guzzle PHP 8+ compatibility issue
 * This file should be included before starting WebSocket server
 */

// Fix for Guzzle\Http\Url::__construct() PHP 8+ compatibility
if (class_exists('Guzzle\Http\Url')) {
    // Patch the Url::factory method to handle QueryString properly
    if (!function_exists('ratchet_guzzle_url_factory_fix')) {
        function ratchet_guzzle_url_factory_fix($url) {
            if (is_string($url)) {
                // Parse URL and ensure query string is properly handled
                $parsed = parse_url($url);
                if (isset($parsed['query'])) {
                    // Create QueryString object if needed
                    if (class_exists('Guzzle\Http\QueryString')) {
                        $queryString = \Guzzle\Http\QueryString::fromString($parsed['query']);
                        // Reconstruct URL with QueryString object
                        $url = \Guzzle\Http\Url::factory($parsed);
                        $url->setQuery($queryString);
                        return $url;
                    }
                }
            }
            return \Guzzle\Http\Url::factory($url);
        }
    }
}

// Alternative: Monkey patch Ratchet's RequestFactory
if (class_exists('Ratchet\Http\Guzzle\Http\Message\RequestFactory')) {
    $reflection = new ReflectionClass('Ratchet\Http\Guzzle\Http\Message\RequestFactory');
    if ($reflection->hasMethod('create')) {
        // The issue is in the create method, but we can't easily patch it
        // So we'll handle it at the WebSocketServer level
    }
}

