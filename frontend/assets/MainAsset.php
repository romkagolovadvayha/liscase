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
        'css/design/styles.min.css',
    ];

    public $js = [
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/js/all.min.js',
        'https://momentjs.com/downloads/moment.min.js',
        'https://momentjs.com/downloads/moment-with-locales.min.js',
        'js/cookie.js',
        'js/clipboard.min.js',
        'js/design/main.js',
        'js/design/menu.js',
        'js/main.js',
    ];

    public $depends = [
        AppAsset::class
    ];
}
