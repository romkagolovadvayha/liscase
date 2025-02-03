<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class BuildingsAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public $js
        = [
            'js/buildings.js?v=1.0',
        ];

    public $depends
        = [
            'frontend\assets\AppAsset',
        ];
}
