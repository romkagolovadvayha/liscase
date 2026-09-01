<?php

use common\models\avatar\AvatarFrame;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var AvatarFrame $model */
?>

<div class="p-4 max-w-[720px]">
    <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

    <?= $form->field($model, 'is_active')->dropDownList([1 => 'Да', 0 => 'Нет']) ?>

    <div class="form-group mb-3">
        <label class="control-label" for="avatar-frame-file">Файл рамки (PNG/WebP)</label>
        <input id="avatar-frame-file" type="file" name="frame_file" class="form-control" accept=".png,.webp,image/png,image/webp">
        <div class="help-block text-muted small mt-1">Рекомендуется квадрат 140x140 или больше, прозрачный фон.</div>
    </div>

    <?php if (!$model->isNewRecord && $model->getImageUrl()): ?>
        <div class="mb-3">
            <div class="small text-muted mb-2">Текущая рамка:</div>
            <img src="<?= Html::encode($model->getImageUrl()) ?>" alt="Текущая рамка аватара" class="avatar-frame-preview" width="84" height="84">
        </div>
    <?php endif; ?>

    <div class="form-group mt-4">
        <?= Html::submitButton($model->isNewRecord ? 'Создать' : 'Сохранить', ['class' => 'ds-btn ds-btn--primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>

