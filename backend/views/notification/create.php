<?php

use backend\models\Notification;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;
use common\models\user\User;

/** @var yii\web\View $this */
/** @var Notification $model */
/** @var ActiveForm $form */

$this->title = 'Создать уведомление';
$this->params['breadcrumbs'][] = ['label' => 'Уведомления', 'url' => ['/notification/index']];
$this->params['breadcrumbs'][] = $this->title;

?>

<div class="notifications-create">
    <div class="row">
        <div class="col-md-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        <i class="fa fa-plus"></i> Создать новое уведомление
                    </h3>
                </div>
                <div class="box-body">
                    <?php $form = ActiveForm::begin([
                        'options' => ['enctype' => 'multipart/form-data']
                    ]); ?>

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
                                <?= $form->field($model, 'target_type')->dropDownList([
                                    'all' => 'Всем пользователям',
                                    'user' => 'Конкретному пользователю',
                                ], [
                                    'prompt' => 'Выберите получателя',
                                    'id' => 'target-type'
                                ])->label('Получатель <span class="text-red">*</span>') ?>
                            </div>
                        </div>
                    </div>

                    <div class="row" id="user-selection" hidden>
                        <div class="col-md-12">
                            <div class="form-group">
                                <?= $form->field($model, 'user_id')->dropDownList(
                                    ArrayHelper::map(
                                        User::find()->orderBy('username')->all(),
                                        'id',
                                        'username'
                                    ),
                                    ['prompt' => 'Выберите пользователя']
                                )->label('Пользователь') ?>
                            </div>
                        </div>
                    </div>

                    <div class="row">
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
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="control-label" id="notification-preview-label">Предварительный просмотр</div>
                                <div class="form-control notification-preview" id="preview" aria-labelledby="notification-preview-label">
                                    <div class="alert alert-info">
                                        <strong>Информация</strong><br>
                                        Здесь будет показан предварительный просмотр уведомления
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <?= $form->field($model, 'message')->textarea([
                                    'rows' => 6,
                                    'placeholder' => 'Введите текст уведомления (поддерживает HTML)',
                                    'id' => 'message-input'
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
                                        '<i class="fa fa-save"></i> Сохранить как черновик',
                                        [
                                            'class' => 'btn btn-default',
                                            'name' => 'save-draft',
                                            'value' => '1'
                                        ]
                                    ) ?>
                                    <?= Html::submitButton(
                                        '<i class="fa fa-send"></i> Отправить уведомление',
                                        [
                                            'class' => 'btn btn-success',
                                            'name' => 'send-notification',
                                            'value' => '1'
                                        ]
                                    ) ?>
                                    <?= Html::a(
                                        '<i class="fa fa-times"></i> Отмена',
                                        ['/notification/index'],
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const targetTypeSelect = document.getElementById('target-type');
    const userSelection = document.getElementById('user-selection');
    const messageInput = document.getElementById('message-input');
    const preview = document.getElementById('preview');
    const typeSelect = document.querySelector('#notification-type');
    const prioritySelect = document.querySelector('#notification-priority');

    // Показ/скрытие выбора пользователя
    targetTypeSelect.addEventListener('change', function() {
        userSelection.hidden = this.value !== 'user';
    });

    // Обновление предварительного просмотра
    function updatePreview() {
        const message = messageInput.value;
        const type = typeSelect ? typeSelect.value : 'info';
        const priority = prioritySelect ? prioritySelect.value : '3';
        
        let alertClass = 'alert-info';
        let priorityText = 'Обычный';
        
        switch(type) {
            case 'success':
                alertClass = 'alert-success';
                break;
            case 'warning':
                alertClass = 'alert-warning';
                break;
            case 'error':
                alertClass = 'alert-danger';
                break;
            case 'support':
                alertClass = 'alert-primary';
                break;
            case 'announcement':
                alertClass = 'alert-info';
                break;
        }
        
        switch(priority) {
            case '1':
                priorityText = 'Критический';
                break;
            case '2':
                priorityText = 'Высокий';
                break;
            case '3':
                priorityText = 'Обычный';
                break;
            case '4':
                priorityText = 'Низкий';
                break;
        }
        
        preview.innerHTML = `
            <div class="alert ${alertClass}">
                <strong>Заголовок уведомления</strong>
                <span class="badge badge-secondary pull-right">${priorityText}</span>
                <br><br>
                ${message || 'Введите текст сообщения для предварительного просмотра'}
            </div>
        `;
    }

    // Обработчики событий
    messageInput.addEventListener('input', updatePreview);
    if (typeSelect) typeSelect.addEventListener('change', updatePreview);
    if (prioritySelect) prioritySelect.addEventListener('change', updatePreview);
    
    // Первоначальный просмотр
    updatePreview();
});
</script>

<style>
.badge {
    font-size: 10px;
    padding: 2px 6px;
}
.badge-secondary {
    background-color: #6c757d;
    color: white;
}
.pull-right {
    float: right;
}
</style>
