<?php

namespace backend\assets;

use yii\web\AssetBundle;

/**
 * Tailwind CSS via official Play CDN (no local file = no 404).
 * npm-asset/tailwindcss in composer only provides build tools, not a ready full CSS.
 */
class TailwindAsset extends AssetBundle
{
    public $sourcePath = null;
    public $baseUrl = null;

    public $js = [
        'https://cdn.tailwindcss.com',
    ];
    public $jsOptions = [
        'position' => \yii\web\View::POS_HEAD,
    ];

    public $css = [];
}
