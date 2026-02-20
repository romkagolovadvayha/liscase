<?php

use common\models\box\Drop;
use kartik\sortable\Sortable;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var Drop[] $items */

$this->title = Yii::t('common', 'Сортировка');

$elements = [];
foreach ($items as $item) {
    $elements[] = [
        'content' => $this->render('sort_item', ['item' => $item]),
    ];
}
?>

<div class="drop-sort-page p-4 lg:p-6 w-full">
    <?php $form = ActiveForm::begin(['id' => 'sort-form']); ?>
    <div class="drop-sort-grid-wrapper">
        <?= Sortable::widget([
            'type' => Sortable::TYPE_GRID,
            'items' => $elements,
            'options' => ['id' => 'drop-sortable', 'class' => 'drop-sortable-grid'],
            'itemOptions' => ['class' => 'drop-sort-item'],
        ]) ?>
    </div>
    <div class="mt-4">
        <?= Html::submitButton(Yii::t('common', 'Сохранить'), ['class' => 'ds-btn ds-btn--primary']) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>
