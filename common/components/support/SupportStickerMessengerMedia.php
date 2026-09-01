<?php

namespace common\components\support;

use Yii;

/**
 * Prepares custom support stickers for messenger image attachments.
 */
final class SupportStickerMessengerMedia
{
    private const CACHE_TTL = 2592000;
    private const MAX_DOWNLOAD_BYTES = 5242880;
    private const MAX_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'tiff', 'bmp', 'heic'];

    public static function absoluteUrl(string $url, string $siteDomain): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, '/')) {
            $url = 'https://' . trim($siteDomain, '/') . $url;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return strtolower((string)parse_url($url, PHP_URL_SCHEME)) === 'https' ? $url : null;
    }

    /**
     * MAX accepts a public URL for an image. Convert WEBP stickers to PNG because
     * WEBP is not listed among MAX image attachment formats.
     */
    public static function maxImageUrl(string $url, string $siteDomain): ?string
    {
        $url = self::absoluteUrl($url, $siteDomain);
        if ($url === null || !self::isAllowedStickerUrl($url, $siteDomain)) {
            return null;
        }

        $extension = strtolower(pathinfo((string)parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (in_array($extension, self::MAX_IMAGE_EXTENSIONS, true)) {
            return $url;
        }
        if ($extension !== 'webp' || !Yii::$app->has('s3Api')) {
            return null;
        }

        $cacheKey = 'support_sticker_messenger_png:' . sha1($url);
        $cached = Yii::$app->cache->get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $source = self::download($url);
        if ($source === null || !function_exists('imagecreatefromstring')) {
            return null;
        }

        $png = self::webpToPng($source);
        if ($png === null) {
            return null;
        }

        $key = 'support/stickers/messenger/' . sha1($url) . '.png';
        if (Yii::$app->s3Api->putFile($key, $png, 'image/png') === false) {
            return null;
        }

        $result = Yii::$app->s3Api->getPublicUrl($key);
        Yii::$app->cache->set($cacheKey, $result, self::CACHE_TTL);

        return $result;
    }

    public static function webpToPng(string $source): ?string
    {
        if ($source === '' || !function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring($source);
        if ($image === false) {
            return null;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        ob_start();
        $written = imagepng($image, null, 6);
        $png = ob_get_clean();
        imagedestroy($image);
        if (!$written || !is_string($png) || $png === '') {
            return null;
        }

        return $png;
    }

    private static function isAllowedStickerUrl(string $url, string $siteDomain): bool
    {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        $path = (string)parse_url($url, PHP_URL_PATH);
        if ($host === '' || !preg_match('#/(?:support/stickers|stickers)/#i', $path)) {
            return false;
        }

        $allowedHosts = [strtolower((string)parse_url('https://' . trim($siteDomain, '/'), PHP_URL_HOST))];
        if (Yii::$app->has('s3Api')) {
            $allowedHosts[] = strtolower((string)parse_url(
                Yii::$app->s3Api->getPublicUrl('support/stickers/'),
                PHP_URL_HOST
            ));
        }

        return in_array($host, array_filter(array_unique($allowedHosts)), true);
    }

    private static function download(string $url): ?string
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_MAXFILESIZE => self::MAX_DOWNLOAD_BYTES,
        ]);
        $body = curl_exec($curl);
        $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (!is_string($body) || $httpCode < 200 || $httpCode >= 300 || strlen($body) > self::MAX_DOWNLOAD_BYTES) {
            return null;
        }
        if (@getimagesizefromstring($body) === false) {
            return null;
        }

        return $body;
    }
}
