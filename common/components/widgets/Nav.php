<?php

namespace common\components\widgets;

use Yii;
use yii\bootstrap5\Html;

class Nav extends \yii\bootstrap5\Nav
{
    public $encodeLabels = false;

    public $options
        = [
            'class' => 'nav nav-tabs',
            'role'  => 'tablist',
        ];

//    public function run()
//    {
//        return Html::tag('nav', parent::run(),['class' => 'tab_navigation']);
//    }
}