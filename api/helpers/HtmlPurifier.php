<?php

namespace yii\helpers;

/**
 * Переопределение для API: кеш HTMLPurifier в @runtime; если каталог не создан / не writable —
 * fallback в sys_get_temp_dir() (иначе падают вебхуки Telegram и любой save() с filter HtmlPurifier).
 */
class HtmlPurifier extends BaseHtmlPurifier
{
    protected static function configure($config)
    {
        $runtime = \Yii::$app->getRuntimePath();
        try {
            FileHelper::createDirectory($runtime, 0775);
        } catch (\Throwable $e) {
            // оставим проверку is_writable ниже
        }

        if (!is_writable($runtime)) {
            $fallback = rtrim(sys_get_temp_dir(), '/\\')
                . DIRECTORY_SEPARATOR
                . 'prostoj-api-htmlpurifier';
            FileHelper::createDirectory($fallback, 0777);
            $config->set('Cache.SerializerPath', $fallback);
            $config->set('Cache.SerializerPermissions', 0777);
        }
    }
}
