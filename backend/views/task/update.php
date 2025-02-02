<?php

use common\models\tasks\Task;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use common\models\box\Drop;

/** @var Task $model */
/** @var $items Task[] */
/** @var $id */
/** @var $tasks */

$data = [];
foreach ($tasks as $_id => $item) {
    $data[] = [
        'id' => $_id,
        'title' => $item,
    ];
}

$searchJS = Drop::searchJS();
?>

<?php $form = ActiveForm::begin(
    [
        'id' => 'box-form',
        'options' => ['enctype' => 'multipart/form-data']
    ]); ?>

<div class="wrap800">
    <ul class="nav nav-tabs">
        <?php foreach ($data as $item): ?>
            <li class="nav-item">
                <a href="/task/type?id=<?=$item['id']?>" class="nav-link<?php if ($item['id'] == $model->type): ?> active<?php endif; ?>"><?=$item['title']?></a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">
        <div class="row">
            <div class="col">
                <?= $form->field($model, 'description')->textInput(); ?>
            </div>
            <div class="col">
                <?= $form->field($model, 'amount')->textInput(); ?>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <?= $form->field($model, 'drop_id')->widget(\kartik\select2\Select2::class, [
                    'data'    => Drop::getDropList(),
                    'options' => [
                        'placeholder' => 'Выберите предмет...',
                        'multiple' => false,
                        'debug' => true,
                    ],
                    'showToggleAll' => true,
                    'pluginOptions' => [
                        'templateResult'       => $searchJS['templateResult'],
                        'templateSelection' => $searchJS['templateSelection'],
                        'escapeMarkup' => $searchJS['escapeMarkup'],
                        'allowClear' => true,
                        'ajax' => [
                            'url' => '/drop/search-drop',
                            'dataType' => 'json',
                            'delay' => 250,
                            'data' => $searchJS['ajaxData'],
                            'processResults' => $searchJS['processResults'],
                            'cache' => true
                        ],
                        'debug' => true,
                    ],
                ]); ?>
            </div>
            <div class="col">
                <?= $form->field($model, 'count')->textInput(); ?>
            </div>
        </div>

        <?= $form->field($model, 'stat_attribute')->textInput(); ?>

        <footer>
            <button type="submit" class="btn btn-primary">
                <span class="button__text button__dark">Сохранить</span>
            </button>
        </footer>
    </div>
</div>
<?php ActiveForm::end(); ?>

