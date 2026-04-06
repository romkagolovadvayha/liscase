<?php

namespace common\components\bootstrap;

use Throwable;
use Yii;
use yii\base\BootstrapInterface;
use yii\helpers\FileHelper;
use yii\web\Application as WebApplication;

/**
 * Готовит @webroot/minify до первого обращения к view (например DbTemplateBootstrap).
 * Каталог из git часто 755 от владельца деплоя — без chmod PHP-FPM не может писать.
 */
class MinifyPathBootstrap implements BootstrapInterface
{
    public function bootstrap($app): void
    {
        if (!$app instanceof WebApplication) {
            return;
        }
        $dir = Yii::getAlias('@webroot/minify', false);
        if ($dir === false) {
            return;
        }
        try {
            FileHelper::createDirectory($dir, 0777);
            if (is_dir($dir) && !is_writable($dir)) {
                @chmod($dir, 0777);
            }
            if (is_dir($dir) && !is_writable($dir)) {
                Yii::warning("MinifyPathBootstrap: $dir is not writable for PHP; chown to FPM user or chmod.", __METHOD__);
            }
        } catch (Throwable $e) {
            Yii::warning('MinifyPathBootstrap: ' . $e->getMessage(), __METHOD__);
        }
    }
}
