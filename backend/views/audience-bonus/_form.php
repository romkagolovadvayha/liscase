<?php

use yii\helpers\Html;
use yii\helpers\Url;
use common\models\bonus\AudienceBonus;

/** @var yii\web\View $this */

$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->csrfToken;
?>
<div class="audience-bonus-form">
    <form id="audience-bonus-form">
        <input type="hidden" name="<?= $csrfParam ?>" value="<?= $csrfToken ?>">

        <div class="form-group">
            <label class="control-label">Тип аудитории <span style="color: red;">*</span></label>
            <div>
                <label style="margin-right: 20px;">
                    <input type="radio" name="audience_type" value="<?= AudienceBonus::AUDIENCE_TYPE_DEPOSITS ?>" required>
                    Депозиты (все кто пополнял баланс от указанной суммы)
                </label>
                <label>
                    <input type="radio" name="audience_type" value="<?= AudienceBonus::AUDIENCE_TYPE_WIPES ?>" required>
                    Вайпы (все кто отыграл больше указанного количества вайпов)
                </label>
            </div>
        </div>

        <div id="deposits-params" style="display: none;">
            <h3>Параметры для депозитов</h3>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Минимальная сумма депозита (по умолчанию: 5000)</label>
                        <input type="number" name="parameters[deposit_min]" class="form-control ds-input" step="0.01" min="0">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Процент бонуса (по умолчанию: 3%)</label>
                        <input type="number" name="parameters[deposit_percent]" class="form-control ds-input" step="0.01" min="0" max="100">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Минимальный бонус (по умолчанию: 500 руб)</label>
                        <input type="number" name="parameters[deposit_min_bonus]" class="form-control ds-input" step="0.01" min="0">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Округление до (по умолчанию: 100 руб)</label>
                        <input type="number" name="parameters[deposit_round]" class="form-control ds-input" step="0.01" min="1">
                    </div>
                </div>
            </div>
        </div>

        <div id="wipes-params" style="display: none;">
            <h3>Параметры для вайпов</h3>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Количество вайпов (по умолчанию: 40)</label>
                        <input type="number" name="parameters[wipes_count]" class="form-control ds-input" step="1" min="1">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Фиксированный бонус (по умолчанию: 500 руб)</label>
                        <input type="number" name="parameters[wipes_bonus]" class="form-control ds-input" step="0.01" min="0">
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label class="control-label">Тестовая аудитория (ID пользователей через запятую, например: 1, 5, 10)</label>
            <input type="text" name="test_user_ids" class="form-control ds-input" placeholder="Оставьте пустым для всех пользователей">
            <small class="help-block">Если указано, начисление произойдет только для указанных пользователей (при условии соответствия критериям)</small>
        </div>

        <div class="form-group">
            <label class="control-label">Шаблон сообщения для ТГ бота</label>
            <textarea name="message_template" class="form-control ds-input" rows="5" placeholder="Опционально. Доступные переменные: {username}, {amount}, {total_deposit}, {wipes_count}"></textarea>
            <small class="help-block">
                Доступные переменные:<br>
                <code>{username}</code> - имя пользователя<br>
                <code>{amount}</code> - сумма бонуса<br>
                <code>{total_deposit}</code> - общая сумма депозитов (только для типа "Депозиты")<br>
                <code>{wipes_count}</code> - количество вайпов (только для типа "Вайпы")
            </small>
        </div>

        <div class="form-group">
            <button type="button" id="preview-btn" class="ds-btn ds-btn--info">
                <i class="fas fa-eye"></i> Предпросмотр
            </button>
            <button type="button" id="apply-btn" class="ds-btn ds-btn--success" style="margin-left: 10px;">
                <i class="fas fa-check"></i> Применить начисление
            </button>
        </div>

        <div id="preview-container" style="display: none; margin-top: 20px;">
            <div class="ds-card">
                <h3>Предпросмотр</h3>
                <div id="preview-content"></div>
            </div>
        </div>
    </form>
</div>

<?php
$previewUrl = Url::to(['preview']);
$applyUrl = Url::to(['apply']);
$successUrl = Url::to(['index']);

$this->registerJs(<<<JS
$(document).ready(function() {
    // Показываем/скрываем параметры в зависимости от выбранного типа
    $('input[name="audience_type"]').on('change', function() {
        var type = $(this).val();
        if (type == '1') {
            $('#deposits-params').show();
            $('#wipes-params').hide();
        } else if (type == '2') {
            $('#deposits-params').hide();
            $('#wipes-params').show();
        }
    });

    // Предпросмотр
    $('#preview-btn').on('click', function() {
        var formData = new FormData($('#audience-bonus-form')[0]);
        
        $.ajax({
            url: '{$previewUrl}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var html = '<p><strong>Количество пользователей:</strong> ' + data.total_users + '</p>';
                    html += '<p><strong>Общая сумма начисления:</strong> ' + data.total_amount.toFixed(2) + ' РУБ</p>';
                    
                    if (data.is_test_mode) {
                        html += '<p><span class="ds-badge ds-badge--warning">Тестовый режим</span></p>';
                    }
                    
                    if (data.users && data.users.length > 0) {
                        html += '<h4>Первые ' + Math.min(data.users.length, 50) + ' пользователей:</h4>';
                        html += '<table class="table table-striped"><thead><tr><th>ID</th><th>Username</th><th>Сумма бонуса</th></tr></thead><tbody>';
                        for (var i = 0; i < Math.min(data.users.length, 50); i++) {
                            var user = data.users[i];
                            html += '<tr><td>' + user.id + '</td><td>' + user.username + '</td><td>' + user.bonus_amount.toFixed(2) + ' РУБ</td></tr>';
                        }
                        html += '</tbody></table>';
                        if (data.total_users > 50) {
                            html += '<p><small>Показано 50 из ' + data.total_users + ' пользователей</small></p>';
                        }
                    }
                    
                    if (data.preview_message) {
                        html += '<h4>Предпросмотр сообщения:</h4>';
                        html += '<div style="background: #f5f5f5; padding: 10px; border-radius: 4px; white-space: pre-wrap;">' + data.preview_message + '</div>';
                    }
                    
                    $('#preview-content').html(html);
                    $('#preview-container').show();
                } else {
                    alert('Ошибка: ' + (response.message || 'Неизвестная ошибка'));
                }
            },
            error: function(xhr) {
                var errorMsg = 'Ошибка при выполнении запроса';
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMsg = response.message;
                    }
                } catch(e) {}
                alert(errorMsg);
            }
        });
    });

    // Применение начисления
    $('#apply-btn').on('click', function() {
        if (!confirm('Вы уверены, что хотите применить начисление? Это действие нельзя отменить.')) {
            return;
        }
        
        var formData = new FormData($('#audience-bonus-form')[0]);
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Применение...');
        
        $.ajax({
            url: '{$applyUrl}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.message || 'Начисление успешно применено');
                    window.location.href = '{$successUrl}';
                } else {
                    alert('Ошибка: ' + (response.message || 'Неизвестная ошибка'));
                    btn.prop('disabled', false).html('<i class="fas fa-check"></i> Применить начисление');
                }
            },
            error: function(xhr) {
                var errorMsg = 'Ошибка при выполнении запроса';
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMsg = response.message;
                    }
                } catch(e) {}
                alert(errorMsg);
                btn.prop('disabled', false).html('<i class="fas fa-check"></i> Применить начисление');
            }
        });
    });
});
JS
    , \yii\web\View::POS_READY
);
?>

