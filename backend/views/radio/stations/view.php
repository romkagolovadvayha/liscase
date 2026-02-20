<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model \common\models\radio\RadioStation */

$this->title = $model->isNewRecord ? Yii::t('common', 'Создать радиостанцию') : Yii::t('common', 'Редактировать радиостанцию');
$this->params['breadcrumbs'][] = ['label' => Yii::t('common', 'Радио'), 'url' => ['radio/index']];
$this->params['breadcrumbs'][] = ['label' => Yii::t('common', 'Радиостанции'), 'url' => ['radio/stations']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="radio-station-view">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row">
        <div class="col-md-8">
            <?php $form = ActiveForm::begin(); ?>

            <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>

            <?= $form->field($model, 'description')->textarea(['rows' => 3]) ?>

            <?= $form->field($model, 'port')->textInput(['type' => 'number']) ?>

            <?= $form->field($model, 'folder_name')->textInput(['maxlength' => true]) ?>

            <?= $form->field($model, 'stream_url')->textInput(['maxlength' => true])
                ->hint(Yii::t('common', 'URL для потока (например, http://example.com:8081). Если не указан, будет использован localhost')) ?>

            <div class="ds-select-wrapper">
                <?= $form->field($model, 'status', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->dropDownList(\common\models\radio\RadioStation::getStatusList(), ['class' => 'ds-select form-control']) ?>
                <i class="fas fa-chevron-down ds-select-arrow"></i>
            </div>

            <div class="form-group">
                <?= Html::submitButton(
                    Yii::t('common', 'Сохранить'),
                    ['class' => 'btn btn-success']
                ) ?>
                <?= Html::a(
                    Yii::t('common', 'Отмена'),
                    ['radio/stations'],
                    ['class' => 'btn btn-default']
                ) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
        
        <div class="col-md-4">
            <div class="well">
                <h4><?= Yii::t('common', 'Информация') ?></h4>
                
                <?php if (!$model->isNewRecord): ?>
                    <p><strong><?= Yii::t('common', 'ID') ?>:</strong> <?= $model->id ?></p>
                    <p><strong><?= Yii::t('common', 'Треков') ?>:</strong> <?= $model->getRadioTracks()->count() ?></p>
                    <p><strong><?= Yii::t('common', 'Запущена') ?>:</strong> 
                        <?php if ($model->is_running): ?>
                            <span class="label label-success"><?= Yii::t('common', 'Да') ?></span>
                        <?php else: ?>
                            <span class="label label-default"><?= Yii::t('common', 'Нет') ?></span>
                        <?php endif; ?>
                    </p>
                    <p><strong><?= Yii::t('common', 'Создана') ?>:</strong> <?= $model->created_at ?></p>
                    <p><strong><?= Yii::t('common', 'Обновлена') ?>:</strong> <?= $model->updated_at ?></p>
                <?php endif; ?>
                
                <hr>
                
                <h5><?= Yii::t('common', 'Использование') ?></h5>
                <p><?= Yii::t('common', 'После создания радиостанции запустите Node.js сервер на указанном порту.') ?></p>
                <p><?= Yii::t('common', 'Файлы будут сохраняться в:') ?></p>
                <code>frontend/web/uploads/radio/<?= $model->isNewRecord ? '{id}' : $model->id ?></code>
            </div>
        </div>
    </div>
</div>

