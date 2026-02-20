<?php

namespace backend\assets;

use yii\web\AssetBundle;
use yii\web\JqueryAsset;

/**
 * Main backend application asset bundle.
 */
class AppAsset extends AssetBundle
{
    public $sourcePath = '@backend/assets/sources';

    public $css = [
        'scss/main.min.css?v=2.0',
    ];

    public $js = [
        'https://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js',
        'js/backend.js?v=2.0',
    ];

    public $depends = [
        BootstrapAsset::class,
        FontAwesomeAsset::class,
        TailwindAsset::class,
    ];
}
