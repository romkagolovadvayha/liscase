<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class MapsV2Asset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public $jsOptions = [
        'defer' => 'defer',
    ];

    public $depends = [
        AppAsset::class,
    ];

    public function init()
    {
        parent::init();

        $version = \Yii::$app->settings->get('site_version');

        $this->js = [
            'js/maps-v2.js?v=' . $version,
        ];
    }
}

