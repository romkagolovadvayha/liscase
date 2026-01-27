<?php

use common\models\servers\Servers;
use yii\bootstrap5\Html;

/** @var Servers[] $servers */

$this->title = Yii::t('common', 'Массовое редактирование дат вайпа');
?>
<div class="content-header">
    <h1><?= Html::encode($this->title) ?></h1>
</div>

<div class="content">
    <?= \frontend\widgets\Alert::widget() ?>

    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">
                <i class="bi bi-calendar-event"></i> <?= Yii::t('common', 'Выбор серверов и редактирование дат') ?>
            </h5>
        </div>
        <div class="ds-card__body">
            <form id="mass-edit-wipe-form">
                <div class="mb-3">
                    <label class="form-label">Выберите серверы:</label>
                    <div class="row">
                        <?php foreach ($servers as $server): ?>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                           name="server_ids[]" 
                                           value="<?= $server->id ?>" 
                                           id="server_<?= $server->id ?>">
                                    <label class="form-check-label" for="server_<?= $server->id ?>">
                                        <?= Html::encode($server->name) ?> 
                                        <small class="text-muted">(<?= Html::encode($server->tag) ?>)</small>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="wipe" class="form-label">Последний вайп:</label>
                            <input type="datetime-local" 
                                   class="form-control" 
                                   id="wipe" 
                                   name="wipe" 
                                   value="">
                            <small class="form-text text-muted">Выберите дату и время последнего вайпа</small>
                        </div>
                        <div class="col-md-4">
                            <label for="next_wipe" class="form-label">Следующий вайп:</label>
                            <input type="datetime-local" 
                                   class="form-control" 
                                   id="next_wipe" 
                                   name="next_wipe" 
                                   value="">
                            <small class="form-text text-muted">Выберите дату и время следующего вайпа</small>
                        </div>
                        <div class="col-md-4">
                            <label for="global_wipe" class="form-label">Глобал вайп:</label>
                            <input type="datetime-local" 
                                   class="form-control" 
                                   id="global_wipe" 
                                   name="global_wipe" 
                                   value="">
                            <small class="form-text text-muted">Выберите дату и время глобального вайпа</small>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="ds-alert ds-alert--info">
                        <i class="bi bi-info-circle"></i> <strong>Внимание!</strong> Заполните только те поля, которые хотите изменить. Незаполненные поля останутся без изменений.
                    </div>
                </div>

                <div class="mb-3">
                    <button type="button" class="ds-btn ds-btn--success" id="save-btn">
                        <i class="bi bi-save"></i> Сохранить изменения
                    </button>
                    <button type="button" class="ds-btn ds-btn--secondary" id="select-all-btn">
                        <i class="bi bi-check-all"></i> Выбрать все
                    </button>
                    <button type="button" class="ds-btn ds-btn--secondary" id="deselect-all-btn">
                        <i class="bi bi-x-circle"></i> Снять выбор
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Область для отображения результатов -->
    <div class="ds-card mb-4" id="results-card" style="display: none;">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">
                <i class="bi bi-check-circle"></i> <?= Yii::t('common', 'Результаты сохранения') ?>
            </h5>
        </div>
        <div class="ds-card__body">
            <div id="results-container">
                <!-- Результаты будут добавляться динамически -->
            </div>
        </div>
    </div>
</div>

<style>
.server-result {
    padding: 0.75rem;
    margin: 0.5rem 0;
    border-radius: 0.25rem;
    background: var(--ds-bg);
    border-left: 3px solid;
}

.server-result.success {
    border-left-color: #198754;
    background: #d1e7dd;
}

.server-result.error {
    border-left-color: #dc3545;
    background: #f8d7da;
}

.summary-result {
    padding: 1rem;
    margin: 1rem 0;
    border-radius: 0.5rem;
    font-weight: 600;
}

.summary-result.success {
    background: #d1e7dd;
    border: 1px solid #badbcc;
    color: #0f5132;
}

.summary-result.error {
    background: #f8d7da;
    border: 1px solid #f5c2c7;
    color: #842029;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('mass-edit-wipe-form');
    const saveBtn = document.getElementById('save-btn');
    const selectAllBtn = document.getElementById('select-all-btn');
    const deselectAllBtn = document.getElementById('deselect-all-btn');
    const resultsCard = document.getElementById('results-card');
    const resultsContainer = document.getElementById('results-container');
    
    // Выбрать все / снять выбор
    selectAllBtn.addEventListener('click', function() {
        document.querySelectorAll('input[name="server_ids[]"]').forEach(cb => cb.checked = true);
    });
    
    deselectAllBtn.addEventListener('click', function() {
        document.querySelectorAll('input[name="server_ids[]"]').forEach(cb => cb.checked = false);
    });
    
    // Сохранить изменения
    saveBtn.addEventListener('click', function() {
        const serverIds = [];
        document.querySelectorAll('input[name="server_ids[]"]:checked').forEach(cb => {
            serverIds.push(cb.value);
        });
        
        if (serverIds.length === 0) {
            alert('Выберите хотя бы один сервер');
            return;
        }
        
        const wipe = document.getElementById('wipe').value.trim();
        const nextWipe = document.getElementById('next_wipe').value.trim();
        const globalWipe = document.getElementById('global_wipe').value.trim();
        
        if (!wipe && !nextWipe && !globalWipe) {
            alert('Заполните хотя бы одно поле для редактирования');
            return;
        }
        
        // Показываем область результатов
        resultsCard.style.display = 'block';
        resultsContainer.innerHTML = '<div class="text-center"><span class="spinner-border spinner-border-sm"></span> Сохранение...</div>';
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Сохранение...';
        
        // Конвертируем datetime-local в формат YYYY-MM-DD HH:MM:SS
        function convertDateTimeLocalToDbFormat(datetimeLocal) {
            if (!datetimeLocal) return '';
            // datetime-local формат: YYYY-MM-DDTHH:MM
            // Нужно преобразовать в: YYYY-MM-DD HH:MM:SS
            return datetimeLocal.replace('T', ' ') + ':00';
        }
        
        // Отправляем запрос
        const formData = new URLSearchParams();
        formData.append('_csrf', '<?= Yii::$app->request->csrfToken ?>');
        serverIds.forEach(id => {
            formData.append('server_ids[]', id);
        });
        if (wipe) {
            formData.append('wipe', convertDateTimeLocalToDbFormat(wipe));
        }
        if (nextWipe) {
            formData.append('next_wipe', convertDateTimeLocalToDbFormat(nextWipe));
        }
        if (globalWipe) {
            formData.append('global_wipe', convertDateTimeLocalToDbFormat(globalWipe));
        }
        
        fetch('/servers/save-mass-wipe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            resultsContainer.innerHTML = '';
            
            // Показываем общий результат
            const summaryDiv = document.createElement('div');
            summaryDiv.className = 'summary-result ' + (data.success ? 'success' : 'error');
            summaryDiv.innerHTML = `
                <i class="bi ${data.success ? 'bi-check-circle' : 'bi-x-circle'}"></i> 
                <strong>${data.message}</strong>
            `;
            resultsContainer.appendChild(summaryDiv);
            
            // Показываем результаты по каждому серверу
            if (data.results) {
                Object.keys(data.results).forEach(serverId => {
                    const result = data.results[serverId];
                    const resultDiv = document.createElement('div');
                    resultDiv.className = 'server-result ' + (result.success ? 'success' : 'error');
                    resultDiv.innerHTML = `
                        <strong>${result.server_name || 'Сервер ID ' + serverId}:</strong> ${result.message}
                    `;
                    resultsContainer.appendChild(resultDiv);
                });
            }
            
            // Прокручиваем к результатам
            resultsCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-save"></i> Сохранить изменения';
            
            // Если все успешно, очищаем форму
            if (data.success && data.error_count === 0) {
                setTimeout(() => {
                    document.getElementById('wipe').value = '';
                    document.getElementById('next_wipe').value = '';
                    document.getElementById('global_wipe').value = '';
                    document.querySelectorAll('input[name="server_ids[]"]:checked').forEach(cb => cb.checked = false);
                }, 2000);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            resultsContainer.innerHTML = `
                <div class="summary-result error">
                    <i class="bi bi-x-circle"></i> 
                    <strong>Ошибка выполнения</strong><br>
                    Произошла ошибка при сохранении: ${error.message}
                </div>
            `;
            
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-save"></i> Сохранить изменения';
        });
    });
});
</script>

