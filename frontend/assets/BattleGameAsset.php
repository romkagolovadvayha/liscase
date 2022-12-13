<?php

namespace frontend\assets;

use yii\bootstrap5\BootstrapPluginAsset;
use yii\web\AssetBundle;
use yii\web\JqueryAsset;

class BattleGameAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public $js
        = [
            'https://cdnjs.cloudflare.com/ajax/libs/velocity/1.5.0/velocity.min.js',
            'https://cdnjs.cloudflare.com/ajax/libs/lodash.js/4.17.4/lodash.min.js',
            'https://cdnjs.cloudflare.com/ajax/libs/backbone.js/1.3.3/backbone-min.js',
            'js/battle-game.js',
        ];

    public $depends
        = [
            BootstrapPluginAsset::class,
            JqueryAsset::class,
        ];
}
