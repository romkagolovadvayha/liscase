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
    public $sourcePath = '@npm/fontawesome-free';

    public $css = [];

    public $js = [
        'js/all.min.js',
    ];

    public $depends = [
        'yii\web\YiiAsset',
    ];
}
