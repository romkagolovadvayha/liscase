<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class SupportAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public $js
        = [
            'js/support.js?v=1.0.111',
        ];

    public $depends
        = [
            'frontend\assets\AppAsset',
        ];
}
