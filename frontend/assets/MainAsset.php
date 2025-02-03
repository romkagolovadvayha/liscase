<?php

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * Main application asset bundle.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class MainAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public $css = [
//        'css/design/root-colors.min.css',
        'css/design/styles.min.css?v=1.0',
    ];

    public $js = [
        'js/cookie.js',
        'js/clipboard.min.js',
        'js/design/main.js',
        'js/design/menu.js',
        'js/main.js',
    ];

    public $depends = [
        AppAsset::class,
        MomentAsset::class,
    ];
}
