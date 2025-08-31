<?php
namespace frontend\assets;

use yii\web\AssetBundle;

class AnimateCssAsset extends AssetBundle
{
    // выбери ОДНО из двух:
    public $sourcePath = '@npm/animate.css';   // если ставил npm-asset/animate.css
    // public $sourcePath = '@bower/animate.css'; // если ставил bower-asset/animate.css

    public $css = [
        'animate.min.css',
    ];
    public $publishOptions = [
        'only' => ['animate.min.css'], // публикуем только нужный файл
    ];
}
