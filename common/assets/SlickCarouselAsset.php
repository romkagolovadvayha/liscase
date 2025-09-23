<?php
namespace common\assets;
use yii\web\AssetBundle;


class SlickCarouselAsset extends AssetBundle
{
    public $sourcePath = "@bower/slick-carousel";
    public $css = [
        ['css/slick.css', 'rel' => 'preload', 'as' => 'style', 'onload' => "this.onload=null;this.rel='stylesheet'"],
    ];

    public $jsOptions = [
        'defer' => 'defer',
    ];

    public $cssOptions = [
        'position' => \yii\web\View::POS_END,
    ];
    public $js = [
        'slick/slick.min.js'
    ];
    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap5\BootstrapAsset'
    ];
}