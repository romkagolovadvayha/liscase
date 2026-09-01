<?php

use backend\models\TelegramRecipientsSearch;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var TelegramRecipientsSearch $searchModel */
?>
<div class="admin-filters-content">
    <?php $form = ActiveForm::begin(['action' => ['index'], 'method' => 'get', 'options' => ['class' => 'admin-filter-form']]) ?>
        <div class="admin-filter-form__body">
            <?= $form->field($searchModel, 'name')->textInput(['class' => 'ds-input form-control', 'placeholder' => 'Название содержит…'])->label('Название') ?>
            <?= $form->field($searchModel, 'created_at')->textInput(['class' => 'ds-input form-control', 'placeholder' => 'Дата'])->label('Дата создания') ?>
        </div>
        <div class="admin-filter-form__footer">
            <button type="submit" class="ds-btn ds-btn--primary"><i class="fa-solid fa-filter" aria-hidden="true"></i> Применить</button>
            <a href="<?= Url::to(['index']) ?>" class="ds-btn ds-btn--secondary">Сбросить</a>
        </div>
    <?php ActiveForm::end() ?>
</div>
