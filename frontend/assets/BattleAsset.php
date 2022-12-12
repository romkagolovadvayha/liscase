<?php

namespace frontend\assets;

use yii\bootstrap5\BootstrapPluginAsset;
use yii\web\AssetBundle;
use yii\web\JqueryAsset;

class BattleAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public $js
        = [
            'js/battle.js',
        ];

    public $depends
        = [
            BootstrapPluginAsset::class,
            JqueryAsset::class,
        ];
}
