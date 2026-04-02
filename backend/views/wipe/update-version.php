<?php

use common\models\servers\Servers;
use yii\bootstrap5\Html;

/** @var Servers[] $servers */

$this->title = Yii::t('common', 'Обновление (FTP)');

$updateResults = Yii::$app->session->getFlash('updateVersionResults');
?>

<div class="content-header">
    <h1><?= Html::encode($this->title) ?></h1>
</div>

<div class="content">
    <?= \frontend\widgets\Alert::widget() ?>

    <?php if (is_array($updateResults) && $updateResults !== []): ?>
        <div class="ds-card mb-4">
            <div class="ds-card__header">
                <h5 class="ds-card__header-title">
                    <i class="bi bi-list-check"></i> <?= Yii::t('common', 'Результат') ?>
                </h5>
            </div>
            <div class="ds-card__body">
                <?php foreach ($updateResults as $block): ?>
                    <div class="mb-4 pb-3 border-bottom border-secondary">
                        <div class="fw-semibold mb-1">
                            <?= Html::encode($block['name'] ?? '') ?>
                            <span class="text-muted small">(<?= Html::encode($block['tag'] ?? '') ?>)</span>
                            <?php if (empty($block['ok'])): ?>
                                <span class="badge bg-danger ms-1"><?= Yii::t('common', 'проблема') ?></span>
                            <?php else: ?>
                                <span class="badge bg-success ms-1">OK</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($block['message'])): ?>
                            <p class="text-muted small mb-2"><?= Html::encode($block['message']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($block['renamed'])): ?>
                            <div class="small text-success mb-1"><?= Yii::t('common', 'Переименовано:') ?></div>
                            <ul class="small mb-2">
                                <?php foreach ($block['renamed'] as $line): ?>
                                    <li><code><?= Html::encode($line) ?></code></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (!empty($block['skipped'])): ?>
                            <div class="small text-warning mb-1"><?= Yii::t('common', 'Не выполнено:') ?></div>
                            <ul class="small mb-0">
                                <?php foreach ($block['skipped'] as $line): ?>
                                    <li><code><?= Html::encode($line) ?></code></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">
                <i class="bi bi-arrow-up-circle"></i> <?= Yii::t('common', 'Смена номера версии в именах файлов') ?>
            </h5>
        </div>
        <div class="ds-card__body">
            <p class="text-muted small">
                <?= Yii::t('common', 'Обрабатывается только каталог из поля сервера «FTP корневой каталог» (ftp_root_path): файлы прямо в нём, без вложенных папок. В имени ищется номер версии как отдельное число (например relationship.282.db → relationship.283.db). Сначала нажмите «Просмотр», затем на следующей странице — «Подтвердить переименование».') ?>
            </p>

            <?= Html::beginForm(['preview-update-version'], 'post', ['class' => 'wipe-update-version-form']) ?>
            <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="previous_version"><?= Yii::t('common', 'Предыдущая версия') ?></label>
                    <input type="text" class="form-control" id="previous_version" name="previous_version"
                           inputmode="numeric" pattern="\d+" required placeholder="282">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="new_version"><?= Yii::t('common', 'Новая версия') ?></label>
                    <input type="text" class="form-control" id="new_version" name="new_version"
                           inputmode="numeric" pattern="\d+" required placeholder="283">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= Yii::t('common', 'Серверы') ?></label>
                <div class="border rounded p-3 bg-dark bg-opacity-25" style="max-height: 280px; overflow-y: auto;">
                    <?php foreach ($servers as $server): ?>
                        <?php
                        $hasFtp = $server->hasFtpCredentials();
                        $cbId = 'srv_' . (int)$server->id;
                        ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="server_ids[]"
                                   value="<?= (int)$server->id ?>" id="<?= $cbId ?>"
                                <?= $hasFtp ? '' : 'disabled' ?>>
                            <label class="form-check-label <?= $hasFtp ? '' : 'text-muted' ?>" for="<?= $cbId ?>">
                                <?= Html::encode($server->name) ?>
                                <span class="text-muted small">(<?= Html::encode($server->tag) ?>)</span>
                                <?php if (!$hasFtp): ?>
                                    <span class="badge bg-secondary ms-1"><?= Yii::t('common', 'нет FTP') ?></span>
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <small class="text-muted"><?= Yii::t('common', 'Доступны только серверы с заполненными FTP логином и паролем.') ?></small>
            </div>

            <button type="submit" class="ds-btn ds-btn--primary">
                <i class="bi bi-eye"></i> <?= Yii::t('common', 'Просмотр') ?>
            </button>
            <?= Html::a('<i class="bi bi-arrow-left"></i> ' . Yii::t('common', 'Назад'), ['/wipe/index'], ['class' => 'ds-btn ds-btn--secondary']) ?>

            <?= Html::endForm() ?>
        </div>
    </div>
</div>
