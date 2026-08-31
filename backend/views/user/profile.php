<?php

use common\models\servers\Servers;
use common\models\user\User;
use backend\forms\userProfile\RoleForm;
use backend\forms\userProfile\BonusForm;
use backend\forms\userProfile\PayoutForm;
use backend\forms\userProfile\BalanceTransferForm;
use backend\forms\userProfile\UserDropTransferForm;
use common\components\helpers\Role;
use common\models\user\UserProfile;
use common\models\user\UserTree;
use common\models\invoice\Deposit;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use frontend\widgets\Alert;
/** @var User $user */
/** @var RoleForm $roleForm */
/** @var BonusForm $bonusForm */
/** @var PayoutForm $payoutForm */
/** @var BalanceTransferForm $balanceTransferForm */
/** @var UserDropTransferForm $userDropTransferForm */

$this->title = Html::encode($user->username);

$userProfile = $user->userProfile;

$usersTree = UserTree::find()
    ->andWhere(['parent_user_id' => $user->id])
    ->andWhere(['NOT IN', 'user_id', [$user->id]])
    ->limit(50)
    ->all();

$total = 0;
foreach ($usersTree as $userTree) {
    foreach ($userTree->user->deposits as $deposit) {
        if ($deposit->status === Deposit::STATUS_SUCCESS) {
            $total += $deposit->amount;
        }
    }
}

$payoutSum = \common\models\user\UserPayoutReferral::find()
    ->andWhere(['user_id' => $user->id])
    ->sum('amount') ?? 0;
$payoutTotal = $total * ($user->userProfile->referral_bonus / 100) - $payoutSum;

$statusBadgeClass = $user->status === User::STATUS_ACTIVE ? 'ds-badge--success' : 'ds-badge--danger';
$displayStatus = $user->getDisplayStatus();
$onlineLabel = $displayStatus === null ? Yii::t('common', 'Скрыт') : ($displayStatus ? Yii::t('common', 'Онлайн') : Yii::t('common', 'Офлайн'));
$onlineBadgeClass = $displayStatus === null ? 'ds-badge--secondary' : ($displayStatus ? 'ds-badge--success' : 'ds-badge--danger');

$servers = Servers::find()
    ->cache(30)
    ->andWhere(['status' => Servers::STATUS_ACTIVE])
    ->orderBy(['sort' => SORT_ASC])
    ->all();

$teams = [];
foreach ($servers as $server) {
    $teams = array_merge($teams, \common\models\statistics\Teams::getAllInTeams($server, $user->steam_id));
}

$teamsProvider = new \yii\data\ArrayDataProvider([
    'allModels' => $teams,
    'totalCount' => count($teams),
    'pagination' => ['pageSize' => 30],
]);

$bans = [];
$lastCheck = [];
$bansOtherProjectProvider = new \yii\data\ArrayDataProvider(['allModels' => $bans, 'totalCount' => 0, 'pagination' => ['pageSize' => 30]]);
$checkingOtherProjectProvider = new \yii\data\ArrayDataProvider(['allModels' => $lastCheck, 'totalCount' => 0, 'pagination' => ['pageSize' => 30]]);

// В стиле админки: те же классы, что в user/index и drop/index
$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wider bg-[hsl(0_0%_15.3%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-zinc-100 border-b border-[hsl(0_0%_15.3%_/_1)]';
$sectionClass = 'bg-[hsl(0_0%_11.8%_/_1)] overflow-hidden';
$sectionHeaderClass = 'px-4 py-3 text-xs font-semibold text-zinc-400 uppercase tracking-wider border-b border-[hsl(0_0%_15.3%_/_1)]';
$borderDivider = 'border-[hsl(0_0%_15.3%_/_1)]';
?>

<div class="user-profile-page w-full min-h-0 flex flex-col flex-1">
    <?= Alert::widget() ?>

    <!-- Затемнение под выдвижную панель (только мобилка) -->
    <div class="user-profile-sidebar-backdrop lg:hidden" id="user-profile-sidebar-backdrop" aria-hidden="true"></div>

    <!-- Основная колонка + сайдбар «Параметры» справа -->
    <div class="user-profile-layout flex flex-col lg:flex-row lg:items-stretch flex-1 min-h-0 w-full gap-4 lg:gap-6 p-4 sm:p-6">
        <div class="user-profile-content flex-1 min-w-0 space-y-6 w-full">
            <!-- Мобилка: кнопка открытия панели (на ПК скрыто CSS-классом user-profile-params-toolbar-mobile) -->
            <div class="user-profile-params-toolbar-mobile sticky top-0 z-20 -mx-4 sm:-mx-6 px-4 sm:px-6 py-2 mb-2 flex justify-end bg-[hsl(0_0%_10%_/_1)]/95 backdrop-blur-sm border-b border-[hsl(0_0%_15.3%_/_1)]">
                <button type="button" id="user-profile-sidebar-toggle" class="ds-btn ds-btn--secondary ds-btn--sm inline-flex items-center gap-2 min-h-[44px] min-w-[44px]" aria-label="<?= Html::encode(Yii::t('common', 'Параметры и блоки')) ?>" aria-expanded="false" aria-controls="user-profile-sidebar">
                    <i class="fas fa-sliders-h" aria-hidden="true"></i>
                    <span><?= Yii::t('common', 'Параметры') ?></span>
                </button>
            </div>
            <!-- Верхняя строка: карточка пользователя слева, Финансы справа -->
            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Карточка пользователя: аватар, ник, Steam, бейджи; кнопки под блоком -->
                <div class="<?= $sectionClass ?> flex-1 min-w-0">
                    <div class="p-4">
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="flex-shrink-0 rounded-lg overflow-hidden ring-1 ring-[hsl(0_0%_15.3%_/_1)]" style="width:100px;height:100px;">
                                <img src="<?= $user->getAvatar() ?: 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><rect fill=%22%23333%22 width=%22100%22 height=%22100%22/><text x=%2250%22 y=%2255%22 fill=%22%23888%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2240%22>?</text></svg>' ?>"
                                     alt="<?= Html::encode($user->username) ?>"
                                     width="100"
                                     height="100"
                                     loading="lazy"
                                     style="width:100px;height:100px;object-fit:cover;object-position:center;display:block;">
                            </div>
                            <div class="min-w-0 flex-1">
                                <h2 class="text-lg font-semibold text-zinc-100 truncate"><?= Html::encode($user->username) ?></h2>
                                <a href="https://steamcommunity.com/profiles/<?= $user->steam_id ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-sm text-zinc-400 hover:text-blue-400 truncate max-w-full mt-0.5">
                                    <i class="fab fa-steam text-zinc-500 flex-shrink-0"></i>
                                    <span class="truncate"><?= $user->steam_id ?></span>
                                </a>
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    <span class="ds-badge <?= $statusBadgeClass ?>"><?= \yii\helpers\ArrayHelper::getValue(User::getStatusList(), $user->status) ?></span>
                                    <span class="ds-badge <?= $onlineBadgeClass ?>"><?= $onlineLabel ?></span>
                                    <?php if ($user->auto): ?>
                                        <span class="ds-badge ds-badge--info"><?= Yii::t('common', 'Авто') ?></span>
                                    <?php endif; ?>
                                    <?php if ($user->hasVip()): ?>
                                        <span class="ds-badge ds-badge--warning"><?= Yii::t('common', 'VIP') ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3 pt-3 border-t <?= $borderDivider ?>">
                            <?php if (Yii::$app->user->can(Role::ROLE_ADMIN)): ?>
                            <button type="button" class="ds-btn ds-btn--secondary ds-btn--sm !border-0" data-bs-toggle="modal" data-bs-modal-form="role_form" data-bs-target="#modalForm">
                                <i class="fas fa-user-shield"></i> <?= Yii::t('common', 'Роль') ?>
                            </button>
                            <?php endif; ?>
                            <?php if (Yii::$app->user->can(Role::ROLE_ADMIN) || Yii::$app->user->can(Role::ROLE_MODERATOR)): ?>
                            <button type="button" class="ds-btn ds-btn--secondary ds-btn--sm !border-0" data-bs-toggle="modal" data-bs-modal-form="user_drop_transfer_form" data-bs-target="#modalForm">
                                <i class="fas fa-boxes" aria-hidden="true"></i> <?= Yii::t('common', 'Перенести предметы') ?>
                            </button>
                            <?php endif; ?>
                            <?php if (!empty($user->server)): ?>
                            <?= Html::a('<i class="fas fa-chart-line"></i> ' . Yii::t('common', 'Статистика'), ['/statistics/index', 'StatisticsSearch' => ['steam_id' => $user->steam_id, 'server_tag' => $user->server->tag, 'wipe' => $user->server->currentWipe()]], ['class' => 'ds-btn ds-btn--secondary ds-btn--sm !border-0']) ?>
                            <?= Html::a('<i class="fas fa-trophy"></i> ' . Yii::t('common', 'Топ'), ['/user-top/index', 'UserTopSearch' => ['user_id' => $user->id, 'server_id' => $user->server->id, 'wipe' => $user->server->currentWipe()]], ['class' => 'ds-btn ds-btn--secondary ds-btn--sm !border-0']) ?>
                            <?php if ((Yii::$app->user->can(Role::ROLE_ADMIN) || Yii::$app->user->can(Role::ROLE_MODERATOR)) && $user->hasVip()): ?>
                            <?= Html::a('<i class="fas fa-crown"></i> ' . Yii::t('common', 'Выдать VIP на сервере'), ['/user/run-vip-on-server', 'userId' => $user->id], ['class' => 'ds-btn ds-btn--secondary ds-btn--sm !border-0', 'data' => ['confirm' => Yii::t('common', 'Выполнить addgroup vip_status на сервере?'), 'method' => 'post']]) ?>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php
                $canFinanceAdmin = Yii::$app->user->can(Role::ROLE_ADMIN);
                $canFinanceModerator = Yii::$app->user->can(Role::ROLE_MODERATOR);
                $canSeeFinance = $canFinanceAdmin || $canFinanceModerator;
                ?>
                <?php if ($canSeeFinance): ?>
                <div class="<?= $sectionClass ?> lg:w-72 flex-shrink-0">
                    <h3 class="<?= $sectionHeaderClass ?>"><?= Yii::t('common', 'Финансы') ?></h3>
                    <div>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 py-3 px-4 border-b <?= $borderDivider ?>">
                            <span class="text-xs font-medium text-zinc-400 sm:min-w-[100px]"><?= Yii::t('common', 'Баланс') ?></span>
                            <span class="text-sm font-medium text-zinc-100"><?= Yii::$app->formatter->asDecimal($user->getPersonalBalance()->getBalanceCeil(), 2) ?> ₽</span>
                        </div>
                        <?php if ($canFinanceAdmin): ?>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 py-3 px-4 border-b <?= $borderDivider ?>">
                            <span class="text-xs font-medium text-zinc-400 sm:min-w-[100px]"><?= Yii::t('common', 'К выводу') ?></span>
                            <span class="text-sm font-medium text-zinc-100"><?= Yii::$app->formatter->asDecimal($payoutTotal, 2) ?> ₽</span>
                        </div>
                        <?php endif; ?>
                        <?php $skinsBalance = $user->getSkinsBalance(); ?>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 py-3 px-4 border-b <?= $borderDivider ?>">
                            <span class="text-xs font-medium text-zinc-400 sm:min-w-[100px]"><?= Yii::t('common', 'Баланс скинов') ?></span>
                            <span class="text-sm font-medium text-zinc-100"><?= $skinsBalance ? Yii::$app->formatter->asDecimal($skinsBalance->getBalanceCeil(), 2) : '0.00' ?></span>
                        </div>
                        <div class="flex flex-wrap gap-2 py-3 px-4">
                            <?php if ($canFinanceAdmin): ?>
                            <button type="button" class="ds-btn ds-btn--secondary ds-btn--sm !border-0" data-bs-toggle="modal" data-bs-modal-form="payout_form" data-bs-target="#modalForm">
                                <i class="fas fa-money-bill-wave"></i> <?= Yii::t('common', 'Вывод') ?>
                            </button>
                            <?php endif; ?>
                            <button type="button" class="ds-btn ds-btn--secondary ds-btn--sm !border-0" data-bs-toggle="modal" data-bs-modal-form="bonus_form" data-bs-target="#modalForm">
                                <i class="fas fa-coins"></i> <?= Yii::t('common', 'Пополнить') ?>
                            </button>
                            <?php if ($canFinanceAdmin || $canFinanceModerator): ?>
                            <button type="button" class="ds-btn ds-btn--secondary ds-btn--sm !border-0" data-bs-toggle="modal" data-bs-modal-form="balance_transfer_form" data-bs-target="#modalForm">
                                <i class="fas fa-exchange-alt"></i> <?= Yii::t('common', 'Перевод') ?>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Основная информация: список «метка — значение» -->
            <div class="<?= $sectionClass ?>">
                <h3 class="<?= $sectionHeaderClass ?>"><?= Yii::t('common', 'Основная информация') ?></h3>
                <div>
                    <?php
                    $infoRows = [
                        ['label' => 'ID', 'value' => (string)$user->id],
                        ['label' => Yii::t('common', 'Регистрация'), 'value' => Yii::$app->formatter->asDate($user->created_at)],
                        ['label' => Yii::t('common', 'Был на сервере'), 'value' => $user->last_visit_server_at ? Yii::$app->formatter->asRelativeTime($user->last_visit_server_at) : '—'],
                    ];
                    if ($user->server) {
                        $infoRows[] = ['label' => Yii::t('common', 'Сервер'), 'value' => $user->server->name ?? $user->server_tag];
                    }
                    if (!empty($user->email)) {
                        $infoRows[] = ['label' => 'Email', 'value' => $user->email];
                    }
                    if (!empty($user->discord_id)) {
                        $infoRows[] = ['label' => 'Discord', 'value' => $user->discord_id];
                    }
                    if (!empty($user->twitch_id)) {
                        $infoRows[] = ['label' => 'Twitch', 'value' => $user->twitch_id];
                    }
                    if (!empty($user->kick_id)) {
                        $infoRows[] = ['label' => 'Kick', 'value' => $user->kick_id];
                    }
                    if ($user->telegram_chat_id) {
                        $infoRows[] = ['label' => 'Telegram', 'value' => $user->telegram_chat_id];
                    }
                    if (Yii::$app->user->can(Role::ROLE_ADMIN) && !empty($user->ip)) {
                        $infoRows[] = ['label' => 'IP', 'value' => $user->ip, 'mono' => true];
                    }
                    if ($user->banned_at) {
                        $infoRows[] = ['label' => Yii::t('common', 'Забанен'), 'value' => Yii::$app->formatter->asDatetime($user->banned_at), 'danger' => true];
                    }
                    if ($user->unbanned_at) {
                        $infoRows[] = ['label' => Yii::t('common', 'Разбан'), 'value' => Yii::$app->formatter->asDatetime($user->unbanned_at)];
                    }
                    foreach ($infoRows as $row):
                        $value = $row['value'] ?? null;
                        $isBadge = !empty($row['badge']);
                        $text = $row['text'] ?? $value;
                    ?>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 py-3 px-4 border-b <?= $borderDivider ?> last:border-b-0">
                        <span class="text-xs font-medium text-zinc-400 sm:min-w-[140px]"><?= Html::encode($row['label']) ?></span>
                        <span class="text-sm text-zinc-100 <?= !empty($row['mono']) ? 'font-mono text-xs' : '' ?> <?= !empty($row['danger']) ? 'text-red-400' : '' ?>">
                            <?php if ($isBadge): ?>
                                <span class="ds-badge <?= $row['class'] ?? '' ?>"><?= Html::encode($text) ?></span>
                            <?php else: ?>
                                <?= Html::encode($text) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Правая колонка: на ПК — колонка; на мобилке — выезжающая панель -->
        <?php
        $boolLabels = [
            'status_banned' => Yii::t('common', 'Заблокирован на сайте'),
            'raid_notify' => Yii::t('common', 'Уведомления о рейде'),
            'ban_notify' => Yii::t('common', 'Уведомления о бане'),
            'store' => Yii::t('common', 'Доступ к магазину на без донатном сервере?'),
            'is_stats' => Yii::t('common', 'Показывать в статистике?'),
            'is_blogger' => Yii::t('common', 'Блогер'),
            'blocked_support' => Yii::t('common', 'Блок поддержки'),
        ];
        $boolAttrs = \backend\controllers\UserController::getUserBoolAttributes();
        ?>
        <div id="user-profile-sidebar-slot" class="max-lg:w-0 max-lg:min-w-0 max-lg:shrink-0 lg:flex lg:flex-shrink-0 lg:flex-col lg:min-h-0 lg:w-[min(320px,100%)] lg:self-stretch">
        <aside id="user-profile-sidebar" class="user-profile-sidebar-panel admin-filters-content flex flex-col flex-shrink-0 min-h-0 h-full bg-[hsl(0_0%_20.4%_/_1)] scrollbar-thin border-[hsl(0_0%_15.3%_/_1)] lg:border-l lg:relative lg:z-auto lg:shadow-none" aria-labelledby="user-profile-sidebar-title">
            <div class="user-profile-sidebar-mobile-header lg:hidden flex-shrink-0 flex items-center justify-between gap-3 px-4 py-3 border-b <?= $borderDivider ?>">
                <h3 id="user-profile-sidebar-title" class="text-sm font-semibold text-zinc-100 m-0"><?= Yii::t('common', 'Параметры и блоки') ?></h3>
                <button type="button" class="user-profile-sidebar-close filters-drawer-close ds-btn ds-btn--icon ds-btn--ghost" aria-label="<?= Html::encode(Yii::t('common', 'Закрыть')) ?>" style="min-width: 44px; min-height: 44px; padding: 0;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="flex flex-col flex-1 min-h-0 overflow-y-auto space-y-6 px-0 pt-3 pb-6 lg:pt-0 lg:pb-0">
            <div class="<?= $sectionClass ?> flex-1 flex flex-col min-h-0">
                <h3 class="<?= $sectionHeaderClass ?> flex-shrink-0"><?= Yii::t('common', 'Параметры') ?></h3>
                <div class="p-4 flex-1 overflow-y-auto">
                    <div class="space-y-3">
                    <?php foreach ($boolAttrs as $attr): ?>
                        <?php
                        $checked = ($attr === 'status_banned') ? ($user->status === User::STATUS_BLOCKED) : !empty($user->$attr);
                        $label = $boolLabels[$attr] ?? $attr;
                        ?>
                        <div class="flex items-center justify-between gap-3 py-1">
                            <label class="text-sm text-zinc-300 cursor-pointer flex-1 min-w-0" for="user-bool-<?= Html::encode($attr) ?>-<?= (int)$user->id ?>"><?= Html::encode($label) ?></label>
                            <label class="user-profile-bool-switch relative inline-flex items-center cursor-pointer flex-shrink-0">
                                <input type="checkbox"
                                       id="user-bool-<?= Html::encode($attr) ?>-<?= (int)$user->id ?>"
                                       class="sr-only user-profile-bool-input"
                                       data-user-id="<?= (int)$user->id ?>"
                                       data-attribute="<?= Html::encode($attr) ?>"
                                       <?= $checked ? ' checked' : '' ?>>
                                <span class="user-profile-bool-slider"></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($teams)): ?>
            <div class="<?= $sectionClass ?>">
                <h3 class="<?= $sectionHeaderClass ?>"><?= Yii::t('common', 'Команда') ?></h3>
                    <div class="overflow-x-auto">
                        <?= \kartik\grid\GridView::widget([
                            'dataProvider' => $teamsProvider,
                            'layout' => "{items}\n{pager}",
                            'tableOptions' => ['class' => 'table-auto w-full text-sm'],
                            'options' => ['class' => 'admin-grid-view-dark'],
                            'filterRowOptions' => ['style' => 'display: none;'],
                            'columns' => [
                                [
                                    'attribute' => 'name',
                                    'label' => Yii::t('common', 'Ник'),
                                    'format' => 'raw',
                                    'headerOptions' => ['class' => $headerCellClass],
                                    'contentOptions' => ['class' => $bodyCellClass],
                                    'value' => function ($model) {
                                        $u = User::findBySteamId($model['steam_id'], false, 'profile2');
                                        return $u ? Html::a(Html::encode($model['name']), '/profile/' . $u->id, ['class' => 'text-blue-400 hover:underline']) : Html::encode($model['name']);
                                    },
                                ],
                            ],
                        ]) ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($lastCheck)): ?>
                <div class="<?= $sectionClass ?>">
                    <h3 class="<?= $sectionHeaderClass ?>"><?= Yii::t('common', 'Проверки на других проектах') ?></h3>
                    <div class="overflow-x-auto"><?= \kartik\grid\GridView::widget([
                        'dataProvider' => $checkingOtherProjectProvider,
                        'layout' => "{items}\n{pager}",
                        'tableOptions' => ['class' => 'table-auto w-full text-sm'],
                        'options' => ['class' => 'admin-grid-view-dark'],
                        'columns' => [
                            ['label' => Yii::t('common', 'Сервер'), 'value' => function ($m) { return Html::encode($m['serverName'] ?? '-'); }, 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                            ['label' => Yii::t('common', 'Дата'), 'value' => function ($m) { return Html::encode($m['date'] ?? '-'); }, 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                        ],
                    ]) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($bans)): ?>
                <div class="<?= $sectionClass ?>">
                    <h3 class="<?= $sectionHeaderClass ?>"><?= Yii::t('common', 'Баны на других проектах') ?></h3>
                    <div class="overflow-x-auto"><?= \kartik\grid\GridView::widget([
                        'dataProvider' => $bansOtherProjectProvider,
                        'layout' => "{items}\n{pager}",
                        'tableOptions' => ['class' => 'table-auto w-full text-sm'],
                        'options' => ['class' => 'admin-grid-view-dark'],
                        'columns' => [
                            ['label' => Yii::t('common', 'Сервер'), 'value' => function ($m) { return Html::encode($m['serverName'] ?? '-'); }, 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                            ['label' => Yii::t('common', 'Причина'), 'value' => function ($m) { return Html::encode($m['reason'] ?? '-'); }, 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                            ['label' => Yii::t('common', 'Дата'), 'value' => function ($m) { return Html::encode($m['date'] ?? '-'); }, 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                        ],
                    ]) ?></div>
                </div>
            <?php endif; ?>
            </div>
        </aside>
        </div>
    </div>
</div>

<!-- Модальные окна: класс user-profile-modal — тёмная тема в style -->
<div class="modal fade user-profile-modal" id="modalForm" tabindex="-1" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable user-profile-modal__dialog" role="document">
        <div class="modal-content user-profile-modal__content">
            <div id="role_form" style="display: none;"><?= $this->render('_form_role', compact('roleForm')) ?></div>
            <div id="bonus_form" style="display: none;"><?= $this->render('_form_personal_bonus', compact('bonusForm')) ?></div>
            <div id="payout_form" style="display: none;"><?= $this->render('_form_payout_form', compact('payoutForm')) ?></div>
            <div id="balance_transfer_form" style="display: none;"><?= $this->render('_form_balance_transfer', compact('balanceTransferForm')) ?></div>
            <div id="user_drop_transfer_form" style="display: none;"><?= $this->render('_form_user_drop_transfer', compact('userDropTransferForm')) ?></div>
        </div>
    </div>
</div>

<style>
.user-profile-bool-switch { width: 44px; height: 24px; }
.user-profile-bool-switch .user-profile-bool-input { position: absolute; opacity: 0; width: 0; height: 0; }
.user-profile-bool-slider {
    position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
    background-color: hsl(0 0% 25%);
    border-radius: 24px;
    border: 1px solid hsl(0 0% 15.3%);
    transition: 0.25s;
}
.user-profile-bool-slider::before {
    position: absolute; content: ""; height: 18px; width: 18px; left: 2px; bottom: 2px;
    background-color: white;
    border-radius: 50%;
    transition: 0.25s;
}
.user-profile-bool-input:checked + .user-profile-bool-slider { background-color: hsl(142 71% 45%); }
.user-profile-bool-input:checked + .user-profile-bool-slider::before { transform: translateX(20px); }
.user-profile-bool-input:focus + .user-profile-bool-slider { box-shadow: 0 0 0 2px hsl(142 71% 45% / 0.3); }
.user-profile-bool-input:disabled + .user-profile-bool-slider { opacity: 0.5; cursor: not-allowed; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
/* Модалки профиля: тёмная тема как карточки страницы */
.user-profile-modal .modal-content {
    background: hsl(0 0% 14% / 1);
    border: 1px solid hsl(0 0% 18% / 1);
    border-radius: 0.5rem;
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.55);
    color: hsl(0 0% 93%);
}
.user-profile-modal__dialog {
    max-width: 26rem;
    margin: 1rem auto;
}
.user-profile-modal .modal-header {
    border-bottom: 1px solid hsl(0 0% 18% / 1);
    padding: 1rem 1.25rem;
    background: hsl(0 0% 12% / 1);
    border-radius: 0.5rem 0.5rem 0 0;
}
.user-profile-modal .modal-title {
    color: hsl(0 0% 98%);
    font-weight: 600;
    font-size: 1rem;
    line-height: 1.35;
    margin: 0;
}
.user-profile-modal .modal-body {
    padding: 1rem 1.25rem 1.25rem;
}
.user-profile-modal .btn-close {
    filter: invert(1);
    opacity: 0.65;
}
.user-profile-modal .btn-close:hover {
    opacity: 1;
}
.user-profile-modal .form-label {
    color: hsl(0 0% 72%);
    font-size: 0.8125rem;
    font-weight: 500;
    margin-bottom: 0.375rem;
}
.user-profile-modal .form-control,
.user-profile-modal .form-select {
    background: hsl(0 0% 11% / 1) !important;
    border: 1px solid hsl(0 0% 22% / 1) !important;
    color: hsl(0 0% 96%) !important;
    border-radius: 0.375rem;
    font-size: 0.9375rem;
}
.user-profile-modal .form-control::placeholder {
    color: hsl(0 0% 45%);
}
.user-profile-modal .form-control:focus,
.user-profile-modal .form-select:focus {
    border-color: hsl(142 71% 42%) !important;
    box-shadow: 0 0 0 2px hsl(142 71% 45% / 0.22);
    color: hsl(0 0% 100%);
}
.user-profile-modal .invalid-feedback,
.user-profile-modal .form-text {
    color: hsl(0 72% 72%);
    font-size: 0.8125rem;
}
.user-profile-modal .user-profile-modal__hint {
    color: hsl(0 0% 62%);
    font-size: 0.8125rem;
    line-height: 1.45;
    margin-bottom: 0.875rem;
}
.user-profile-modal .alert-danger,
.user-profile-modal .alert.alert-danger {
    background: hsl(0 45% 16% / 0.95) !important;
    border: 1px solid hsl(0 40% 32%) !important;
    color: hsl(0 86% 88%) !important;
    border-radius: 0.375rem;
    font-size: 0.875rem;
}
.user-profile-modal .alert-danger ul {
    margin: 0.35rem 0 0 1rem;
    padding: 0;
}
.user-profile-modal .ds-select-wrapper {
    position: relative;
    margin-bottom: 0.75rem;
}
.user-profile-modal .ds-select-wrapper .ds-select-arrow {
    color: hsl(0 0% 55%);
}
.user-profile-modal .mb-3 {
    margin-bottom: 0.875rem !important;
}
.user-profile-modal .modal-body .btn-success,
.user-profile-modal .modal-body .btn-primary,
.user-profile-modal .modal-body .ds-btn {
    margin-top: 0.25rem;
}
.user-profile-modal .modal-body .ds-btn--primary,
.user-profile-modal .modal-body .ds-btn--success {
    min-height: 40px;
    padding-left: 1rem;
    padding-right: 1rem;
    font-weight: 600;
}
.user-profile-modal select[multiple].chosen-select,
.user-profile-modal select[multiple].ds-select {
    min-height: 9rem;
    padding: 0.5rem;
    line-height: 1.4;
}
.user-profile-modal select option {
    background: hsl(0 0% 14%);
    color: hsl(0 0% 93%);
    padding: 0.25rem;
}
/* Полоска «Параметры»: только экраны ≤991px (в админке Tailwind lg:hidden часто не в бандле) */
.user-profile-params-toolbar-mobile { display: none !important; }
@media (max-width: 991px) {
    .user-profile-params-toolbar-mobile {
        display: flex !important;
        justify-content: flex-end;
        align-items: center;
    }
}
/* Мобилка: выдвижная панель параметров (как фильтры в списках) */
.user-profile-sidebar-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9998;
    opacity: 0;
    transition: opacity 0.2s ease;
    -webkit-tap-highlight-color: transparent;
}
body.user-profile-sidebar-open .user-profile-sidebar-backdrop {
    display: block;
    opacity: 1;
}
@media (max-width: 991px) {
    .user-profile-sidebar-backdrop {
        display: block;
        pointer-events: none;
    }
    body.user-profile-sidebar-open .user-profile-sidebar-backdrop {
        pointer-events: auto;
    }
    #user-profile-sidebar.user-profile-sidebar-panel {
        position: fixed !important;
        top: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        left: auto !important;
        width: min(90vw, 320px) !important;
        z-index: 9999 !important;
        transform: translateX(100%);
        transition: transform 0.25s ease-out;
        box-shadow: -4px 0 24px rgba(0,0,0,0.4);
    }
    body.user-profile-sidebar-open #user-profile-sidebar.user-profile-sidebar-panel {
        transform: translateX(0) !important;
    }
    body.user-profile-sidebar-open {
        overflow: hidden !important;
    }
}
@media (min-width: 992px) {
    .user-profile-sidebar-backdrop { display: none !important; }
    #user-profile-sidebar.user-profile-sidebar-panel {
        transform: none !important;
        position: relative !important;
        inset: auto !important;
        width: auto !important;
        box-shadow: none !important;
    }
    body.user-profile-sidebar-open {
        overflow: auto !important;
    }
}
</style>
<?php
$setUserBoolUrl = Url::to(['/user/set-user-bool']);
$csrfName = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->csrfToken;
$dropTransferLookupUrl = Json::htmlEncode(Url::to(['/user/lookup-drop-transfer-recipient']));
$dropTransferUserId = (int) $user->id;
$dropTransferRowsCount = $userDropTransferForm->getTransferableRowsCount();
$this->registerJs(<<<JS
(function() {
    $('[data-bs-modal-form]').on('click', function () {
        var form = $($(this).data().bsTarget);
        var element = $("#" + $(this).data().bsModalForm);
        form.find('.modal-content > *').hide();
        element.show();

        var title = element.find('.modal-title').first();
        if (title.length) {
            if (!title.attr('id')) {
                title.attr('id', element.attr('id') + '-title');
            }
            form.attr('aria-labelledby', title.attr('id'));
        }
    });

    var dropTransferLookupUrl = {$dropTransferLookupUrl};
    var dropTransferUserId = {$dropTransferUserId};
    var dropTransferRowsCount = {$dropTransferRowsCount};
    var dropTransferInput = $('#user-drop-transfer-steam-id');
    var dropTransferStatus = $('#user-drop-transfer-lookup-status');
    var dropTransferRecipient = $('#user-drop-transfer-recipient');
    var dropTransferSubmit = $('#user-drop-transfer-submit');
    var dropTransferForm = $('#user-drop-transfer-form-element');
    var dropTransferTimer = null;
    var dropTransferRequest = null;
    var verifiedSteamId = '';

    function setDropTransferStatus(message, isError) {
        dropTransferStatus
            .text(message || '')
            .toggleClass('invalid-feedback d-block', !!isError)
            .toggleClass('form-text', !isError);
        dropTransferInput.attr('aria-invalid', isError ? 'true' : 'false');
    }

    function resetDropTransferRecipient(message, isError) {
        verifiedSteamId = '';
        dropTransferRecipient.prop('hidden', true);
        dropTransferSubmit.prop('disabled', true).attr('aria-disabled', 'true');
        setDropTransferStatus(message, isError);
    }

    function showDropTransferRecipient(user) {
        verifiedSteamId = String(user.steamId || '');
        $('#user-drop-transfer-name').text(user.username || 'Без ника');
        $('#user-drop-transfer-steam').text(verifiedSteamId);

        var avatar = $('#user-drop-transfer-avatar');
        var fallback = $('#user-drop-transfer-avatar-fallback');
        if (user.avatar) {
            avatar
                .off('error.userDropTransfer')
                .on('error.userDropTransfer', function() {
                    avatar.prop('hidden', true);
                    fallback.prop('hidden', false);
                })
                .attr('src', user.avatar)
                .attr('alt', '')
                .prop('hidden', false);
            fallback.prop('hidden', true);
        } else {
            avatar.attr('src', '').attr('alt', '').prop('hidden', true);
            fallback.prop('hidden', false);
        }

        dropTransferRecipient.prop('hidden', false);
        setDropTransferStatus('Получатель найден. Проверьте ник и Steam ID.', false);
        if (dropTransferRowsCount > 0) {
            dropTransferSubmit.prop('disabled', false).attr('aria-disabled', 'false');
        }
    }

    function lookupDropTransferRecipient() {
        var steamId = $.trim(dropTransferInput.val());
        if (!steamId) {
            resetDropTransferRecipient('Введите Steam ID, чтобы проверить получателя.', false);
            return;
        }
        if (!/^\d{8,20}$/.test(steamId)) {
            resetDropTransferRecipient('Steam ID должен содержать от 8 до 20 цифр.', true);
            return;
        }

        if (dropTransferRequest) {
            dropTransferRequest.abort();
        }

        resetDropTransferRecipient('Ищем пользователя…', false);
        dropTransferInput.attr('aria-busy', 'true');
        dropTransferRequest = $.ajax({
            url: dropTransferLookupUrl,
            type: 'GET',
            dataType: 'json',
            data: {
                userId: dropTransferUserId,
                steamId: steamId
            }
        }).done(function(response) {
            if ($.trim(dropTransferInput.val()) !== steamId) {
                return;
            }
            if (response && response.success && response.user) {
                showDropTransferRecipient(response.user);
                return;
            }
            resetDropTransferRecipient((response && response.error) || 'Пользователь не найден.', true);
        }).fail(function(xhr, status) {
            if (status === 'abort') {
                return;
            }
            var response = xhr.responseJSON || {};
            resetDropTransferRecipient(response.error || 'Не удалось проверить пользователя.', true);
        }).always(function() {
            dropTransferInput.removeAttr('aria-busy');
            dropTransferRequest = null;
        });
    }

    dropTransferInput.on('input', function() {
        clearTimeout(dropTransferTimer);
        resetDropTransferRecipient('', false);
        dropTransferTimer = setTimeout(lookupDropTransferRecipient, 350);
    });

    $('[data-bs-modal-form="user_drop_transfer_form"]').on('click', function() {
        if (dropTransferInput.val()) {
            lookupDropTransferRecipient();
        }
    });

    dropTransferForm.on('beforeSubmit', function() {
        var currentSteamId = $.trim(dropTransferInput.val());
        if (!verifiedSteamId || verifiedSteamId !== currentSteamId) {
            resetDropTransferRecipient('Сначала дождитесь проверки получателя.', true);
            dropTransferInput.trigger('focus');
            return false;
        }
        dropTransferSubmit
            .prop('disabled', true)
            .attr('aria-disabled', 'true')
            .attr('aria-busy', 'true')
            .text('Переносим…');
        return true;
    });

    $('#modalForm').on('shown.bs.modal', function() {
        var visibleInput = $(this).find('.modal-content > div:visible input:not(:disabled)').first();
        if (visibleInput.length) {
            visibleInput.trigger('focus');
        }
    });

    $('.user-profile-bool-input').on('change', function() {
        var input = $(this);
        var userId = input.data('user-id');
        var attribute = input.data('attribute');
        var value = input.is(':checked') ? 1 : 0;
        input.prop('disabled', true);

        $.ajax({
            url: '{$setUserBoolUrl}',
            type: 'POST',
            data: {
                userId: userId,
                attribute: attribute,
                value: value,
                '{$csrfName}': '{$csrfToken}'
            },
            dataType: 'json'
        }).done(function(res) {
            if (res.success) {
                if (attribute === 'status_banned') location.reload();
                return;
            }
            input.prop('checked', value === 0);
            alert(res.error || 'Ошибка сохранения');
        }).fail(function() {
            input.prop('checked', value === 0);
            alert('Ошибка сети');
        }).always(function() {
            input.prop('disabled', false);
        });
    });
})();
JS
);
$this->registerJs(<<<JS
(function() {
    var btn = document.getElementById('user-profile-sidebar-toggle');
    var backdrop = document.getElementById('user-profile-sidebar-backdrop');
    var sidebar = document.getElementById('user-profile-sidebar');
    var slot = document.getElementById('user-profile-sidebar-slot');
    if (!btn || !backdrop || !sidebar || !slot) return;

    function isMobile() {
        return window.matchMedia('(max-width: 991px)').matches;
    }

    function openDrawer() {
        if (!isMobile()) return;
        if (sidebar.parentNode === slot) {
            document.body.appendChild(sidebar);
        }
        document.body.classList.add('user-profile-sidebar-open');
        backdrop.setAttribute('aria-hidden', 'false');
        btn.setAttribute('aria-expanded', 'true');
    }

    function closeDrawer() {
        document.body.classList.remove('user-profile-sidebar-open');
        backdrop.setAttribute('aria-hidden', 'true');
        btn.setAttribute('aria-expanded', 'false');
        if (sidebar.parentNode === document.body) {
            slot.appendChild(sidebar);
        }
    }

    btn.addEventListener('click', function(e) {
        e.preventDefault();
        if (document.body.classList.contains('user-profile-sidebar-open')) {
            closeDrawer();
        } else {
            openDrawer();
        }
    });
    backdrop.addEventListener('click', closeDrawer);
    document.addEventListener('click', function(e) {
        if (e.target && e.target.closest && e.target.closest('#user-profile-sidebar .filters-drawer-close')) {
            closeDrawer();
        }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.body.classList.contains('user-profile-sidebar-open')) {
            closeDrawer();
        }
    });
    window.addEventListener('resize', function() {
        if (!isMobile() && document.body.classList.contains('user-profile-sidebar-open')) {
            closeDrawer();
        }
    });
})();
JS
);
?>

<?php if ($balanceTransferForm->hasErrors()): ?>
<?php
$this->registerJs(<<<JS
(function() {
    var modalEl = document.getElementById('modalForm');
    if (!modalEl || !window.bootstrap) return;
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    $('#modalForm .modal-content > *').hide();
    $('#balance_transfer_form').show();
    modal.show();
})();
JS
);
?>
<?php endif; ?>

<?php if ($userDropTransferForm->hasErrors()): ?>
<?php
$this->registerJs(<<<JS
(function() {
    var modalEl = document.getElementById('modalForm');
    if (!modalEl || !window.bootstrap) return;
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    $('#modalForm .modal-content > *').hide();
    $('#user_drop_transfer_form').show();
    modalEl.setAttribute('aria-labelledby', 'user-drop-transfer-title');
    modal.show();
    $('#user-drop-transfer-steam-id').trigger('input');
})();
JS
);
?>
<?php endif; ?>
