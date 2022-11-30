<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class OnlineCounterAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public $js
        = [
            'js/online-counter.js',
        ];

    public $depends
        = [
            'frontend\assets\AppAsset',
        ];
}
