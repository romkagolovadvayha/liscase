<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use common\models\servers\Servers;

/** @var yii\web\View $this */
/** @var string $command */
/** @var array $results */
/** @var Servers[] $allServers */
/** @var array $selectedServers */

$this->title = 'RCON команды';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="rcon-index-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <div class="ds-card">
            <div class="card-header">
                <h3 class="card-title">Выполнить команду на всех серверах</h3>
            </div>
            <div class="card-body">
                <?php $form = ActiveForm::begin([
                    'id' => 'rcon-form',
                    'action' => ['index'],
                    'method' => 'post',
                    'options' => [
                        'class' => 'rcon-form',
                    ],
                ]); ?>

                <div class="form-group">
                    <?= Html::label('RCON команда', 'command', ['class' => 'control-label']) ?>
                    <?= Html::textInput('command', $command, [
                        'id' => 'command',
                        'class' => 'form-control',
                        'placeholder' => 'Например: serverinfo или say "Привет всем!"',
                        'required' => true,
                        'autofocus' => true,
                    ]) ?>
                    <div class="help-block">
                        Команда будет выполнена на выбранных серверах
                    </div>
                </div>

                <div class="form-group">
                    <?= Html::label('Выберите сервера', 'servers', ['class' => 'control-label']) ?>
                    <div class="rcon-servers-list">
                        <div class="rcon-servers-controls" style="margin-bottom: 10px;">
                            <button type="button" class="btn btn-sm btn-link" id="select-all-servers" style="padding: 0; color: #007bff;">
                                Выбрать все
                            </button>
                            <span style="margin: 0 10px; color: #6c757d;">|</span>
                            <button type="button" class="btn btn-sm btn-link" id="deselect-all-servers" style="padding: 0; color: #007bff;">
                                Снять все
                            </button>
                        </div>
                        <div class="rcon-servers-checkboxes">
                            <?php foreach ($allServers as $server): ?>
                                <?php
                                $isChecked = in_array($server->tag, $selectedServers);
                                ?>
                                <div class="rcon-server-checkbox">
                                    <?= Html::checkbox('servers[]', $isChecked, [
                                        'value' => $server->tag,
                                        'id' => 'server-' . $server->id,
                                        'class' => 'server-checkbox',
                                    ]) ?>
                                    <?= Html::label(
                                        Html::encode($server->name) . ' <small>(' . Html::encode($server->tag) . ')</small>',
                                        'server-' . $server->id,
                                        ['class' => 'server-checkbox-label']
                                    ) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <?= Html::submitButton('<i class="fas fa-play"></i> Выполнить', [
                        'class' => 'ds-btn ds-btn--primary',
                        'id' => 'execute-btn',
                    ]) ?>
                </div>

                <?php ActiveForm::end(); ?>
            </div>
        </div>

        <?php if (!empty($results)): ?>
            <div class="ds-card" style="margin-top: 20px;">
                <div class="card-header">
                    <h3 class="card-title">Результаты выполнения</h3>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs rcon-tabs" role="tablist">
                        <?php $firstTab = true; ?>
                        <?php foreach ($results as $tag => $result): ?>
                            <?php
                            /** @var Servers $server */
                            $server = $result['server'];
                            $tabId = 'rcon-tab-' . $server->id;
                            $paneId = 'rcon-pane-' . $server->id;
                            ?>
                            <li class="nav-item" role="presentation">
                                <button
                                    class="nav-link<?= $firstTab ? ' active' : '' ?>"
                                    id="<?= $tabId ?>"
                                    data-bs-toggle="tab"
                                    data-bs-target="#<?= $paneId ?>"
                                    type="button"
                                    role="tab"
                                    aria-controls="<?= $paneId ?>"
                                    aria-selected="<?= $firstTab ? 'true' : 'false' ?>">
                                    <i class="fas fa-server"></i>
                                    <?= Html::encode($server->name) ?>
                                    <small>(<?= Html::encode($tag) ?>)</small>
                                    <?php if ($result['error']): ?>
                                        <i class="fas fa-exclamation-circle text-danger" style="margin-left: 5px;"></i>
                                    <?php elseif ($result['result'] === null): ?>
                                        <i class="fas fa-exclamation-triangle text-warning" style="margin-left: 5px;"></i>
                                    <?php else: ?>
                                        <i class="fas fa-check-circle text-success" style="margin-left: 5px;"></i>
                                    <?php endif; ?>
                                </button>
                            </li>
                            <?php $firstTab = false; ?>
                        <?php endforeach; ?>
                    </ul>
                    
                    <div class="tab-content rcon-tab-content">
                        <?php $firstTab = true; ?>
                        <?php foreach ($results as $tag => $result): ?>
                            <?php
                            /** @var Servers $server */
                            $server = $result['server'];
                            $serverResult = $result['result'];
                            $error = $result['error'];
                            $paneId = 'rcon-pane-' . $server->id;
                            
                            // Проверяем, является ли результат JSON
                            $isJson = false;
                            $jsonData = null;
                            $hasResultField = false;
                            if ($serverResult !== null && !empty($serverResult)) {
                                $decoded = json_decode($serverResult, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                    $isJson = true;
                                    $jsonData = $decoded;
                                    // Проверяем, есть ли поле result с текстом
                                    if (isset($jsonData['result']) && is_string($jsonData['result'])) {
                                        $hasResultField = true;
                                    }
                                }
                            }
                            ?>
                            <div class="tab-pane fade<?= $firstTab ? ' show active' : '' ?>" 
                                 id="<?= $paneId ?>" 
                                 role="tabpanel" 
                                 aria-labelledby="rcon-tab-<?= $server->id ?>">
                                <div class="rcon-result-item">
                                    <?php if ($error): ?>
                                        <div class="rcon-error">
                                            <i class="fas fa-exclamation-circle"></i>
                                            <strong>Ошибка:</strong> <?= Html::encode($error) ?>
                                        </div>
                                    <?php elseif ($serverResult !== null): ?>
                                        <?php if ($isJson && $hasResultField): ?>
                                            <?php
                                            // Если JSON содержит поле result с текстом, выводим только его содержимое
                                            $resultText = $jsonData['result'];
                                            // Заменяем \n на реальные переносы строк
                                            $resultText = str_replace("\\n", "\n", $resultText);
                                            ?>
                                            <div class="rcon-result-content">
                                                <pre><?= Html::encode($resultText) ?></pre>
                                            </div>
                                        <?php elseif ($isJson): ?>
                                            <div class="rcon-result-json">
                                                <pre><code><?= Html::encode(json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                                            </div>
                                        <?php else: ?>
                                            <?php
                                            // Обрабатываем переносы строк в обычном тексте
                                            $formattedResult = str_replace("\\n", "\n", $serverResult);
                                            ?>
                                            <div class="rcon-result-content">
                                                <pre><?= Html::encode($formattedResult) ?></pre>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="rcon-warning">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            Нет ответа от сервера
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php $firstTab = false; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.rcon-form {
    max-width: 800px;
}

/* Табы для результатов */
.rcon-tabs {
    border-bottom: 2px solid #333;
    margin-bottom: 0;
}

.rcon-tabs .nav-link {
    color: #888;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    padding: 12px 20px;
    transition: all 0.3s ease;
    font-weight: 500;
}

.rcon-tabs .nav-link:hover {
    color: #e0e0e0;
    border-bottom-color: #555;
    background: rgba(255, 255, 255, 0.05);
}

.rcon-tabs .nav-link.active {
    color: #4a9eff;
    border-bottom-color: #4a9eff;
    background: transparent;
}

.rcon-tabs .nav-link small {
    color: #666;
    font-size: 0.85em;
    margin-left: 4px;
}

.rcon-tabs .nav-link.active small {
    color: #6bb3ff;
}

.rcon-tab-content {
    background: #1e1e1e;
    border: 1px solid #333;
    border-top: none;
    border-radius: 0 0 8px 8px;
    padding: 0;
    min-height: 200px;
}

.rcon-result-item {
    padding: 20px;
    background: transparent;
    border: none;
    border-radius: 0;
    box-shadow: none;
}

.rcon-result-item:hover {
    box-shadow: none;
    border: none;
}

.rcon-result-content,
.rcon-result-json {
    background: #0d1117;
    border: 1px solid #21262d;
    border-radius: 6px;
    padding: 15px;
    max-height: 500px;
    overflow-y: auto;
    position: relative;
}

.rcon-result-content pre,
.rcon-result-json pre {
    margin: 0;
    padding: 0;
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 13px;
    line-height: 1.6;
    color: #c9d1d9;
    white-space: pre-wrap;
    word-wrap: break-word;
    background: transparent;
    border: none;
}

.rcon-result-json pre code {
    color: #c9d1d9;
    display: block;
}


/* JSON Syntax Highlighting */
.rcon-result-json pre code {
    color: #c9d1d9;
}

/* Scrollbar styling for dark theme */
.rcon-result-content::-webkit-scrollbar,
.rcon-result-json::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

.rcon-result-content::-webkit-scrollbar-track,
.rcon-result-json::-webkit-scrollbar-track {
    background: #161b22;
    border-radius: 5px;
}

.rcon-result-content::-webkit-scrollbar-thumb,
.rcon-result-json::-webkit-scrollbar-thumb {
    background: #30363d;
    border-radius: 5px;
    border: 2px solid #161b22;
}

.rcon-result-content::-webkit-scrollbar-thumb:hover,
.rcon-result-json::-webkit-scrollbar-thumb:hover {
    background: #484f58;
}

.rcon-error {
    background: #2d1b1b;
    border: 1px solid #8b2635;
    border-radius: 6px;
    padding: 12px 15px;
    color: #f85149;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.rcon-error i {
    font-size: 16px;
}

.rcon-warning {
    background: #2d2b1b;
    border: 1px solid #8b7d26;
    border-radius: 6px;
    padding: 12px 15px;
    color: #d29922;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.rcon-warning i {
    font-size: 16px;
}

.rcon-servers-list {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 15px;
    max-height: 300px;
    overflow-y: auto;
}

.rcon-servers-controls {
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 10px;
}

.rcon-servers-checkboxes {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 10px;
    margin-top: 15px;
}

.rcon-server-checkbox {
    display: flex;
    align-items: center;
    padding: 8px;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    transition: all 0.2s;
}

.rcon-server-checkbox:hover {
    background: #e9ecef;
    border-color: #adb5bd;
}

.rcon-server-checkbox input[type="checkbox"] {
    margin-right: 8px;
    cursor: pointer;
}

.rcon-server-checkbox label {
    margin: 0;
    cursor: pointer;
    font-weight: 400;
    color: #212529;
    flex: 1;
}

.rcon-server-checkbox label small {
    color: #6c757d;
    font-size: 0.9em;
}

@media (max-width: 768px) {
    .rcon-result-item {
        padding: 15px;
    }
    
    .rcon-result-content pre,
    .rcon-result-json pre code {
        font-size: 11px;
    }
    
    .rcon-result-header h4 {
        font-size: 14px;
    }
    
    .rcon-servers-checkboxes {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
(function() {
    // Выбрать все серверы
    document.getElementById('select-all-servers')?.addEventListener('click', function() {
        document.querySelectorAll('.server-checkbox').forEach(function(checkbox) {
            checkbox.checked = true;
        });
    });
    
    // Снять все серверы
    document.getElementById('deselect-all-servers')?.addEventListener('click', function() {
        document.querySelectorAll('.server-checkbox').forEach(function(checkbox) {
            checkbox.checked = false;
        });
    });
})();
</script>

