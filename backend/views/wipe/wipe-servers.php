<?php

use common\models\servers\Servers;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

/** @var Servers[] $servers */

$this->title = Yii::t('common', 'Комплексный вайп серверов');
?>
<div class="content-header">
    <h1><?= Html::encode($this->title) ?></h1>
</div>

<div class="content">
    <?= \frontend\widgets\Alert::widget() ?>

    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">
                <i class="bi bi-server"></i> <?= Yii::t('common', 'Выбор серверов для вайпа') ?>
            </h5>
        </div>
        <div class="ds-card__body">
            <form id="wipe-servers-form">
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
                    <label for="rcon_command" class="form-label">RCON команда (необязательно):</label>
                    <input type="text" class="form-control" id="rcon_command" name="rcon_command" 
                           placeholder="Например: server.save" value="">
                    <small class="form-text text-muted">Команда будет выполнена на всех выбранных серверах после завершения остальных этапов</small>
                </div>

                <div class="mb-3">
                    <button type="button" class="ds-btn ds-btn--success" id="start-wipe-btn">
                        <i class="bi bi-play-circle"></i> Начать вайп
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

    <!-- Область для отображения процесса -->
    <div class="ds-card mb-4" id="wipe-progress-card" style="display: none;">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">
                <i class="bi bi-activity"></i> <?= Yii::t('common', 'Процесс вайпа') ?>
            </h5>
        </div>
        <div class="ds-card__body">
            <div id="wipe-progress-container">
                <!-- Прогресс будет добавляться динамически -->
            </div>
        </div>
    </div>
</div>

<style>
.wipe-step {
    margin-bottom: 1.5rem;
    padding: 1rem;
    border: 1px solid var(--ds-border-color);
    border-radius: 0.5rem;
    background: var(--ds-bg-secondary);
}

.wipe-step-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.wipe-step-status {
    padding: 0.25rem 0.75rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.wipe-step-status.pending {
    background: #6c757d;
    color: white;
}

.wipe-step-status.processing {
    background: #0d6efd;
    color: white;
}

.wipe-step-status.success {
    background: #198754;
    color: white;
}

.wipe-step-status.error {
    background: #dc3545;
    color: white;
}

.server-result {
    padding: 0.5rem;
    margin: 0.25rem 0;
    border-radius: 0.25rem;
    background: var(--ds-bg);
}

.server-result.success {
    border-left: 3px solid #198754;
}

.server-result.error {
    border-left: 3px solid #dc3545;
}

.server-result.pending {
    border-left: 3px solid #6c757d;
}

.spinner-border-sm {
    width: 1rem;
    height: 1rem;
    border-width: 0.15em;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('wipe-servers-form');
    const startBtn = document.getElementById('start-wipe-btn');
    const selectAllBtn = document.getElementById('select-all-btn');
    const deselectAllBtn = document.getElementById('deselect-all-btn');
    const progressCard = document.getElementById('wipe-progress-card');
    const progressContainer = document.getElementById('wipe-progress-container');
    
    // Выбрать все / снять выбор
    selectAllBtn.addEventListener('click', function() {
        document.querySelectorAll('input[name="server_ids[]"]').forEach(cb => cb.checked = true);
    });
    
    deselectAllBtn.addEventListener('click', function() {
        document.querySelectorAll('input[name="server_ids[]"]').forEach(cb => cb.checked = false);
    });
    
    // Начать вайп
    startBtn.addEventListener('click', function() {
        const serverIds = [];
        document.querySelectorAll('input[name="server_ids[]"]:checked').forEach(cb => {
            serverIds.push(cb.value);
        });
        
        if (serverIds.length === 0) {
            alert('Выберите хотя бы один сервер');
            return;
        }
        
        const rconCommand = document.getElementById('rcon_command').value;
        
        // Показываем область прогресса
        progressCard.style.display = 'block';
        progressContainer.innerHTML = '';
        startBtn.disabled = true;
        startBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Выполняется...';
        
        // Инициализируем отображение этапов
        const steps = [
            { id: 'step1', name: 'Этап 1: Блокировка предметов в магазине', status: 'pending' },
            { id: 'step2', name: 'Этап 2: Начисление наград за топы', status: 'pending' },
            { id: 'step3', name: 'Этап 3: Обнуление промокода WIPE', status: 'pending' },
            { id: 'step4', name: 'Этап 4: Выполнение RCON команды', status: 'pending' },
        ];
        
        steps.forEach(step => {
            const stepDiv = document.createElement('div');
            stepDiv.className = 'wipe-step';
            stepDiv.id = step.id;
            stepDiv.innerHTML = `
                <div class="wipe-step-header">
                    <span>${step.name}</span>
                    <span class="wipe-step-status pending" id="${step.id}_status">Ожидание</span>
                </div>
                <div id="${step.id}_results"></div>
            `;
            progressContainer.appendChild(stepDiv);
        });
        
        // Отправляем запрос
        const formData = new URLSearchParams();
        formData.append('_csrf', '<?= Yii::$app->request->csrfToken ?>');
        serverIds.forEach(id => {
            formData.append('server_ids[]', id);
        });
        if (rconCommand) {
            formData.append('rcon_command', rconCommand);
        }
        
        fetch('/wipe/execute-wipe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Обновляем статусы этапов
            if (data.results) {
                updateStepStatus('step1', data.results.step1_block_items, 'Блокировка предметов');
                updateStepStatus('step2', data.results.step2_top_rewards, 'Начисление наград');
                updateStepStatus('step3', data.results.step3_reset_promocode, 'Обнуление промокода');
                updateStepStatus('step4', data.results.step4_rcon, 'RCON команда');
            }
            
            // Показываем общий результат
            const resultDiv = document.createElement('div');
            resultDiv.className = 'wipe-step';
            resultDiv.style.marginTop = '1rem';
            resultDiv.style.padding = '1rem';
            resultDiv.style.backgroundColor = data.success ? '#d1e7dd' : '#f8d7da';
            resultDiv.style.borderColor = data.success ? '#badbcc' : '#f5c2c7';
            resultDiv.innerHTML = `
                <div class="wipe-step-header">
                    <span><strong>${data.success ? '✓ Вайп успешно завершен' : '✗ Вайп завершен с ошибками'}</strong></span>
                </div>
                <div>${data.message}</div>
            `;
            progressContainer.appendChild(resultDiv);
            
            startBtn.disabled = false;
            startBtn.innerHTML = '<i class="bi bi-play-circle"></i> Начать вайп';
        })
        .catch(error => {
            console.error('Error:', error);
            const errorDiv = document.createElement('div');
            errorDiv.className = 'wipe-step';
            errorDiv.style.marginTop = '1rem';
            errorDiv.style.backgroundColor = '#f8d7da';
            errorDiv.style.borderColor = '#f5c2c7';
            errorDiv.innerHTML = `
                <div class="wipe-step-header">
                    <span><strong>✗ Ошибка выполнения</strong></span>
                </div>
                <div>Произошла ошибка при выполнении вайпа: ${error.message}</div>
            `;
            progressContainer.appendChild(errorDiv);
            
            startBtn.disabled = false;
            startBtn.innerHTML = '<i class="bi bi-play-circle"></i> Начать вайп';
        });
    });
    
    function updateStepStatus(stepId, results, stepName) {
        const stepDiv = document.getElementById(stepId);
        const statusSpan = document.getElementById(stepId + '_status');
        const resultsDiv = document.getElementById(stepId + '_results');
        
        if (!results || (typeof results === 'object' && Object.keys(results).length === 0)) {
            return;
        }
        
        let allSuccess = true;
        let hasError = false;
        
        // Если это не массив результатов по серверам, а один результат
        if (results.success !== undefined) {
            allSuccess = results.success;
            hasError = !results.success;
            
            statusSpan.className = 'wipe-step-status ' + (allSuccess ? 'success' : 'error');
            statusSpan.textContent = allSuccess ? '✓ Выполнено' : '✗ Ошибка';
            
            resultsDiv.innerHTML = `
                <div class="server-result ${allSuccess ? 'success' : 'error'}">
                    <strong>${stepName}:</strong> ${results.message || 'Выполнено'}
                </div>
            `;
            return;
        }
        
        // Обрабатываем результаты по серверам
        resultsDiv.innerHTML = '';
        
        Object.keys(results).forEach(serverId => {
            const result = results[serverId];
            if (!result.success) {
                allSuccess = false;
                hasError = true;
            }
            
            const serverResultDiv = document.createElement('div');
            serverResultDiv.className = 'server-result ' + (result.success ? 'success' : 'error');
            serverResultDiv.innerHTML = `
                <strong>Сервер ID ${serverId}:</strong> ${result.message || 'Выполнено'}
                ${result.result ? '<br><small class="text-muted">' + result.result + '</small>' : ''}
            `;
            resultsDiv.appendChild(serverResultDiv);
        });
        
        statusSpan.className = 'wipe-step-status ' + (allSuccess ? 'success' : 'error');
        statusSpan.textContent = allSuccess ? '✓ Выполнено' : '✗ Ошибка';
    }
});
</script>

