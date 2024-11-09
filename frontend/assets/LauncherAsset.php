<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace frontend\assets;

use lavrentiev\widgets\toastr\ToastrAsset;
use yii\bootstrap5\BootstrapAsset;
use yii\web\AssetBundle;

/**
 * Main application asset bundle.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class LauncherAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public $css = [
        'css/launcher.min.css?v=1.0.0',
    ];
    public $js = [
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/js/all.min.js',
        'https://momentjs.com/downloads/moment.min.js',
        'https://momentjs.com/downloads/moment-with-locales.min.js',
        'js/socket.js',
        'js/launcher.js',
    ];
    public $depends = [
        'yii\web\YiiAsset',
        BootstrapAsset::class,
        ToastrAsset::class,
    ];
}
