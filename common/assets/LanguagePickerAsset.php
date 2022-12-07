<?php

namespace common\assets;
use yii\web\AssetBundle;

class LanguagePickerAsset extends AssetBundle
{
    public $sourcePath = '@common/assets/sources';

    public $js
        = [
            'js/language-picker.js',
        ];

    public $depends
        = [
            'common\assets\TemplateAsset',
        ];
}
