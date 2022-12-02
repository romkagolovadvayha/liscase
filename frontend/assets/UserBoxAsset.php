<?php

namespace frontend\assets;

use common\assets\SlickCarouselAsset;
use yii\bootstrap5\BootstrapAsset;
use yii\bootstrap5\BootstrapPluginAsset;
use yii\web\AssetBundle;

class UserBoxAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public $js
        = [
            'js/user-box.js',
        ];

    public $depends
        = [
            SlickCarouselAsset::class,
            BootstrapPluginAsset::class,
        ];
}
