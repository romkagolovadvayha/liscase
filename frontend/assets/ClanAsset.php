<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class ClanAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public $js
        = [
            'js/clan.js',
        ];

    public $depends
        = [
            'frontend\assets\AppAsset',
            MomentAsset::class,
        ];
}
