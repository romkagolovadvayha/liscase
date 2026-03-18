<?php

namespace common\components;

use common\models\box\DropImage;
use Yii;

/**
 * Скачивает постер видео по URL, прогоняет через TinyPNG, загружает в S3.
 * poster_image_150 — уменьшенная копия 250×250px (обрезка по меньшей стороне, центр).
 */
class VideoPosterUploader
{
    private const S3_PREFIX = 'uploads/user-video/';
    private const TINIFY_KEYS = [
        'dY4rkCVRZxqxWD3wZcCdysWBbM7CGWB8',
        'SQMyJN0ZNs1zQfzrwBjMcsRHCnpffCbl',
    ];

    private const THUMB_SIZE = 250;

    /**
     * Скачать постер по URL, оптимизировать TinyPNG, загрузить в S3 (оригинал + копия 250×250 по меньшей стороне).
     *
     * @param string $posterUrl URL постера (YouTube/TikTok thumbnail)
     * @return array|null ['poster_image' => string, 'poster_image_150' => string, 'poster_image_400' => string] или null при ошибке
     */
    public static function uploadPoster(string $posterUrl): ?array
    {
        $posterUrl = trim($posterUrl);
        if ($posterUrl === '') {
            return null;
        }
        if (!Yii::$app->has('s3Api')) {
            Yii::warning('VideoPosterUploader: s3Api not available', __METHOD__);
            return null;
        }

        $tempDir = sys_get_temp_dir();
        $uniq = uniqid('video_', true);
        $tempOriginal = $tempDir . '/' . str_replace('.', '_', $uniq) . '_orig.png';
        $tempThumb = $tempDir . '/' . str_replace('.', '_', $uniq) . '_' . self::THUMB_SIZE . '.png';

        $imageContent = self::downloadImage($posterUrl);
        if ($imageContent === null || $imageContent === '') {
            return null;
        }

        if (file_put_contents($tempOriginal, $imageContent) === false) {
            Yii::error('VideoPosterUploader: failed to write temp file', __METHOD__);
            return null;
        }

        $imageInfo = @getimagesizefromstring($imageContent);
        if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
            @unlink($tempOriginal);
            Yii::error('VideoPosterUploader: invalid image', __METHOD__);
            return null;
        }

        self::optimizeWithTinify($tempOriginal);

        $s3Api = Yii::$app->s3Api;
        $baseName = $uniq . '.png';
        $s3KeyOriginal = self::S3_PREFIX . $baseName;
        $s3KeyThumb = self::S3_PREFIX . self::THUMB_SIZE . '_' . $baseName;

        $originalContent = file_get_contents($tempOriginal);
        if ($originalContent === false) {
            @unlink($tempOriginal);
            return null;
        }
        if ($s3Api->putFile($s3KeyOriginal, $originalContent, 'image/png') === false) {
            @unlink($tempOriginal);
            Yii::error('VideoPosterUploader: failed to upload original to S3', __METHOD__);
            return null;
        }

        $posterUrlMain = $s3Api->getPublicUrl($s3KeyOriginal);

        if (!DropImage::resizeImageByMinSide($tempOriginal, $tempThumb, self::THUMB_SIZE)) {
            @unlink($tempOriginal);
            @unlink($tempThumb);
            return [
                'poster_image' => $posterUrlMain,
                'poster_image_150' => $posterUrlMain,
                'poster_image_400' => $posterUrlMain,
            ];
        }

        $contentThumb = file_get_contents($tempThumb);
        @unlink($tempOriginal);
        @unlink($tempThumb);
        if ($contentThumb === false || $s3Api->putFile($s3KeyThumb, $contentThumb, 'image/png') === false) {
            return [
                'poster_image' => $posterUrlMain,
                'poster_image_150' => $posterUrlMain,
                'poster_image_400' => $posterUrlMain,
            ];
        }

        $posterUrlThumb = $s3Api->getPublicUrl($s3KeyThumb);

        return [
            'poster_image' => $posterUrlMain,
            'poster_image_150' => $posterUrlThumb,
            'poster_image_400' => $posterUrlMain,
        ];
    }

    private static function downloadImage(string $url): ?string
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $headers = "User-Agent: {$ua}\r\n";
        if (stripos($url, 'tiktok') !== false) {
            $headers .= "Referer: https://www.tiktok.com/\r\n";
        }
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 15,
                'ignore_errors' => true,
                'header' => $headers,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        return is_string($raw) ? $raw : null;
    }

    private static function optimizeWithTinify(string $filePath): void
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return;
        }
        foreach (self::TINIFY_KEYS as $key) {
            try {
                if (method_exists('\Tinify\Tinify', 'setTimeout')) {
                    \Tinify\Tinify::setTimeout(5);
                }
                \Tinify\setKey($key);
                $source = \Tinify\fromFile($filePath);
                $source->toFile($filePath);
                return;
            } catch (\Tinify\Exception $e) {
                continue;
            } catch (\Exception $e) {
                Yii::info('Tinify error for video poster: ' . $e->getMessage(), __METHOD__);
                return;
            }
        }
    }
}
