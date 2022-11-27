<?php

namespace common\components\widgets;

use Yii;
use yii\base\Widget;
use yii\bootstrap5\Modal;
use yii\helpers\Html;

class ModalWidget extends Widget
{
    public $modalId = 'modal-dialog';
    public $isFooter = true;
    public $isHeader = true;

    public function run()
    {
        $footer = null;
        if ($this->isFooter) {
            $footer = Html::button(Yii::t('common', 'Закрыть'), [
                'type'         => 'button',
                'class'        => 'btn btn-default waves-effect text-left',
                'data-dismiss' => 'modal',
            ]);
        }
        Modal::begin([
            'id'            => $this->modalId,
            'clientOptions' => ['backdrop' => 'static', 'keyboard' => false],
            'footer'        => $footer,
            'options'       => [
                'class' => 'fade bs-modal-block',
            ],
        ]);
        Modal::end();
    }
}
