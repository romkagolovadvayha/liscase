<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class MapsAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public $jsOptions = [
        'defer' => 'defer',
    ];

    public function init()
    {
        parent::init();

        $this->js = [
            'js/maps.js?v=' . \Yii::$app->settings->get('site_version'),
        ];
    }

    public $depends
        = [
            'frontend\assets\AppAsset',
        ];
}

