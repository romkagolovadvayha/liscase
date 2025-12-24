<?php

use common\models\servers\Servers;
use common\models\map\MapList;
use yii\bootstrap5\Html;

/** @var Servers $server */
/** @var string $wipeType */
/** @var MapList|null $mapList */
/** @var int|null $seed */
/** @var int|null $worldsize */
/** @var string $gamemode */
/** @var string $description */
/** @var int $maxplayers */
/** @var string $hostname */
/** @var string $tags */
/** @var string $rconCommand */

$this->title = Yii::t('common', 'Подтверждение вайпа');
?>
<div class="content-header">
    <h1><?= Html::encode($this->title) ?></h1>
</div>

<div class="content">
    <?= \frontend\widgets\Alert::widget() ?>

    <div class="ds-card mb-4">
        <div class="ds-card__header" style="background: #dc3545; color: white;">
            <h5 class="ds-card__header-title" style="color: white;">
                <i class="bi bi-exclamation-triangle"></i> <?= Yii::t('common', 'Подтверждение выполнения вайпа') ?>
            </h5>
        </div>
        <div class="ds-card__body">
            <div class="ds-alert ds-alert--warning mb-4">
                <i class="bi bi-exclamation-triangle"></i> <strong>Внимание!</strong> Вы собираетесь выполнить вайп сервера. Это действие нельзя отменить!
            </div>

            <h5 class="mb-3">Информация о сервере:</h5>
            <table class="table table-bordered mb-4">
                <tr>
                    <th style="width: 200px;">Название сервера:</th>
                    <td><?= Html::encode($server->name) ?></td>
                </tr>
                <tr>
                    <th>Тег сервера:</th>
                    <td><?= Html::encode($server->tag) ?></td>
                </tr>
                <tr>
                    <th>Тип вайпа:</th>
                    <td>
                        <span class="ds-badge ds-badge--<?= $wipeType === 'global' ? 'danger' : 'warning' ?>">
                            <?= $wipeType === 'global' ? 'Глобальный вайп (global)' : 'Вайп карты (wipe)' ?>
                        </span>
                    </td>
                </tr>
            </table>

            <h5 class="mb-3">Параметры вайпа:</h5>
            <table class="table table-bordered mb-4">
                <tr>
                    <th style="width: 200px;">Seed карты:</th>
                    <td>
                        <?php if ($seed !== null): ?>
                            <code><?= Html::encode($seed) ?></code>
                        <?php else: ?>
                            <span class="text-muted">Не указан (будет использован 0)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Размер мира (worldsize):</th>
                    <td>
                        <?php if ($worldsize !== null): ?>
                            <code><?= Html::encode($worldsize) ?></code>
                        <?php else: ?>
                            <span class="text-muted">Не указан (будет использован 0)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Имя пресета:</th>
                    <td><code><?= Html::encode($wipeType) ?></code></td>
                </tr>
                <tr>
                    <th>Режим игры (gamemode):</th>
                    <td><code><?= Html::encode($gamemode) ?></code></td>
                </tr>
                <tr>
                    <th>Описание (description):</th>
                    <td>
                        <?php if (!empty($description)): ?>
                            <pre style="white-space: pre-wrap; background: #f5f5f5; padding: 0.5rem; border-radius: 0.25rem; margin: 0;"><?= Html::encode($description) ?></pre>
                            <small class="text-muted">(Переносы строк будут заменены на \n)</small>
                        <?php else: ?>
                            <span class="text-muted">Не указано</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Макс. игроков (maxplayers):</th>
                    <td><code><?= Html::encode($maxplayers) ?></code></td>
                </tr>
                <tr>
                    <th>Название сервера (hostname):</th>
                    <td>
                        <?php if (!empty($hostname)): ?>
                            <code><?= Html::encode($hostname) ?></code>
                        <?php else: ?>
                            <span class="text-muted">Не указано</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Теги (tags):</th>
                    <td><code><?= Html::encode($tags) ?></code></td>
                </tr>
            </table>

            <h5 class="mb-3">RCON команда, которая будет выполнена:</h5>
            <div class="mb-4">
                <pre style="background: #1e1e1e; color: #d4d4d4; padding: 1rem; border-radius: 0.5rem; overflow-x: auto; font-family: 'Courier New', monospace; font-size: 0.9rem;"><code><?= Html::encode($rconCommand) ?></code></pre>
            </div>

            <form id="execute-wipe-form" method="post" action="/wipe/execute-run-wipe">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                <?= Html::hiddenInput('server_id', $server->id) ?>
                <?= Html::hiddenInput('wipe_type', $wipeType) ?>

                <div class="d-flex gap-2">
                    <button type="submit" class="ds-btn ds-btn--danger" id="execute-btn">
                        <i class="bi bi-check-circle"></i> Подтвердить и выполнить вайп
                    </button>
                    <?= Html::a(
                        '<i class="bi bi-arrow-left"></i> Назад',
                        '/wipe/run-wipe',
                        ['class' => 'ds-btn ds-btn--secondary']
                    ) ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Область для отображения результата -->
    <div class="ds-card mb-4" id="result-card" style="display: none;">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">
                <i class="bi bi-activity"></i> Результат выполнения
            </h5>
        </div>
        <div class="ds-card__body" id="result-body">
            <!-- Результат будет добавлен динамически -->
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('execute-wipe-form');
    const executeBtn = document.getElementById('execute-btn');
    const resultCard = document.getElementById('result-card');
    const resultBody = document.getElementById('result-body');

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        if (!confirm('Вы уверены, что хотите выполнить вайп? Это действие нельзя отменить!')) {
            return;
        }

        executeBtn.disabled = true;
        executeBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Выполняется...';

        const formData = new FormData(form);

        fetch('/wipe/execute-run-wipe', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            resultCard.style.display = 'block';
            
            if (data.success) {
                resultCard.querySelector('.ds-card__header').style.background = '#198754';
                resultCard.querySelector('.ds-card__header-title').style.color = 'white';
                resultBody.innerHTML = `
                    <div class="ds-alert ds-alert--success">
                        <i class="bi bi-check-circle"></i> <strong>Вайп успешно выполнен!</strong>
                    </div>
                    <p><strong>Команда:</strong></p>
                    <pre style="background: #1e1e1e; color: #d4d4d4; padding: 1rem; border-radius: 0.5rem; overflow-x: auto; font-family: 'Courier New', monospace; font-size: 0.9rem;"><code>${data.command}</code></pre>
                    ${data.result ? `<p><strong>Результат:</strong></p><pre style="background: #f5f5f5; padding: 1rem; border-radius: 0.5rem; overflow-x: auto;">${data.result}</pre>` : ''}
                `;
            } else {
                resultCard.querySelector('.ds-card__header').style.background = '#dc3545';
                resultCard.querySelector('.ds-card__header-title').style.color = 'white';
                resultBody.innerHTML = `
                    <div class="ds-alert ds-alert--danger">
                        <i class="bi bi-x-circle"></i> <strong>Ошибка выполнения вайпа!</strong>
                    </div>
                    <p>${data.message}</p>
                    ${data.command ? `<p><strong>Команда:</strong></p><pre style="background: #1e1e1e; color: #d4d4d4; padding: 1rem; border-radius: 0.5rem; overflow-x: auto; font-family: 'Courier New', monospace; font-size: 0.9rem;"><code>${data.command}</code></pre>` : ''}
                `;
            }

            executeBtn.disabled = false;
            executeBtn.innerHTML = '<i class="bi bi-check-circle"></i> Подтвердить и выполнить вайп';
        })
        .catch(error => {
            resultCard.style.display = 'block';
            resultCard.querySelector('.ds-card__header').style.background = '#dc3545';
            resultCard.querySelector('.ds-card__header-title').style.color = 'white';
            resultBody.innerHTML = `
                <div class="ds-alert ds-alert--danger">
                    <i class="bi bi-x-circle"></i> <strong>Ошибка выполнения вайпа!</strong>
                </div>
                <p>Произошла ошибка: ${error.message}</p>
            `;

            executeBtn.disabled = false;
            executeBtn.innerHTML = '<i class="bi bi-check-circle"></i> Подтвердить и выполнить вайп';
        });
    });
});
</script>

