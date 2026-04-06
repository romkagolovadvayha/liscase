<?php

namespace common\components\bootstrap;

use Throwable;
use Yii;
use yii\base\BootstrapInterface;
use yii\helpers\FileHelper;
use yii\web\Application as WebApplication;

/**
 * Создаёт @webroot/minify до инициализации rmrevin\yii\minify\View (common/config/web.php).
 * Если родительский каталог не даёт mkdir — на сервере нужны права/chown для PHP-FPM.
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
            if (!is_dir($dir)) {
                FileHelper::createDirectory($dir, 0775);
            }
        } catch (Throwable $e) {
            Yii::warning('MinifyPathBootstrap: ' . $e->getMessage(), __METHOD__);
        }
    }
}
