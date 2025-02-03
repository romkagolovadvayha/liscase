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
            'js/user-box.js?v=1.0',
        ];

    public $depends
        = [
            SlickCarouselAsset::class,
            BootstrapPluginAsset::class,
        ];
}
