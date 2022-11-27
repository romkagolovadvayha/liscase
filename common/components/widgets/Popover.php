<?php

namespace common\components\widgets;

use Yii;
use yii\bootstrap5\Html;

class Popover extends \yii\bootstrap5\Widget
{
    public $content;
    public $cssClass = 'info-popover-icon';

    public function init()
    {
        parent::init();

        echo Html::tag('span', '', [
            'id'             => 'popover_' . Yii::$app->security->generateRandomString(3),
            'class'          => $this->cssClass,
            'data-placement' => 'left',
            'data-html'      => 'true',
            'data-toggle'    => 'popover',
            'data-content'   => $this->content,
        ]);
    }
}