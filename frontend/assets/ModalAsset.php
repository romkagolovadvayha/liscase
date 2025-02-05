<?php

namespace frontend\assets;

use common\assets\SlickCarouselAsset;
use yii\bootstrap5\BootstrapPluginAsset;
use yii\web\AssetBundle;

class ModalAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public $js
        = [
            'js/modal.js?v=1.1',
        ];

    public $depends
        = [
            BootstrapPluginAsset::class,
        ];
}
