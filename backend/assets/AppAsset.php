<?php

namespace backend\assets;

use yii\web\AssetBundle;

/**
 * Main backend application asset bundle.
 */
class AppAsset extends AssetBundle
{
    public $sourcePath = '@backend/assets/sources';

    public $css = [
        'scss/main.min.css',
    ];

    public $js = [
        'https://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js',
        'js/backend.js',
    ];
}
