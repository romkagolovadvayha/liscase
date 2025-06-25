<?php

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * Main application asset bundle.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class CircleProgressAsset extends AssetBundle
{
    public $sourcePath = '@npm/js-circle-progress/dist';

    public $css = [];

    public $js = [
        'js/circle-progress.min.js',
    ];

    public $depends = [
        AppAsset::class
    ];
}
