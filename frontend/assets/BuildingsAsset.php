<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class BuildingsAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public $js
        = [
            'js/buildings.js',
        ];

    public $depends
        = [
            'frontend\assets\AppAsset',
        ];
}
