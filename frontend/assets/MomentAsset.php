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
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/js/all.min.js',
        'https://momentjs.com/downloads/moment.min.js',
        'https://momentjs.com/downloads/moment-with-locales.min.js',
    ];

    public $depends = [
        AppAsset::class
    ];
}
