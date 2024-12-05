<?php

use common\models\box\Drop;
use kartik\grid\GridView;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

/** @var $items \common\models\servers\Servers[] */

$this->title = Yii::t('common', 'Сортировка');

$elements = [];
foreach ($items as $item) {
    $html = $this->render('sort_item', [
        'item' => $item
    ]);
    $elements[] = [
        'content' => $html
    ];
}
?>

<style>
    .item_sort {
        background-size: cover;
        background-repeat: no-repeat;
        display: block;
        height: 60px;
        width: 60px;
    }
</style>


<?php $form = ActiveForm::begin(['id' => 'sort-form',]); ?>
<?=\kartik\sortable\Sortable::widget(['type'=>'grid', 'items'=> $elements])?>
<div class="form-group">
    <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
</div>
<?php ActiveForm::end(); ?>

