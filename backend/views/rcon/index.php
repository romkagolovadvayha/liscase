<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use common\models\servers\Servers;

/** @var yii\web\View $this */
/** @var string $command */
/** @var array $results */

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
                        Команда будет выполнена на всех активных серверах одновременно
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
                    <div class="rcon-results">
                        <?php foreach ($results as $tag => $result): ?>
                            <?php
                            /** @var Servers $server */
                            $server = $result['server'];
                            $serverResult = $result['result'];
                            $error = $result['error'];
                            
                            // Проверяем, является ли результат JSON
                            $isJson = false;
                            $jsonData = null;
                            if ($serverResult !== null && !empty($serverResult)) {
                                $decoded = json_decode($serverResult, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                    $isJson = true;
                                    $jsonData = $decoded;
                                }
                            }
                            ?>
                            <div class="rcon-result-item">
                                <div class="rcon-result-header">
                                    <h4>
                                        <i class="fas fa-server"></i> 
                                        <?= Html::encode($server->name) ?> 
                                        <small>(<?= Html::encode($tag) ?>)</small>
                                    </h4>
                                </div>
                                
                                <?php if ($error): ?>
                                    <div class="rcon-error">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <strong>Ошибка:</strong> <?= Html::encode($error) ?>
                                    </div>
                                <?php elseif ($serverResult !== null): ?>
                                    <?php if ($isJson): ?>
                                        <div class="rcon-result-json">
                                            <pre><code><?= Html::encode(json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                                        </div>
                                    <?php else: ?>
                                        <div class="rcon-result-content">
                                            <pre><?= Html::encode($serverResult) ?></pre>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="rcon-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Нет ответа от сервера
                                    </div>
                                <?php endif; ?>
                            </div>
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

.rcon-results {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.rcon-result-item {
    background: #1e1e1e;
    border: 1px solid #333;
    border-radius: 8px;
    padding: 20px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.rcon-result-item:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
    border-color: #444;
}

.rcon-result-header {
    margin-bottom: 15px;
    padding-bottom: 12px;
    border-bottom: 1px solid #333;
}

.rcon-result-header h4 {
    margin: 0;
    color: #e0e0e0;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.rcon-result-header h4 i {
    color: #4a9eff;
}

.rcon-result-header h4 small {
    color: #888;
    font-weight: 400;
    font-size: 14px;
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
}
</style>

