<?php

namespace common\components\support;

/**
 * Prepares support messages for messenger notifications.
 */
final class SupportMessageNotificationFormatter
{
    private const STICKER_PATTERNS = [
        '~<video\b(?=[^>]*\bclass\s*=\s*(["\'])[^"\']*\bsupport_sticker\b[^"\']*\1)[^>]*>.*?</video\s*>~isu',
        '~<img\b(?=[^>]*\bclass\s*=\s*(["\'])[^"\']*\bsupport_sticker\b[^"\']*\1)[^>]*\s*/?>~isu',
    ];

    public static function format(string $message, bool $hadFiles = false): string
    {
        $decoded = html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (stripos($decoded, 'support_sticker') !== false) {
            $message = self::replaceStickerTags($decoded);
        }

        $visibleText = trim(strip_tags(str_replace(
            ['&nbsp;', "\xc2\xa0"],
            ' ',
            html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8')
        )));

        if ($message === '' || $visibleText === '') {
            return $hadFiles ? '[вложения]' : '[сообщение]';
        }

        return $message;
    }

    public static function stickerUrl(string $message): ?string
    {
        $decoded = html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (stripos($decoded, 'support_sticker') === false) {
            return null;
        }

        foreach (self::STICKER_PATTERNS as $pattern) {
            if (preg_match($pattern, $decoded, $matches) !== 1) {
                continue;
            }

            $url = trim(html_entity_decode(self::attribute($matches[0], 'src'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($url !== '' && (str_starts_with($url, '/') || filter_var($url, FILTER_VALIDATE_URL))) {
                return $url;
            }
        }

        return null;
    }

    private static function replaceStickerTags(string $message): string
    {
        foreach (self::STICKER_PATTERNS as $pattern) {
            $message = preg_replace_callback($pattern, static function (array $matches): string {
                return self::stickerLabel($matches[0]);
            }, $message) ?? $message;
        }

        return trim($message);
    }

    private static function stickerLabel(string $tag): string
    {
        $name = self::attribute($tag, 'alt') ?: self::attribute($tag, 'title');
        if ($name !== '') {
            $name = trim(strip_tags(html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $name = preg_replace('/^стикер\s*/iu', '', $name) ?? $name;
        }

        if ($name === '') {
            $src = self::attribute($tag, 'src');
            if ($src !== '') {
                $path = (string)(parse_url(html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_PATH) ?? '');
                $basename = pathinfo(rawurldecode(basename($path)), PATHINFO_FILENAME);
                if ($basename !== '') {
                    $name = preg_replace('/[-_]+/u', ' ', $basename) ?? $basename;
                }
            }
        }

        if ($name === '') {
            return '🖼 Стикер';
        }

        return '🖼 Стикер «' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '»';
    }

    private static function attribute(string $html, string $attribute): string
    {
        $pattern = '/\b' . preg_quote($attribute, '/') . '\s*=\s*(["\'])(.*?)\1/isu';
        if (preg_match($pattern, $html, $matches) !== 1) {
            return '';
        }

        return (string)$matches[2];
    }
}
