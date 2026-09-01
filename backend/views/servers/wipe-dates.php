<?php

use common\models\servers\Servers;
use yii\bootstrap5\Html;

/** @var Servers[] $servers */
/** @var array<int, array{wipe: string, next_wipe: string, global_wipe: string}> $suggestions */

$this->title = Yii::t('common', 'Даты вайпов');
?>

<div class="content-header">
    <h1><?= Html::encode($this->title) ?></h1>
</div>

<div class="content">
    <div class="ds-card">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">
                <i class="bi bi-calendar3"></i> <?= Yii::t('common', 'Сервера и даты вайпов') ?>
            </h5>
        </div>
        <div class="ds-card__body">
            <div class="table-responsive">
                <table class="table table-striped align-middle" aria-label="<?= Html::encode(Yii::t('common', 'Сервера и даты вайпов')) ?>">
                    <thead>
                    <tr>
                        <th scope="col"><?= Yii::t('common', 'Сервер') ?></th>
                        <th scope="col"><?= Yii::t('common', 'wipe') ?></th>
                        <th scope="col"><?= Yii::t('common', 'next_wipe') ?></th>
                        <th scope="col"><?= Yii::t('common', 'global_wipe') ?></th>
                        <th scope="col"><?= Yii::t('common', 'Действие') ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($servers as $server): ?>
                        <?php
                        $sid = (int) $server->id;
                        $preset = $suggestions[$sid] ?? ['wipe' => '', 'next_wipe' => '', 'global_wipe' => ''];
                        ?>
                        <tr id="wipe-row-<?= $sid ?>">
                            <td>
                                <strong><?= Html::encode($server->name) ?></strong><br>
                                <small class="text-muted"><?= Html::encode($server->tag) ?></small>
                            </td>
                            <td>
                                <label class="visually-hidden" for="wipe-<?= $sid ?>"><?= Html::encode($server->name) ?> — <?= Yii::t('common', 'Дата текущего вайпа') ?></label>
                                <input type="text"
                                       id="wipe-<?= $sid ?>"
                                       class="form-control"
                                       data-role="wipe"
                                       aria-describedby="current-wipe-<?= $sid ?>"
                                       value="<?= Html::encode($preset['wipe']) ?>"
                                       placeholder="YYYY-MM-DD HH:MM:SS">
                                <small class="text-muted d-block mt-1">
                                    Текущее: <span id="current-wipe-<?= $sid ?>" data-current="wipe"><?= Html::encode((string) $server->wipe) ?></span>
                                </small>
                            </td>
                            <td>
                                <label class="visually-hidden" for="next-wipe-<?= $sid ?>"><?= Html::encode($server->name) ?> — <?= Yii::t('common', 'Дата следующего вайпа') ?></label>
                                <input type="text"
                                       id="next-wipe-<?= $sid ?>"
                                       class="form-control"
                                       data-role="next_wipe"
                                       aria-describedby="current-next-wipe-<?= $sid ?>"
                                       value="<?= Html::encode($preset['next_wipe']) ?>"
                                       placeholder="YYYY-MM-DD HH:MM:SS">
                                <small class="text-muted d-block mt-1">
                                    Текущее: <span id="current-next-wipe-<?= $sid ?>" data-current="next_wipe"><?= Html::encode((string) $server->next_wipe) ?></span>
                                </small>
                            </td>
                            <td>
                                <label class="visually-hidden" for="global-wipe-<?= $sid ?>"><?= Html::encode($server->name) ?> — <?= Yii::t('common', 'Дата глобального вайпа') ?></label>
                                <input type="text"
                                       id="global-wipe-<?= $sid ?>"
                                       class="form-control"
                                       data-role="global_wipe"
                                       aria-describedby="current-global-wipe-<?= $sid ?>"
                                       value="<?= Html::encode($preset['global_wipe']) ?>"
                                       placeholder="YYYY-MM-DD HH:MM:SS">
                                <small class="text-muted d-block mt-1">
                                    Текущее: <span id="current-global-wipe-<?= $sid ?>" data-current="global_wipe"><?= Html::encode((string) $server->global_wipe) ?></span>
                                </small>
                            </td>
                            <td>
                                <button type="button"
                                        class="ds-btn ds-btn--success js-save-wipe-row"
                                        data-server-id="<?= $sid ?>">
                                    <i class="bi bi-save" aria-hidden="true"></i> <?= Yii::t('common', 'Сохранить') ?>
                                </button>
                                <div class="mt-2 small" data-role="result" role="status" aria-live="polite"></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = '<?= Yii::$app->request->csrfToken ?>';

    function setResult(row, message, success) {
        const target = row.querySelector('[data-role="result"]');
        target.textContent = message;
        target.classList.remove('text-success', 'text-danger');
        target.classList.add(success ? 'text-success' : 'text-danger');
    }

    document.querySelectorAll('.js-save-wipe-row').forEach(function (button) {
        button.addEventListener('click', function () {
            const serverId = button.getAttribute('data-server-id');
            const row = document.getElementById('wipe-row-' + serverId);
            const wipe = row.querySelector('[data-role="wipe"]').value.trim();
            const nextWipe = row.querySelector('[data-role="next_wipe"]').value.trim();
            const globalWipe = row.querySelector('[data-role="global_wipe"]').value.trim();

            button.disabled = true;
            setResult(row, 'Сохранение...', true);

            const formData = new URLSearchParams();
            formData.append('_csrf', csrfToken);
            formData.append('server_id', serverId);
            formData.append('wipe', wipe);
            formData.append('next_wipe', nextWipe);
            formData.append('global_wipe', globalWipe);

            fetch('/servers/save-wipe-dates-row', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData.toString(),
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (data.success) {
                        setResult(row, data.message || 'Сохранено', true);
                        if (data.current) {
                            row.querySelector('[data-current="wipe"]').textContent = data.current.wipe || '';
                            row.querySelector('[data-current="next_wipe"]').textContent = data.current.next_wipe || '';
                            row.querySelector('[data-current="global_wipe"]').textContent = data.current.global_wipe || '';
                        }
                    } else {
                        setResult(row, data.message || 'Ошибка сохранения', false);
                    }
                })
                .catch(function (error) {
                    setResult(row, 'Ошибка: ' + error.message, false);
                })
                .finally(function () {
                    button.disabled = false;
                });
        });
    });
});
</script>
