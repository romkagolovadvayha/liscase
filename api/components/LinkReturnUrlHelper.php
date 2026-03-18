<?php

namespace api\components;

use Yii;

/**
 * URL для редиректа после привязки Discord/Twitch/Kick.
 * Когда API на api.*, по умолчанию редирект на основной домен /profile.
 */
class LinkReturnUrlHelper
{
    /**
     * URL по умолчанию (фронт профиля), когда return_url не был сохранён при старте OAuth.
     */
    public static function getDefaultProfileUrl(): string
    {
        $frontendUrl = Yii::$app->params['frontendUrl'] ?? null;
        if (!empty($frontendUrl)) {
            return rtrim($frontendUrl, '/') . '/profile';
        }
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        if (strpos($host, 'api.') === 0) {
            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            return $scheme . '://' . substr($host, 4) . '/profile';
        }
        $homePage = Yii::$app->params['homePage'] ?? 'http://localhost';
        return rtrim($homePage, '/') . '/profile';
    }

    /**
     * Проверка, что URL безопасен для редиректа (тот же фронт/домен).
     * Для локальной разработки можно задать params['allowedRedirectHosts'] => ['prostoj.store', 'prostoj.local', 'localhost'].
     */
    public static function isValidReturnUrl(?string $url): bool
    {
        if (empty($url) || !preg_match('#^https?://#i', $url)) {
            return false;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === null) {
            return false;
        }
        $hostLower = strtolower($host);
        $allowedHosts = Yii::$app->params['allowedRedirectHosts'] ?? null;
        if (!empty($allowedHosts) && is_array($allowedHosts)) {
            foreach ($allowedHosts as $allowed) {
                if (is_string($allowed) && $hostLower === strtolower($allowed)) {
                    return true;
                }
            }
            return false;
        }
        $frontendUrl = Yii::$app->params['frontendUrl'] ?? null;
        if (!empty($frontendUrl)) {
            $allowedHost = parse_url($frontendUrl, PHP_URL_HOST);
            if ($allowedHost && $hostLower === strtolower($allowedHost)) {
                return true;
            }
        }
        $currentHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $mainHost = (strpos($currentHost, 'api.') === 0) ? substr($currentHost, 4) : $currentHost;
        return $hostLower === strtolower($mainHost);
    }
}
