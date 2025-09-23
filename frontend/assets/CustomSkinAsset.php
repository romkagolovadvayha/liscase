<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class CustomSkinAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public $jsOptions = [
        'defer' => 'defer',
    ];

    public function init()
    {
        parent::init();

        $this->js = [
            'js/custom-skins.js?v=' . \Yii::$app->settings->get('site_version'),
        ];
    }

    public $depends
        = [
            'frontend\assets\AppAsset',
        ];
}
