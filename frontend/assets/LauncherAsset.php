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
        'css/launcher.min.css?v=1.1',
    ];
    public $js = [
        'js/launcher.js?v=1.1',
    ];
    public $depends = [
        'yii\web\YiiAsset',
        BootstrapAsset::class,
        ToastrAsset::class,
        MomentAsset::class,
        SocketAsset::class,
    ];
}
