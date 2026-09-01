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

        <fieldset class="form-group audience-type-fieldset">
            <legend class="control-label">Тип аудитории <span class="required" aria-hidden="true">*</span></legend>
            <div class="audience-type-options">
                <label>
                    <input type="radio" name="audience_type" value="<?= AudienceBonus::AUDIENCE_TYPE_DEPOSITS ?>" required>
                    Депозиты (все кто пополнял баланс от указанной суммы)
                </label>
                <label>
                    <input type="radio" name="audience_type" value="<?= AudienceBonus::AUDIENCE_TYPE_WIPES ?>" required>
                    Вайпы (все кто отыграл больше указанного количества вайпов)
                </label>
            </div>
        </fieldset>

        <div id="deposits-params" hidden>
            <h3>Параметры для депозитов</h3>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label" for="audience-deposit-min">Минимальная сумма депозита (по умолчанию: 5000)</label>
                        <input id="audience-deposit-min" type="number" name="parameters[deposit_min]" class="form-control ds-input" step="0.01" min="0">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label" for="audience-deposit-percent">Процент бонуса (по умолчанию: 3%)</label>
                        <input id="audience-deposit-percent" type="number" name="parameters[deposit_percent]" class="form-control ds-input" step="0.01" min="0" max="100">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label" for="audience-deposit-min-bonus">Минимальный бонус (по умолчанию: 500 руб)</label>
                        <input id="audience-deposit-min-bonus" type="number" name="parameters[deposit_min_bonus]" class="form-control ds-input" step="0.01" min="0">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label" for="audience-deposit-round">Округление до (по умолчанию: 100 руб)</label>
                        <input id="audience-deposit-round" type="number" name="parameters[deposit_round]" class="form-control ds-input" step="0.01" min="1">
                    </div>
                </div>
            </div>
        </div>

        <div id="wipes-params" hidden>
            <h3>Параметры для вайпов</h3>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label" for="audience-wipes-count">Количество вайпов (по умолчанию: 40)</label>
                        <input id="audience-wipes-count" type="number" name="parameters[wipes_count]" class="form-control ds-input" step="1" min="1">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label" for="audience-wipes-bonus">Фиксированный бонус (по умолчанию: 500 руб)</label>
                        <input id="audience-wipes-bonus" type="number" name="parameters[wipes_bonus]" class="form-control ds-input" step="0.01" min="0">
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label class="control-label" for="audience-test-users">Тестовая аудитория (ID пользователей через запятую, например: 1, 5, 10)</label>
            <input id="audience-test-users" type="text" name="test_user_ids" class="form-control ds-input" placeholder="Оставьте пустым для всех пользователей">
            <small class="help-block">Если указано, начисление произойдет только для указанных пользователей (при условии соответствия критериям)</small>
        </div>

        <div class="form-group">
            <label class="control-label" for="audience-message-template">Шаблон сообщения для ТГ-бота</label>
            <textarea id="audience-message-template" name="message_template" class="form-control ds-input" rows="5" placeholder="Опционально. Доступные переменные: {username}, {amount}, {total_deposit}, {wipes_count}"></textarea>
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
                <i class="fas fa-eye" aria-hidden="true"></i> Предпросмотр
            </button>
            <button type="button" id="apply-btn" class="ds-btn ds-btn--success audience-apply-btn">
                <i class="fas fa-check" aria-hidden="true"></i> Применить начисление
            </button>
        </div>

        <div id="preview-container" class="audience-preview" hidden>
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
            $('#deposits-params').prop('hidden', false);
            $('#wipes-params').prop('hidden', true);
        } else if (type == '2') {
            $('#deposits-params').prop('hidden', true);
            $('#wipes-params').prop('hidden', false);
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
                    var content = $('#preview-content').empty();
                    var totalUsers = Number(data.total_users) || 0;
                    var totalAmount = Number(data.total_amount) || 0;
                    content.append($('<p>').append($('<strong>').text('Количество пользователей: '), document.createTextNode(String(totalUsers))));
                    content.append($('<p>').append($('<strong>').text('Общая сумма начисления: '), document.createTextNode(totalAmount.toFixed(2) + ' РУБ')));
                    
                    if (data.is_test_mode) {
                        content.append($('<p>').append($('<span>', {class: 'ds-badge ds-badge--warning', text: 'Тестовый режим'})));
                    }
                    
                    if (data.users && data.users.length > 0) {
                        var visibleUsers = Math.min(data.users.length, 50);
                        content.append($('<h4>').text('Первые ' + visibleUsers + ' пользователей:'));
                        var table = $('<table>', {class: 'table table-striped'});
                        table.append('<thead><tr><th scope="col">ID</th><th scope="col">Пользователь</th><th scope="col">Сумма бонуса</th></tr></thead>');
                        var tbody = $('<tbody>');
                        for (var i = 0; i < visibleUsers; i++) {
                            var user = data.users[i];
                            var row = $('<tr>');
                            row.append($('<td>').text(String(user.id)));
                            row.append($('<td>').text(String(user.username || '')));
                            row.append($('<td>').text((Number(user.bonus_amount) || 0).toFixed(2) + ' РУБ'));
                            tbody.append(row);
                        }
                        table.append(tbody);
                        content.append(table);
                        if (totalUsers > 50) {
                            content.append($('<p>').append($('<small>').text('Показано 50 из ' + totalUsers + ' пользователей')));
                        }
                    }
                    
                    if (data.preview_message) {
                        content.append($('<h4>').text('Предпросмотр сообщения:'));
                        content.append($('<div>', {class: 'audience-preview-message', text: String(data.preview_message)}));
                    }
                    
                    $('#preview-container').prop('hidden', false);
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
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Применение...');
        
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
                    btn.prop('disabled', false).html('<i class="fas fa-check" aria-hidden="true"></i> Применить начисление');
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
                btn.prop('disabled', false).html('<i class="fas fa-check" aria-hidden="true"></i> Применить начисление');
            }
        });
    });
});
JS
    , \yii\web\View::POS_READY
);
?>

