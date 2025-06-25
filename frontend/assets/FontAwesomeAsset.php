<?php

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * Main application asset bundle.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class FontAwesomeAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public $css = [];

    public $js = [
        'js/fontawesome-free-6.7.2/js/all.min.js',
    ];

    public $depends = [
        'yii\web\YiiAsset',
    ];
}
