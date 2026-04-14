<?php

namespace common\helpers;

use Yii;

/**
 * Публичный базовый URL сайта (фронт), без завершающего «/».
 * Учитывает params (baseUrl, frontendUrl, homePage, domain) и снимает поддомен api. с URL, совпадающего с хостом API.
 */
final class FrontendPublicUrl
{
    public static function getBaseUrl(): string
    {
        $p = Yii::$app->params;

        foreach (['baseUrl', 'frontendUrl'] as $key) {
            if (!empty($p[$key]) && \is_string($p[$key])) {
                $u = self::normalizeBase($p[$key]);
                if ($u !== '') {
                    return $u;
                }
            }
        }

        if (!empty($p['homePage']) && \is_string($p['homePage'])) {
            $u = self::normalizeBase($p['homePage']);
            if ($u !== '' && !self::isLocalhostUrl($u)) {
                return $u;
            }
        }

        if (!empty($p['domain']) && \is_string($p['domain'])) {
            $domain = trim($p['domain'], " \t\n\r\0\x0B/");
            if ($domain !== '') {
                return 'https://' . $domain;
            }
        }

        $fromRequest = self::fromRequestHost();
        if ($fromRequest !== '') {
            return $fromRequest;
        }

        if (!empty($p['homePage']) && \is_string($p['homePage'])) {
            $u = self::normalizeBase($p['homePage']);
            if ($u !== '') {
                return $u;
            }
        }

        return YII_ENV_DEV ? 'http://localhost' : '';
    }

    private static function normalizeBase(string $url): string
    {
        $u = rtrim($url, '/');
        return str_replace('api.', '', $u);
    }

    private static function isLocalhostUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === false || $host === null || $host === '') {
            return false;
        }
        $h = strtolower($host);
        return $h === 'localhost' || $h === '127.0.0.1' || $h === '::1';
    }

    /**
     * База для ссылок с хоста текущего HTTP-запроса к API (за прокси — X-Forwarded-*).
     */
    private static function fromRequestHost(): string
    {
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '';
        if ($host !== '' && strpos($host, ',') !== false) {
            $host = trim(explode(',', $host)[0]);
        }
        if ($host === '') {
            return '';
        }
        if (strpos($host, 'api.') === 0) {
            $host = substr($host, 4);
        }

        $scheme = null;
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $scheme = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]);
        }
        if ($scheme === null || $scheme === '') {
            $scheme = $_SERVER['REQUEST_SCHEME'] ?? null;
        }
        if ($scheme === null || $scheme === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        }

        return $scheme . '://' . $host;
    }
}
