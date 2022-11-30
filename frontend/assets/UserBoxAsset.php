<?php

namespace frontend\assets;

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
            'frontend\assets\AppAsset',
            'common\assets\SlickCarouselAsset',
        ];
}
