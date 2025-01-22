<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class SupportAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public $js
        = [
            'https://cdn.jsdelivr.net/gh/tigrr/circle-progress@v0.2.4/dist/circle-progress.min.js',
            'js/support.js',
        ];

    public $depends
        = [
            'frontend\assets\AppAsset',
            MomentAsset::class,
        ];
}
