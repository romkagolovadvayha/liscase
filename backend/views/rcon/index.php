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
                            ?>
                            <div class="rcon-result-item" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                                <div class="rcon-result-header" style="margin-bottom: 10px;">
                                    <h4 style="margin: 0; color: #333;">
                                        <i class="fas fa-server"></i> 
                                        <?= Html::encode($server->name) ?> 
                                        <small style="color: #666;">(<?= Html::encode($tag) ?>)</small>
                                    </h4>
                                </div>
                                
                                <?php if ($error): ?>
                                    <div class="alert alert-danger" style="margin: 0;">
                                        <strong>Ошибка:</strong> <?= Html::encode($error) ?>
                                    </div>
                                <?php elseif ($serverResult !== null): ?>
                                    <div class="rcon-result-content" style="background: #f5f5f5; padding: 10px; border-radius: 4px; font-family: monospace; white-space: pre-wrap; word-wrap: break-word; max-height: 400px; overflow-y: auto;">
                                        <?= Html::encode($serverResult) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning" style="margin: 0;">
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

.rcon-result-item {
    transition: box-shadow 0.2s;
}

.rcon-result-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.rcon-result-content {
    font-size: 13px;
    line-height: 1.5;
}

@media (max-width: 768px) {
    .rcon-result-content {
        font-size: 11px;
    }
}
</style>

