<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class PromotionAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public $css = [];

    public $js
        = [
            'js/pages/tasks.js?v=1.0.1',
        ];

    public $depends
        = [
            'common\assets\TemplateAsset',
        ];
}
