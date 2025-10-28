<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var common\models\radio\RadioTrack $model */
/** @var common\models\radio\RadioStation $station */
/** @var yii\widgets\ActiveForm $form */

$this->title = Yii::t('common', 'Загрузить трек');
?>

<div class="radio-track-create">
    <h3><?= Html::encode($this->title) ?></h3>
    <p class="help-text">
        <?= Yii::t('common', 'Радиостанция:') ?> <strong><?= Html::encode($station->name) ?></strong>
    </p>
    <p class="help-text">
        <?= Yii::t('common', 'Трек будет отправлен на модерацию. После одобрения он появится в списке и будет доступен для прослушивания.') ?>
    </p>

    <div class="radio-track-form">
        <?php $form = ActiveForm::begin([
            'id' => 'radio-track-form',
            'options' => ['enctype' => 'multipart/form-data'],
        ]); ?>

        <?= $form->field($model, 'title')->textInput([
            'maxlength' => true,
            'placeholder' => Yii::t('common', 'Например: Never Gonna Give You Up'),
        ]) ?>

        <?= $form->field($model, 'artist')->textInput([
            'maxlength' => true,
            'placeholder' => Yii::t('common', 'Например: Rick Astley'),
        ]) ?>

        <?= $form->field($model, 'audioFile')->fileInput([
            'accept' => 'audio/mpeg,.mp3',
        ])->hint(Yii::t('common', 'Максимальный размер файла: 20 МБ. Поддерживается только MP3.')) ?>

        <div class="form-group">
            <?= Html::submitButton(
                '<i class="fa fa-upload"></i> ' . Yii::t('common', 'Загрузить трек'),
                ['class' => 'btn btn-success btn-block']
            ) ?>
        </div>

        <?php ActiveForm::end(); ?>
    </div>

    <div class="upload-rules">
        <h4><?= Yii::t('common', 'Правила загрузки треков:') ?></h4>
        <ul>
            <li><?= Yii::t('common', 'Формат файла: только MP3') ?></li>
            <li><?= Yii::t('common', 'Максимальный размер: 20 МБ') ?></li>
            <li><?= Yii::t('common', 'Запрещены треки с нецензурной лексикой') ?></li>
            <li><?= Yii::t('common', 'Запрещена пропаганда насилия и экстремизма') ?></li>
            <li><?= Yii::t('common', 'Треки проверяются модераторами в течение 24 часов') ?></li>
        </ul>
    </div>
</div>

<style>
.radio-track-create {
    padding: 20px;
}

.help-text {
    color: var(--text-secondary);
    font-size: 14px;
    margin-bottom: 8px;
}

.radio-track-form {
    margin: 20px 0;
}

.upload-rules {
    margin-top: 30px;
    padding: 16px;
    background: var(--background-teritiary);
    border-radius: 8px;
}

.upload-rules h4 {
    margin-top: 0;
    margin-bottom: 12px;
    font-size: 16px;
}

.upload-rules ul {
    margin: 0;
    padding-left: 20px;
}

.upload-rules li {
    margin-bottom: 8px;
    font-size: 14px;
    color: var(--text-secondary);
}

.btn-block {
    width: 100%;
    padding: 12px;
    font-size: 16px;
    font-weight: 600;
    border-radius: 8px;
}
</style>

