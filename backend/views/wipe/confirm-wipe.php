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
        <div class="ds-card__header wipe-confirm-header">
            <h5 class="ds-card__header-title">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> <?= Yii::t('common', 'Подтверждение выполнения вайпа') ?>
            </h5>
        </div>
        <div class="ds-card__body">
            <div class="ds-alert ds-alert--warning mb-4">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> <strong>Внимание!</strong> Вы собираетесь выполнить вайп сервера. Это действие нельзя отменить!
            </div>

            <h5 class="mb-3">Информация о сервере:</h5>
            <table class="table table-bordered mb-4">
                <tr>
                    <th class="wipe-detail-label" scope="row">Название сервера:</th>
                    <td><?= Html::encode($server->name) ?></td>
                </tr>
                <tr>
                    <th scope="row">Тег сервера:</th>
                    <td><?= Html::encode($server->tag) ?></td>
                </tr>
                <tr>
                    <th scope="row">Тип вайпа:</th>
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
                    <th class="wipe-detail-label" scope="row">Seed карты:</th>
                    <td>
                        <?php if ($seed !== null): ?>
                            <code><?= Html::encode($seed) ?></code>
                        <?php else: ?>
                            <span class="text-muted">Не указан (будет использован 0)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Размер мира (worldsize):</th>
                    <td>
                        <?php if ($worldsize !== null): ?>
                            <code><?= Html::encode($worldsize) ?></code>
                        <?php else: ?>
                            <span class="text-muted">Не указан (будет использован 0)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Имя пресета:</th>
                    <td><code><?= Html::encode($wipeType) ?></code></td>
                </tr>
                <tr>
                    <th scope="row">Режим игры (gamemode):</th>
                    <td><code><?= Html::encode($gamemode) ?></code></td>
                </tr>
                <tr>
                    <th scope="row">Описание (description):</th>
                    <td>
                        <?php if (!empty($description)): ?>
                            <pre class="admin-code-block admin-code-block--wrap"><?= Html::encode($description) ?></pre>
                            <small class="text-muted">(Переносы строк будут заменены на \n)</small>
                        <?php else: ?>
                            <span class="text-muted">Не указано</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Макс. игроков (maxplayers):</th>
                    <td><code><?= Html::encode($maxplayers) ?></code></td>
                </tr>
                <tr>
                    <th scope="row">Название сервера (hostname):</th>
                    <td>
                        <?php if (!empty($hostname)): ?>
                            <code><?= Html::encode($hostname) ?></code>
                        <?php else: ?>
                            <span class="text-muted">Не указано</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Теги (tags):</th>
                    <td><code><?= Html::encode($tags) ?></code></td>
                </tr>
            </table>

            <h5 class="mb-3">RCON команда, которая будет выполнена:</h5>
            <div class="mb-4">
                <pre class="admin-code-block"><code><?= Html::encode($rconCommand) ?></code></pre>
            </div>

            <form id="execute-wipe-form" method="post" action="/wipe/execute-run-wipe">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                <?= Html::hiddenInput('server_id', $server->id) ?>
                <?= Html::hiddenInput('wipe_type', $wipeType) ?>

                <div class="d-flex gap-2">
                    <button type="submit" class="ds-btn ds-btn--danger" id="execute-btn">
                        <i class="bi bi-check-circle" aria-hidden="true"></i> Подтвердить и выполнить вайп
                    </button>
                    <?= Html::a(
                        '<i class="bi bi-arrow-left" aria-hidden="true"></i> Назад',
                        '/wipe/run-wipe',
                        ['class' => 'ds-btn ds-btn--secondary']
                    ) ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Область для отображения результата -->
    <div class="ds-card mb-4" id="result-card" hidden>
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">
                <i class="bi bi-activity" aria-hidden="true"></i> Результат выполнения
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
    const resultHeader = resultCard.querySelector('.ds-card__header');

    function appendAlert(type, iconClass, message) {
        const alert = document.createElement('div');
        alert.className = 'ds-alert ds-alert--' + type;
        const icon = document.createElement('i');
        icon.className = iconClass;
        icon.setAttribute('aria-hidden', 'true');
        const strong = document.createElement('strong');
        strong.textContent = message;
        alert.append(icon, document.createTextNode(' '), strong);
        resultBody.appendChild(alert);
    }

    function appendResultBlock(label, value, useCode) {
        if (!value) return;
        const heading = document.createElement('p');
        const strong = document.createElement('strong');
        strong.textContent = label;
        heading.appendChild(strong);
        const pre = document.createElement('pre');
        pre.className = 'admin-code-block';
        if (useCode) {
            const code = document.createElement('code');
            code.textContent = String(value);
            pre.appendChild(code);
        } else {
            pre.textContent = String(value);
        }
        resultBody.append(heading, pre);
    }

    function resetExecuteButton() {
        executeBtn.disabled = false;
        executeBtn.innerHTML = '<i class="bi bi-check-circle" aria-hidden="true"></i> Подтвердить и выполнить вайп';
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        if (!confirm('Вы уверены, что хотите выполнить вайп? Это действие нельзя отменить!')) {
            return;
        }

        executeBtn.disabled = true;
        executeBtn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Выполняется...';

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
            resultCard.hidden = false;
            resultBody.replaceChildren();
            resultHeader.classList.toggle('wipe-result-header--success', Boolean(data.success));
            resultHeader.classList.toggle('wipe-result-header--danger', !data.success);
            
            if (data.success) {
                appendAlert('success', 'bi bi-check-circle', 'Вайп успешно выполнен!');
                appendResultBlock('Команда:', data.command, true);
                appendResultBlock('Результат:', data.result, false);
            } else {
                appendAlert('danger', 'bi bi-x-circle', 'Ошибка выполнения вайпа!');
                const message = document.createElement('p');
                message.textContent = String(data.message || 'Неизвестная ошибка');
                resultBody.appendChild(message);
                appendResultBlock('Команда:', data.command, true);
            }

            resetExecuteButton();
        })
        .catch(error => {
            resultCard.hidden = false;
            resultBody.replaceChildren();
            resultHeader.classList.remove('wipe-result-header--success');
            resultHeader.classList.add('wipe-result-header--danger');
            appendAlert('danger', 'bi bi-x-circle', 'Ошибка выполнения вайпа!');
            const message = document.createElement('p');
            message.textContent = 'Произошла ошибка: ' + error.message;
            resultBody.appendChild(message);
            resetExecuteButton();
        });
    });
});
</script>



