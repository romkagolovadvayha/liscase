<?php

use common\models\servers\Servers;
use yii\bootstrap5\Html;

/** @var Servers[] $servers */
/** @var array $serverOptions */
/** @var array $selectedServerTags */
/** @var array $availableWipes */
/** @var string $wipe */
/** @var array|null $plan */

$this->title = Yii::t('common', 'Начисления за ТОП по вайпу');
?>

<div class="content-header">
    <h1><?= Html::encode($this->title) ?></h1>
</div>

<div class="content">
    <?= \frontend\widgets\Alert::widget() ?>

    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">
                <i class="bi bi-filter-circle"></i> <?= Yii::t('common', 'Параметры начисления') ?>
            </h5>
        </div>
        <div class="ds-card__body">
            <form method="get" action="/wipe/top-rewards">
                <div class="mb-3">
                    <label for="wipe" class="form-label"><?= Yii::t('common', 'Вайп') ?></label>
                    <input
                        class="form-control"
                        id="wipe"
                        name="wipe"
                        list="wipe-list"
                        value="<?= Html::encode($wipe) ?>"
                        placeholder="2026-02-15/2026-03-01"
                        required
                    >
                    <datalist id="wipe-list">
                        <?php foreach ($availableWipes as $availableWipe): ?>
                            <option value="<?= Html::encode($availableWipe) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="mb-3">
                    <label for="server_tags" class="form-label"><?= Yii::t('common', 'Серверы') ?></label>
                    <select class="form-select" id="server_tags" name="server_tags[]" multiple size="8">
                        <?php foreach ($serverOptions as $tag => $title): ?>
                            <option value="<?= Html::encode($tag) ?>" <?= in_array($tag, $selectedServerTags, true) ? 'selected' : '' ?>>
                                <?= Html::encode($title) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted"><?= Yii::t('common', 'Если ничего не выбрать, будут использованы все активные серверы.') ?></small>
                </div>

                <button type="submit" class="ds-btn ds-btn--primary">
                    <i class="bi bi-search"></i> <?= Yii::t('common', 'Посмотреть начисления') ?>
                </button>
                <?= Html::a('<i class="bi bi-arrow-left"></i> ' . Yii::t('common', 'Назад'), ['/wipe/index'], ['class' => 'ds-btn ds-btn--secondary']) ?>
            </form>
        </div>
    </div>

    <?php if (!empty($plan)): ?>
        <div class="ds-card mb-4">
            <div class="ds-card__header">
                <h5 class="ds-card__header-title">
                    <i class="bi bi-table"></i> <?= Yii::t('common', 'Предпросмотр начислений') ?>
                </h5>
            </div>
            <div class="ds-card__body">
                <div class="mb-3">
                    <div><strong><?= Yii::t('common', 'Вайп:') ?></strong> <?= Html::encode($plan['wipe']) ?></div>
                    <div><strong><?= Yii::t('common', 'Всего строк:') ?></strong> <?= (int)$plan['totalCount'] ?></div>
                    <div><strong><?= Yii::t('common', 'К начислению:') ?></strong> <?= (int)$plan['payableCount'] ?></div>
                    <div><strong><?= Yii::t('common', 'Пропущено:') ?></strong> <?= (int)$plan['skippedCount'] ?></div>
                    <div><strong><?= Yii::t('common', 'Сумма к начислению:') ?></strong> <?= (int)$plan['payableAmount'] ?> РУБ</div>
                </div>

                <div class="table-responsive">
                    <table class="table table-dark table-striped table-hover align-middle">
                        <thead>
                        <tr>
                            <th><?= Yii::t('common', 'Сервер') ?></th>
                            <th><?= Yii::t('common', 'Категория') ?></th>
                            <th><?= Yii::t('common', 'Место') ?></th>
                            <th><?= Yii::t('common', 'Игрок') ?></th>
                            <th><?= Yii::t('common', 'SteamID') ?></th>
                            <th><?= Yii::t('common', 'Сумма') ?></th>
                            <th><?= Yii::t('common', 'Статус') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($plan['rows'] as $row): ?>
                            <tr>
                                <td><?= Html::encode($row['server_name']) ?> (<?= Html::encode($row['server_tag']) ?>)</td>
                                <td><?= Html::encode($row['label']) ?></td>
                                <td>#<?= (int)$row['position'] ?></td>
                                <td><?= Html::encode($row['username']) ?></td>
                                <td><code><?= Html::encode($row['steam_id']) ?></code></td>
                                <td><?= (int)$row['amount'] ?> РУБ</td>
                                <td>
                                    <?php if ($row['can_pay']): ?>
                                        <span class="badge bg-success"><?= Yii::t('common', 'Будет начислено') ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><?= Html::encode($row['skip_reason']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ((int)$plan['payableCount'] > 0): ?>
                    <form method="post" action="/wipe/top-rewards" class="mt-3">
                        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                        <?= Html::hiddenInput('confirm', '1') ?>
                        <?= Html::hiddenInput('wipe', $wipe) ?>
                        <?php foreach ($selectedServerTags as $tag): ?>
                            <?= Html::hiddenInput('server_tags[]', $tag) ?>
                        <?php endforeach; ?>

                        <button
                            type="submit"
                            class="ds-btn ds-btn--success"
                            onclick="return confirm('Начислить выплаты за выбранный вайп?')"
                        >
                            <i class="bi bi-check-circle"></i> <?= Yii::t('common', 'Подтвердить и начислить') ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
