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
class AppAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
//        'https://cdn.datatables.net/2.0.2/css/dataTables.dataTables.min.css',
//        'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css',
//        'https://kenwheeler.github.io/slick/slick/slick-theme.css',
        'css/site.css?v=1.0.4',
        'css/main.css?v=1.0.4',
    ];
    public $js = [
//        'https://cdn.datatables.net/2.0.2/js/dataTables.min.js',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/js/all.min.js',
        'https://momentjs.com/downloads/moment.min.js',
        'https://momentjs.com/downloads/moment-with-locales.min.js',
        'js/clipboard.min.js',
        'js/main.js?v=1.0.4',
//        'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js',
    ];
    public $depends = [
        'yii\web\YiiAsset',
        BootstrapAsset::class,
        ToastrAsset::class,
    ];
}
