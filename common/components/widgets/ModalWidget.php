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
                'class'        => 'ds-btn ds-btn--secondary',
                'data'         => ['bs-dismiss' => 'modal'],
            ]);
        }
        $titleId = $this->modalId . '-title';
        Modal::begin([
            'id'            => $this->modalId,
            'title'         => Yii::t('common', 'Диалог'),
            'titleOptions'  => [
                'id' => $titleId,
                'class' => 'modal-title modal-title-js',
            ],
            'closeButton'   => [
                'aria' => ['label' => Yii::t('common', 'Закрыть окно')],
            ],
            'clientOptions' => false,
            'footer'        => $footer,
            'bodyOptions'   => [
                'class' => 'modal-body modal-body-js',
            ],
            'options'       => [
                'class' => 'fade bs-modal-block',
                'role' => 'dialog',
                'aria' => [
                    'modal' => 'true',
                    'labelledby' => $titleId,
                ],
                'data' => [
                    'bs-backdrop' => 'static',
                    'bs-keyboard' => 'false',
                ],
            ],
        ]);
        Modal::end();
    }
}
