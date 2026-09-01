<?php

namespace backend\assets;

use yii\web\AssetBundle;

/**
 * Tailwind CSS via official Play CDN (no local file = no 404).
 * npm-asset/tailwindcss in composer only provides build tools, not a ready full CSS.
 */
class TailwindAsset extends AssetBundle
{
    public $sourcePath = '@backend/assets/sources';

    public $css = [
        'scss/tailwind.min.css',
    ];
}
