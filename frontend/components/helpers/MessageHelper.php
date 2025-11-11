<?php

namespace frontend\components\helpers;

use Yii;

/**
 * Хелпер для обработки сообщений поддержки
 */
class MessageHelper
{
    /**
     * Обработка сообщения (ссылки, эмодзи, стикеры)
     */
    public static function processMessage($message)
    {
        // Обрабатываем ссылки
        $message = self::processLinks($message);
        
        // Обрабатываем эмодзи
        $message = self::processEmojis($message);
        
        // Обрабатываем стикеры
        $message = self::processStickers($message);
        
        return $message;
    }

    /**
     * Обработка ссылок
     */
    private static function processLinks($message)
    {
        // Автоматически делаем ссылки кликабельными (улучшенная версия)
        $urlRegex = '/(?<!href=["\'])(?<!src=["\'])(https?:\/\/[^\s<>"\'\)]+)/i';
        $message = preg_replace_callback($urlRegex, function($matches) {
            $url = $matches[1];
            // Обрезаем URL если он слишком длинный
            $displayUrl = strlen($url) > 50 ? substr($url, 0, 47) . '...' : $url;
            return '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener noreferrer" class="message-link">' . htmlspecialchars($displayUrl) . '</a>';
        }, $message);
        
        return $message;
    }

    /**
     * Обработка эмодзи
     */
    private static function processEmojis($message)
    {
        $emojiMap = [
            ':)(' => '😊',
            ':(' => '😢',
            ':D' => '😃',
            ':P' => '😛',
            ';)' => '😉',
            ':|' => '😐',
            ':o' => '😮',
            ':*' => '😘',
            '<3' => '❤️',
            '</3' => '💔',
            'xD' => '😂',
            'o.O' => '😵',
            '^.^' => '😊',
            'T_T' => '😭'
        ];

        foreach ($emojiMap as $shortcut => $emoji) {
            $message = str_replace($shortcut, $emoji, $message);
        }

        return $message;
    }

    /**
     * Обработка стикеров
     */
    private static function processStickers($message)
    {
        // Заменяем [sticker:name] на HTML тег
        $stickerRegex = '/\[sticker:([a-zA-Z0-9_.-]+)\]/';
        $message = preg_replace_callback($stickerRegex, function($matches) {
            $stickerName = $matches[1];
            
            // Поддерживаем разные форматы файлов
            $supportedFormats = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
            $stickerPath = null;
            $extension = null;
            
            foreach ($supportedFormats as $format) {
                $testPath = Yii::getAlias('@frontend/web/stickers/' . $stickerName . '.' . $format);
                if (file_exists($testPath)) {
                    $stickerPath = $testPath;
                    $extension = $format;
                    break;
                }
            }
            
            if ($stickerPath) {
                return '<img src="/stickers/' . htmlspecialchars($stickerName) . '.' . $extension . '" class="message-sticker" alt="стикер ' . htmlspecialchars($stickerName) . '" title="стикер ' . htmlspecialchars($stickerName) . '">';
            } else {
                // Если стикер не найден, показываем текст
                return '<span class="sticker-not-found">[стикер: ' . htmlspecialchars($stickerName) . ']</span>';
            }
        }, $message);
        
        return $message;
    }
}


