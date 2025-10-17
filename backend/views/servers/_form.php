<?php

use common\models\servers\ServersTags;
use kartik\select2\Select2;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var common\models\servers\Servers $model */
/** @var yii\widgets\ActiveForm $form */
/** @var array $selectedTags */

$selectedTags = $model->isNewRecord ? [] : $model->getTagIds();
?>

<div class="servers-form">

    <?php $form = ActiveForm::begin(); ?>

    <div class="row">
        <div class="col-md-3">
            <?= $form->field($model, 'name')->textInput() ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($model, 'monitoring_name')->textInput() ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($model, 'monitoring_description')->textInput() ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($model, 'rust_app_id')->textInput() ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3">
            <?= $form->field($model, 'wipe')->textInput() ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($model, 'next_wipe')->textInput() ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($model, 'global_wipe')->textInput() ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($model, 'wipe_type')->textInput() ?>
        </div>
    </div>
    <div class="row">
        <div class="col-md-2">
            <?= $form->field($model, 'min_map_size')->textInput() ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($model, 'max_map_size')->textInput() ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($model, 'team_limit')->textInput() ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($model, 'max')->textInput() ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($model, 'tag')->textInput() ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($model, 'wargm_id')->textInput() ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3">
            <?= $form->field($model, 'ip')->textInput() ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($model, 'port')->textInput() ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($model, 'query')->textInput() ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($model, 'rcon')->textInput() ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($model, 'rcon_password')->textInput(['maxlength' => true]) ?>
        </div>
    </div>

    <?= $form->field($model, 'status')->dropDownList(\common\models\servers\Servers::getStatusList(), []) ?>
    <?= $form->field($model, 'skindrops')->dropDownList([
                                                            0       => Yii::t('common', 'Нет'),
                                                            1      => Yii::t('common', 'Да'),
                                                        ], []) ?>
    <?= $form->field($model, 'is_store')->dropDownList([
                                                            0       => Yii::t('common', 'Нет'),
                                                            1      => Yii::t('common', 'Да'),
                                                        ], []) ?>
    <?= $form->field($model, 'secret_map')->dropDownList([
                                                            0       => Yii::t('common', 'Нет'),
                                                            1      => Yii::t('common', 'Да'),
                                                        ], []) ?>

    <?= $form->field($model, 'commands')->textInput() ?>

    <?= $form->field($model, 'secret_key')->textInput() ?>
    <?= $form->field($model, 'discord_token')->textInput() ?>
    <?= $form->field($model, 'sort')->textInput() ?>


    <?= $form->field($model, 'description')->textarea(['rows' => 6]) ?>
    <?= $form->field($model, 'rules')->textarea(['rows' => 6]) ?>
    
    <div class="form-group">
        <label><?= Yii::t('common', 'Теги сервера') ?></label>
        <?= Select2::widget([
            'name' => 'server_tags',
            'value' => $selectedTags,
            'data' => ServersTags::getTagsList(),
            'options' => [
                'placeholder' => Yii::t('common', 'Выберите теги...'),
                'multiple' => true,
            ],
            'pluginOptions' => [
                'allowClear' => true,
                'tags' => false,
            ],
        ]); ?>
        <p class="help-block"><?= Yii::t('common', 'Можно выбрать несколько тегов') ?></p>
    </div>

    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
