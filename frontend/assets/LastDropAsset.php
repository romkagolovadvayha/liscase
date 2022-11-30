<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class LastDropAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public $js
        = [
            'js/last-drop.js',
        ];

    public $depends
        = [
            'frontend\assets\AppAsset',
        ];
}
