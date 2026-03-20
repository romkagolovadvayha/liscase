<?php

use backend\models\Notification;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var Notification $model */
/** @var ActiveForm $form */

$this->title = 'Отправить уведомление всем пользователям';
$this->params['breadcrumbs'][] = ['label' => 'Уведомления', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

?>

<div class="notifications-send-to-all">
    <div class="row">
        <div class="col-md-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        <i class="fa fa-send"></i> Быстрая отправка всем пользователям
                    </h3>
                </div>
                <div class="box-body">
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i>
                        <strong>Внимание!</strong> Это уведомление будет отправлено всем активным пользователям (были на сервере в течение последних 6 месяцев).
                    </div>

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
                                <?= $form->field($model, 'expires_at')->textInput([
                                    'type' => 'datetime-local',
                                    'placeholder' => 'Дата истечения'
                                ])->label('Дата истечения') ?>
                                <small class="help-block">
                                    Оставьте пустым, если уведомление не должно истекать
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <?= $form->field($model, 'message')->textarea([
                                    'rows' => 6,
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
                                        '<i class="fa fa-send"></i> Отправить всем пользователям',
                                        [
                                            'class' => 'btn btn-success btn-lg',
                                            'data' => [
                                                'confirm' => 'Вы уверены, что хотите отправить это уведомление всем активным пользователям?'
                                            ]
                                        ]
                                    ) ?>
                                    <?= Html::a(
                                        '<i class="fa fa-times"></i> Отмена',
                                        ['index'],
                                        ['class' => 'btn btn-danger btn-lg']
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


























































