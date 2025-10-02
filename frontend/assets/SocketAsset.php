<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class SocketAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public function init()
    {
        parent::init();

        $this->js = [
            'js/socket.js?v=' . \Yii::$app->settings->get('site_version'),
        ];
    }

    public $jsOptions = [
        'defer' => 'defer',
    ];

    public $depends
        = [
            'frontend\assets\AppAsset',
            'frontend\assets\SupportAsset',
        ];
}
