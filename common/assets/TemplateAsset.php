<?php

namespace common\assets;
use yii\web\AssetBundle;

class TemplateAsset extends AssetBundle
{
    public $depends
        = [
            'yii\jui\JuiAsset',
            'yii\bootstrap5\BootstrapPluginAsset',
        ];
}
