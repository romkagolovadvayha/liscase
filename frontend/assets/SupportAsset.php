<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class SupportAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public function init()
    {
        parent::init();

        $this->js = [
            'js/circle-progress.min.js',
            'js/support.js?v=' . \Yii::$app->settings->get('site_version'),
        ];
    }

    public $depends
        = [
            'frontend\assets\AppAsset',
            MomentAsset::class,
        ];
}
