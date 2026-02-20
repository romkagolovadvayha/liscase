<?php

use common\models\tasks\Task;
use kartik\sortable\Sortable;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var Task[] $items */

$this->title = Yii::t('common', 'Сортировка');

$elements = [];
foreach ($items as $item) {
    $elements[] = [
        'content' => $this->render('sort_item', ['item' => $item]),
    ];
}
?>

<div class="task-sort-page admin-sort-page p-4 lg:p-6 w-full">
    <?php $form = ActiveForm::begin(['id' => 'sort-form']); ?>
    <div class="admin-sort-grid-wrapper">
        <?= Sortable::widget([
            'type' => Sortable::TYPE_GRID,
            'items' => $elements,
            'options' => ['id' => 'task-sortable', 'class' => 'admin-sortable-grid'],
            'itemOptions' => ['class' => 'admin-sort-item'],
        ]) ?>
    </div>
    <div class="mt-4">
        <?= Html::submitButton(Yii::t('common', 'Сохранить'), ['class' => 'ds-btn ds-btn--primary']) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>
