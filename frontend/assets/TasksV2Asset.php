<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class TasksV2Asset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public $jsOptions = [
        'defer' => 'defer',
    ];

    public function init()
    {
        parent::init();

        $this->js = [
            'js/tasks-v2.js?v=' . \Yii::$app->settings->get('site_version'),
        ];
    }

    public $depends = [
        'frontend\assets\AppAsset',
        'frontend\assets\ModalAsset',
    ];
}
































