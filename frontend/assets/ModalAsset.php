<?php

namespace frontend\assets;

use common\assets\SlickCarouselAsset;
use yii\bootstrap5\BootstrapPluginAsset;
use yii\web\AssetBundle;

class ModalAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public function init()
    {
        parent::init();

        $this->js = [
            'js/modal.js?v=' . \Yii::$app->settings->get('site_version'),
        ];
    }

    public $jsOptions = [
        'defer' => 'defer',
    ];

    public $depends
        = [
            BootstrapPluginAsset::class,
        ];
}
