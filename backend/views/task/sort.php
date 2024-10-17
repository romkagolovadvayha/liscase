<?php

use common\models\box\Drop;
use kartik\grid\GridView;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use common\models\tasks\Task;
use yii\helpers\ArrayHelper;

/** @var $items Task[] */

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

<?php $form = ActiveForm::begin(['id' => 'sort-form',]); ?>
<?=\kartik\sortable\Sortable::widget(['type'=>'grid', 'items'=> $elements])?>
<?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
<?php ActiveForm::end(); ?>

