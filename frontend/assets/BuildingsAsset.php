<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class BuildingsAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public function init()
    {
        parent::init();

        $this->js = [
            'js/buildings.js?v=' . \Yii::$app->settings->get('site_version'),
        ];
    }

    public $jsOptions = [
        'defer' => 'defer',
    ];

    public $depends
        = [
            'frontend\assets\AppAsset',
        ];
}
