<?php
namespace common\assets;
use yii\web\AssetBundle;


class SlickCarouselAsset extends AssetBundle
{
    public $sourcePath = "@bower/slick-carousel";
    public $css = [
        'slick/slick.css'
    ];
    public $js = [
        'slick/slick.min.js'
    ];
    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap5\BootstrapAsset'
    ];
}