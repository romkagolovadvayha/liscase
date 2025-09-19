<?php

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * Main application asset bundle.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class MainAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public function init()
    {
        parent::init();

        $this->js = [
            'js/cookie.js?v=' . \Yii::$app->settings->get('site_version'),
            'js/clipboard.min.js?v=' . \Yii::$app->settings->get('site_version'),
            'js/design/main.js?v=' . \Yii::$app->settings->get('site_version'),
            'js/design/menu.js?v=' . \Yii::$app->settings->get('site_version'),
            'js/main.js?v=' . \Yii::$app->settings->get('site_version'),
        ];
        $this->css = [
            'css/design/styles-local.min.css?v=' . \Yii::$app->settings->get('site_version'),
        ];
    }

    public $depends = [
        AppAsset::class,
        MomentAsset::class,
    ];
}
