<?php
namespace common\assets;
use yii\web\AssetBundle;


class BootstrapIcons extends AssetBundle
{
    public $sourcePath = "@npm/bootstrap-icons";
    public $css = [
        'font/bootstrap-icons.css'
    ];
}