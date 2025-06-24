<?php

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * Main application asset bundle.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class MomentAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public $css = [];

    public $js = [
        'js/fontawesome-free-6.7.2/js/all.js',
        'js/momentjs/moment.min.js',
        'js/momentjs/moment-with-locales.min.js',
    ];

    public $depends = [
        AppAsset::class
    ];
}
