<?php

namespace common\components;

use common\models\video\UserVideo;

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
        return (bool) preg_match('#tiktok\.com/[^/]+/video/\d+#i', $url);
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
        $json = self::httpGet($oembedUrl, 10);
        if ($json === null) {
            return [];
        }
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['title'])) {
            return [];
        }
        $thumb = $data['thumbnail_url'] ?? $data['thumbnail'] ?? '';
        return [
            'type' => UserVideo::TYPE_TIKTOK,
            'name' => $data['title'],
            'poster_image' => $thumb,
            'poster_image_150' => $thumb,
            'poster_image_400' => $thumb,
        ];
    }

    private static function httpGet(string $url, int $timeout = 10): ?string
    {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'ignore_errors' => true,
                'header' => "User-Agent: Mozilla/5.0 (compatible; Bot/1.0)\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        return is_string($raw) ? $raw : null;
    }
}
