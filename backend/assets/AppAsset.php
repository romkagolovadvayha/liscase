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
        'scss/main.min.css?v=1.4',
    ];

    public $js = [
        'https://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js',
        'js/backend.js?v=1.4',
    ];

    public $depends = [
        BootstrapAsset::class,
        FontAwesomeAsset::class,
    ];
}
