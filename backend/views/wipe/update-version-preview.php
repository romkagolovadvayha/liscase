<?php

use yii\bootstrap5\Html;

/** @var array $preview */

$this->title = Yii::t('common', 'Предпросмотр переименования');

$totalRenames = 0;
foreach ($preview['blocks'] ?? [] as $b) {
    if (!empty($b['ok']) && !empty($b['pairs'])) {
        $totalRenames += count($b['pairs']);
    }
}
?>

<div class="content-header">
    <h1><?= Html::encode($this->title) ?></h1>
</div>

<div class="content">
    <?= \frontend\widgets\Alert::widget() ?>

    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">
                <i class="bi bi-eye"></i> <?= Yii::t('common', 'Что будет переименовано') ?>
            </h5>
        </div>
        <div class="ds-card__body">
            <p class="mb-3">
                <strong><?= Yii::t('common', 'Предыдущая версия:') ?></strong>
                <code><?= Html::encode($preview['previous_version'] ?? '') ?></code>
                &nbsp;→&nbsp;
                <strong><?= Yii::t('common', 'Новая версия:') ?></strong>
                <code><?= Html::encode($preview['new_version'] ?? '') ?></code>
            </p>

            <?php foreach ($preview['blocks'] ?? [] as $block): ?>
                <div class="mb-4 pb-3 border-bottom border-secondary">
                    <div class="fw-semibold mb-2">
                        <?= Html::encode($block['name'] ?? '') ?>
                        <span class="text-muted small">(<?= Html::encode($block['tag'] ?? '') ?>)</span>
                        <?php if (empty($block['ok'])): ?>
                            <span class="badge bg-danger ms-1"><?= Yii::t('common', 'недоступно') ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($block['error'])): ?>
                        <p class="text-danger small mb-0"><?= Html::encode($block['error']) ?></p>
                    <?php elseif (empty($block['pairs'])): ?>
                        <p class="text-muted small mb-0"><?= Yii::t('common', 'Нет файлов для переименования в ftp_root_path.') ?></p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-dark table-bordered mb-0">
                                <thead>
                                <tr>
                                    <th><?= Yii::t('common', 'Было') ?></th>
                                    <th><?= Yii::t('common', 'Станет') ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($block['pairs'] as $pair): ?>
                                    <tr>
                                        <td><code class="text-break"><?= Html::encode($pair['from'] ?? '') ?></code></td>
                                        <td><code class="text-break"><?= Html::encode($pair['to'] ?? '') ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php if ($totalRenames === 0): ?>
                <p class="text-warning mb-3"><?= Yii::t('common', 'Нет ни одного файла для переименования. Вернитесь и проверьте версии и серверы.') ?></p>
            <?php endif; ?>

            <div class="d-flex flex-wrap gap-2 align-items-center">
                <?php if ($totalRenames > 0): ?>
                    <?= Html::beginForm(['execute-update-version'], 'post') ?>
                    <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                    <button type="submit" class="ds-btn ds-btn--danger">
                        <i class="bi bi-check2-circle"></i> <?= Yii::t('common', 'Подтвердить переименование') ?>
                    </button>
                    <?= Html::endForm() ?>
                <?php endif; ?>

                <?= Html::a('<i class="bi bi-arrow-left"></i> ' . Yii::t('common', 'Изменить параметры'), ['/wipe/update-version'], ['class' => 'ds-btn ds-btn--secondary']) ?>
            </div>
        </div>
    </div>
</div>
