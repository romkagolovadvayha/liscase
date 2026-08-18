<?php

use common\models\tournament\CashRaceScore;
use common\models\tournament\CashRaceTournament;
use common\models\tournament\Tournament;
use yii\data\ActiveDataProvider;
use yii\helpers\Html;
use yii\widgets\LinkPager;

/** @var Tournament $model */
/** @var CashRaceTournament|null $config */
/** @var ActiveDataProvider $dataProvider */
/** @var array $totals */
/** @var string $search */
/** @var bool $canManageScores */
/** @var array<int, int> $heldByUser */

$this->title = 'Игроки: ' . $model->title;
$editable = $canManageScores && $config && !$config->awards_issued_at;
$pagination = $dataProvider->getPagination();
$currentPage = $pagination ? $pagination->getPage() + 1 : 1;
?>

<div class="cash-race-players-page">
    <header class="cash-race-players-hero">
        <div class="cash-race-players-hero__copy">
            <span class="cash-race-players-hero__icon" aria-hidden="true"><i class="fas fa-users"></i></span>
            <div>
                <p class="cash-race-players-hero__eyebrow"><?= Html::encode($model->server ? $model->server->name : 'Денежная гонка') ?></p>
                <h1>Игроки и ключи</h1>
                <p>Здесь отображаются игроки, которые нашли хотя бы один ключ в этой гонке.</p>
            </div>
        </div>
        <div class="cash-race-players-summary" aria-label="Сводка турнира">
            <div><strong><?= number_format((int)($totals['players'] ?? 0), 0, ',', ' ') ?></strong><span>игроков</span></div>
            <div><strong><?= number_format((int)($totals['found'] ?? 0), 0, ',', ' ') ?></strong><span>найдено</span></div>
            <div><strong><?= number_format((int)($totals['deposited'] ?? 0), 0, ',', ' ') ?></strong><span>засчитано</span></div>
        </div>
    </header>

    <div class="cash-race-players-notice">
        <i class="fas fa-info-circle" aria-hidden="true"></i>
        <p><strong>Ручная корректировка меняет статистику и место в рейтинге.</strong> Она не создаёт и не удаляет предметы в инвентаре Rust. После начисления медалей результаты блокируются.</p>
    </div>

    <section class="cash-race-players-panel" aria-labelledby="cash-race-players-table-title">
        <header class="cash-race-players-panel__toolbar">
            <div>
                <h2 id="cash-race-players-table-title">Участники</h2>
                <p><?= number_format($dataProvider->getTotalCount(), 0, ',', ' ') ?> найдено по текущему фильтру</p>
            </div>
            <?= Html::beginForm(['players', 'id' => $model->id], 'get', ['class' => 'cash-race-players-search']) ?>
                <label class="sr-only" for="cash-race-player-search">Найти игрока</label>
                <i class="fas fa-search" aria-hidden="true"></i>
                <?= Html::textInput('q', $search, [
                    'id' => 'cash-race-player-search',
                    'class' => 'ds-input',
                    'placeholder' => 'Ник или Steam ID',
                    'autocomplete' => 'off',
                ]) ?>
                <?= Html::submitButton('Найти', ['class' => 'ds-btn ds-btn--primary']) ?>
                <?php if ($search !== ''): ?>
                    <?= Html::a('Сбросить', ['players', 'id' => $model->id], ['class' => 'ds-btn ds-btn--secondary']) ?>
                <?php endif; ?>
            <?= Html::endForm() ?>
        </header>

        <div class="cash-race-players-table-wrap scrollbar-thin">
            <table class="cash-race-players-table">
                <thead>
                    <tr>
                        <th>Игрок</th>
                        <th>Найдено</th>
                        <th>Потеряно</th>
                        <th>Засчитано</th>
                        <th>На руках</th>
                        <th>Последний ключ</th>
                        <?php if ($canManageScores): ?><th><span class="sr-only">Действие</span></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($dataProvider->getModels() as $score): ?>
                    <?php
                    /** @var CashRaceScore $score */
                    $user = $score->user;
                    $avatar = $user ? $user->getAvatar() : '';
                    $formId = 'cash-race-score-' . (int)$score->id;
                    $held = (int)($heldByUser[(int)$score->user_id] ?? 0);
                    ?>
                    <tr>
                        <td>
                            <div class="cash-race-player-cell">
                                <?php if ($avatar): ?>
                                    <?= Html::img($avatar, ['alt' => '', 'loading' => 'lazy']) ?>
                                <?php else: ?>
                                    <span class="cash-race-player-cell__fallback" aria-hidden="true"><i class="fas fa-user"></i></span>
                                <?php endif; ?>
                                <span>
                                    <strong><?= Html::encode($user ? $user->username : 'Игрок #' . $score->user_id) ?></strong>
                                    <small><?= Html::encode($score->steam_id) ?></small>
                                </span>
                            </div>
                        </td>
                        <td>
                            <?php if ($editable): ?>
                                <?= Html::input('number', 'keys_found', (int)$score->keys_found, [
                                    'class' => 'ds-input cash-race-score-input', 'min' => 0, 'max' => 1000000,
                                    'form' => $formId, 'aria-label' => 'Найдено: ' . ($user ? $user->username : $score->steam_id),
                                ]) ?>
                            <?php else: ?><strong><?= number_format((int)$score->keys_found, 0, ',', ' ') ?></strong><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($editable): ?>
                                <?= Html::input('number', 'keys_lost', (int)$score->keys_lost, [
                                    'class' => 'ds-input cash-race-score-input', 'min' => 0, 'max' => 1000000,
                                    'form' => $formId, 'aria-label' => 'Потеряно: ' . ($user ? $user->username : $score->steam_id),
                                ]) ?>
                            <?php else: ?><strong><?= number_format((int)$score->keys_lost, 0, ',', ' ') ?></strong><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($editable): ?>
                                <?= Html::input('number', 'keys_deposited', (int)$score->keys_deposited, [
                                    'class' => 'ds-input cash-race-score-input cash-race-score-input--accent', 'min' => 0, 'max' => 1000000,
                                    'form' => $formId, 'aria-label' => 'Засчитано: ' . ($user ? $user->username : $score->steam_id),
                                ]) ?>
                            <?php else: ?><strong class="cash-race-score-accent"><?= number_format((int)$score->keys_deposited, 0, ',', ' ') ?></strong><?php endif; ?>
                        </td>
                        <td><strong><?= number_format($held, 0, ',', ' ') ?></strong></td>
                        <td><time datetime="<?= Html::encode((string)$score->last_found_at) ?>"><?= $score->last_found_at ? date('d.m.Y H:i', strtotime($score->last_found_at)) : 'Нет данных' ?></time></td>
                        <?php if ($canManageScores): ?>
                            <td class="cash-race-players-table__action">
                                <?php if ($editable): ?>
                                    <?= Html::beginForm(['score-update', 'id' => $model->id, 'scoreId' => $score->id], 'post', ['id' => $formId]) ?>
                                        <?= Html::hiddenInput('return_q', $search) ?>
                                        <?= Html::hiddenInput('return_page', $currentPage) ?>
                                        <?= Html::submitButton('<i class="fas fa-save" aria-hidden="true"></i><span>Сохранить</span>', [
                                            'class' => 'ds-btn ds-btn--primary ds-btn--sm',
                                            'title' => 'Сохранить статистику игрока',
                                        ]) ?>
                                    <?= Html::endForm() ?>
                                <?php else: ?>
                                    <span class="cash-race-score-locked" title="Результаты уже зафиксированы"><i class="fas fa-lock" aria-hidden="true"></i></span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$dataProvider->getCount()): ?>
                    <tr><td colspan="<?= $canManageScores ? 7 : 6 ?>" class="cash-race-players-empty"><i class="fas fa-key" aria-hidden="true"></i><strong>Игроки не найдены</strong><span>Здесь появятся участники после первого найденного ключа.</span></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pagination && $pagination->getPageCount() > 1): ?>
            <footer class="cash-race-players-panel__pagination">
                <?= LinkPager::widget(['pagination' => $pagination]) ?>
            </footer>
        <?php endif; ?>
    </section>
</div>
