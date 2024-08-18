<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace frontend\assets;

use yii\bootstrap5\BootstrapPluginAsset;
use yii\web\AssetBundle;

/**
 * Main application asset bundle.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ChartistAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'https://cdn.jsdelivr.net/chartist.js/latest/chartist.min.css',
    ];
    public $js = [
        'https://cdn.jsdelivr.net/chartist.js/latest/chartist.min.js',
    ];
    public $depends = [
        BootstrapPluginAsset::class,
    ];
}
