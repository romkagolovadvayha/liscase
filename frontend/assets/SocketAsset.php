<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class SocketAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public $js
        = [
            'js/socket.js?v=1.0.112',
        ];

    public $depends
        = [
            'frontend\assets\AppAsset',
            'frontend\assets\SupportAsset',
        ];
}
