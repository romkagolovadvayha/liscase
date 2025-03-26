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
        'css/design/styles.min.css?v=1.33',
    ];

    public $js = [
        'js/cookie.js?v=1.0',
        'js/clipboard.min.js?v=1.0',
        'js/design/main.js?v=1.0',
        'js/design/menu.js?v=1.0',
        'js/main.js?v=1.1',
    ];

    public $depends = [
        AppAsset::class,
        MomentAsset::class,
    ];
}
