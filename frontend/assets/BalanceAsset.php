<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class BalanceAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public $js
        = [
            'js/balance.js',
        ];

    public $depends
        = [
            'frontend\assets\AppAsset',
        ];
}
