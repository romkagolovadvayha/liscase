<?php

use backend\models\Notification;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var Notification $model */
/** @var ActiveForm $form */

$this->title = 'Редактировать уведомление: ' . $model->title;
$this->params['breadcrumbs'][] = ['label' => 'Уведомления', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->title, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Редактировать';

?>

<div class="notifications-update">
    <div class="row">
        <div class="col-md-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        <i class="fa fa-edit"></i> Редактировать уведомление
                    </h3>
                </div>
                <div class="box-body">
                    <?php $form = ActiveForm::begin(); ?>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <?= $form->field($model, 'title')->textInput([
                                    'maxlength' => true,
                                    'placeholder' => 'Введите заголовок уведомления'
                                ])->label('Заголовок <span class="text-red">*</span>') ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <?= $form->field($model, 'type')->dropDownList(
                                    Notification::getTypeLabels(),
                                    ['prompt' => 'Выберите тип уведомления']
                                )->label('Тип уведомления <span class="text-red">*</span>') ?>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <?= $form->field($model, 'priority')->dropDownList(
                                    Notification::getPriorityLabels(),
                                    ['prompt' => 'Выберите приоритет']
                                )->label('Приоритет <span class="text-red">*</span>') ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">Получатель</label>
                                <div class="form-control" style="background-color: #f5f5f5;">
                                    <?php if ($model->user_id === null): ?>
                                        <span class="label label-primary">Всем пользователям</span>
                                    <?php else: ?>
                                        <?= Html::a(
                                            'ID: ' . $model->user_id,
                                            ['/user/view', 'id' => $model->user_id],
                                            ['title' => 'Просмотр пользователя']
                                        ) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <?= $form->field($model, 'expires_at')->textInput([
                                    'type' => 'datetime-local',
                                    'value' => $model->expires_at ? date('Y-m-d\TH:i', $model->expires_at) : '',
                                    'placeholder' => 'Дата истечения'
                                ])->label('Дата истечения') ?>
                                <small class="help-block">
                                    Оставьте пустым, если уведомление не должно истекать
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <?= $form->field($model, 'is_read')->checkbox([
                                    'label' => 'Отмечено как прочитанное'
                                ]) ?>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <?= $form->field($model, 'message')->textarea([
                                    'rows' => 8,
                                    'placeholder' => 'Введите текст уведомления (поддерживает HTML)'
                                ])->label('Сообщение <span class="text-red">*</span>') ?>
                                <small class="help-block">
                                    Поддерживается HTML разметка. Можно использовать теги: &lt;b&gt;, &lt;i&gt;, &lt;u&gt;, &lt;br&gt;, &lt;a&gt;
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <div class="btn-group" role="group">
                                    <?= Html::submitButton(
                                        '<i class="fa fa-save"></i> Сохранить изменения',
                                        ['class' => 'btn btn-success']
                                    ) ?>
                                    <?= Html::a(
                                        '<i class="fa fa-times"></i> Отмена',
                                        ['view', 'id' => $model->id],
                                        ['class' => 'btn btn-danger']
                                    ) ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>
</div>
