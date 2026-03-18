<?php

namespace common\components;

use common\models\video\UserVideo;
use Yii;

/**
 * Получение названия и превью видео по ссылке (YouTube, TikTok) через oembed.
 */
class VideoMetadataFetcher
{
    /**
     * Определяет тип по ссылке и получает метаданные.
     *
     * @param string $url Нормализованная ссылка на видео
     * @return array ['type' => string, 'name' => string, 'poster_image' => ?string, 'poster_image_150' => ?string, 'poster_image_400' => ?string] или пустой массив при ошибке
     */
    public static function fetch(string $url): array
    {
        $url = trim($url);
        if (self::isYouTubeUrl($url)) {
            return self::fetchYouTube($url);
        }
        if (self::isTikTokUrl($url)) {
            return self::fetchTikTok($url);
        }
        return [];
    }

    public static function isYouTubeUrl(string $url): bool
    {
        return (bool) preg_match('#(?:youtube\.com/watch\?v=|youtube\.com/shorts/|youtu\.be/|youtube\.com/embed/)[\w-]+#i', $url);
    }

    public static function isTikTokUrl(string $url): bool
    {
        // Ссылка на видео: tiktok.com (любой поддомен) / @user или user / video / цифры
        return (bool) preg_match('#tiktok\.com/.+?/video/[0-9]+#i', $url);
    }

    /**
     * Нормализует ссылку (единый вид для сохранения и проверки типа).
     */
    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if (preg_match('#youtu\.be/([\w-]+)#i', $url, $m)) {
            return 'https://www.youtube.com/watch?v=' . $m[1];
        }
        if (preg_match('#youtube\.com/embed/([\w-]+)#i', $url, $m)) {
            return 'https://www.youtube.com/watch?v=' . $m[1];
        }
        if (preg_match('#youtube\.com/shorts/([\w-]+)#i', $url, $m)) {
            return 'https://www.youtube.com/watch?v=' . $m[1];
        }
        return $url;
    }

    /**
     * @return array ['type' => 'youtube', 'name' => ..., 'poster_image' => ..., 'poster_image_150' => ..., 'poster_image_400' => ...]
     */
    protected static function fetchYouTube(string $url): array
    {
        $oembedUrl = 'https://www.youtube.com/oembed?url=' . rawurlencode($url) . '&format=json';
        $json = self::httpGet($oembedUrl, 10);
        if ($json === null) {
            return [];
        }
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['title'])) {
            return [];
        }
        $thumb = $data['thumbnail_url'] ?? '';
        $videoId = '';
        if (preg_match('#/vi/([\w-]+)/#', $thumb, $m)) {
            $videoId = $m[1];
        } elseif (preg_match('#youtube\.com/watch\?v=([\w-]+)#i', $url, $m)) {
            $videoId = $m[1];
        } elseif (preg_match('#youtube\.com/shorts/([\w-]+)#i', $url, $m)) {
            $videoId = $m[1];
        } elseif (preg_match('#youtu\.be/([\w-]+)#i', $url, $m)) {
            $videoId = $m[1];
        }
        $poster150 = $poster400 = $thumb;
        if ($videoId) {
            $poster150 = "https://i.ytimg.com/vi/{$videoId}/mqdefault.jpg";
            $poster400 = "https://i.ytimg.com/vi/{$videoId}/sddefault.jpg";
        }
        return [
            'type' => UserVideo::TYPE_YOUTUBE,
            'name' => $data['title'],
            'poster_image' => $thumb,
            'poster_image_150' => $poster150,
            'poster_image_400' => $poster400,
        ];
    }

    /**
     * @return array ['type' => 'tiktok', 'name' => ..., 'poster_image' => ..., 'poster_image_150' => ..., 'poster_image_400' => ...]
     */
    protected static function fetchTikTok(string $url): array
    {
        $oembedUrl = 'https://www.tiktok.com/oembed?url=' . rawurlencode($url);
        $json = self::httpGet($oembedUrl, 15, self::getTikTokUserAgent());
        $fallbackName = self::getTikTokFallbackName($url);
        self::logTikTokToTelegram($url, $oembedUrl, $json);
        if ($json === null) {
            return self::tiktokMetaFallback($fallbackName);
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return self::tiktokMetaFallback($fallbackName);
        }
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = $fallbackName;
        }
        $thumb = $data['thumbnail_url'] ?? $data['thumbnail'] ?? '';
        return [
            'type' => UserVideo::TYPE_TIKTOK,
            'name' => $title,
            'poster_image' => $thumb ?: null,
            'poster_image_150' => $thumb ?: null,
            'poster_image_400' => $thumb ?: null,
        ];
    }

    private static function logTikTokToTelegram(string $videoUrl, string $oembedUrl, ?string $response): void
    {
        if (!Yii::$app->has('telegramChats')) {
            return;
        }
        $maxLen = 3800;
        $pre = "TikTok oembed\nurl: " . $oembedUrl . "\n";
        if ($response === null) {
            $msg = $pre . "response: NULL (timeout/403/error)";
        } else {
            $len = strlen($response);
            $body = $len > $maxLen ? substr($response, 0, $maxLen) . "\n...truncated " . $len . " total" : $response;
            $msg = $pre . "response length: " . $len . "\nbody:\n" . $body;
        }
        try {
            Yii::$app->telegramChats->sendMessage($msg);
        } catch (\Throwable $e) {
            Yii::warning('VideoMetadataFetcher telegramChats: ' . $e->getMessage(), __METHOD__);
        }
    }

    private static function getTikTokFallbackName(string $url): string
    {
        if (preg_match('#tiktok\.com/@([^/]+)/video/#i', $url, $m)) {
            return 'TikTok @' . $m[1];
        }
        return 'TikTok video';
    }

    private static function tiktokMetaFallback(string $name): array
    {
        return [
            'type' => UserVideo::TYPE_TIKTOK,
            'name' => $name,
            'poster_image' => null,
            'poster_image_150' => null,
            'poster_image_400' => null,
        ];
    }

    private static function getTikTokUserAgent(): string
    {
        return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    }

    private static function httpGet(string $url, int $timeout = 10, ?string $userAgent = null): ?string
    {
        $userAgent = $userAgent ?? 'Mozilla/5.0 (compatible; Bot/1.0)';
        $ctx = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'ignore_errors' => true,
                'header' => "User-Agent: {$userAgent}\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        return is_string($raw) ? $raw : null;
    }
}
