<?php

use common\models\servers\Servers;
use common\models\user\User;
use backend\forms\userProfile\RoleForm;
use backend\forms\userProfile\BonusForm;
use backend\forms\userProfile\PayoutForm;
use common\components\helpers\Role;
use common\models\user\UserProfile;
use common\models\user\UserTree;
use common\models\invoice\Deposit;
use yii\helpers\Html;
use yii\helpers\Url;
use frontend\widgets\Alert;
/** @var User $user */
/** @var RoleForm $roleForm */
/** @var BonusForm $bonusForm */
/** @var PayoutForm $payoutForm */

$this->title = Html::encode($user->username);

$userProfile = $user->userProfile;

$usersTree = UserTree::find()
    ->andWhere(['parent_user_id' => $user->id])
    ->andWhere(['NOT IN', 'user_id', [$user->id]])
    ->limit(20)
    ->all();

$dataProvider = new \yii\data\ArrayDataProvider([
    'allModels' => $usersTree,
    'totalCount' => count($usersTree),
    'pagination' => ['pageSize' => 10],
]);

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

    <!-- Основная колонка + сайдбар «Параметры» справа -->
    <div class="user-profile-layout flex flex-col flex-1 min-h-0 gap-6 p-4 sm:p-6">
        <div class="user-profile-content flex-1 min-w-0 space-y-6 overflow-auto">
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
                            <?php if (!empty($user->server)): ?>
                            <?= Html::a('<i class="fas fa-chart-line"></i> ' . Yii::t('common', 'Статистика'), ['/statistics/index', 'StatisticsSearch' => ['steam_id' => $user->steam_id, 'server_tag' => $user->server->tag, 'wipe' => $user->server->currentWipe()]], ['class' => 'ds-btn ds-btn--secondary ds-btn--sm !border-0']) ?>
                            <?= Html::a('<i class="fas fa-trophy"></i> ' . Yii::t('common', 'Топ'), ['/user-top/index', 'UserTopSearch' => ['user_id' => $user->id, 'server_id' => $user->server->id, 'wipe' => $user->server->currentWipe()]], ['class' => 'ds-btn ds-btn--secondary ds-btn--sm !border-0']) ?>
                            <?php if (Yii::$app->user->can(Role::ROLE_ADMIN) && $user->hasVip()): ?>
                            <?= Html::a('<i class="fas fa-crown"></i> ' . Yii::t('common', 'Выдать VIP на сервере'), ['/user/run-vip-on-server', 'userId' => $user->id], ['class' => 'ds-btn ds-btn--secondary ds-btn--sm !border-0', 'data' => ['confirm' => Yii::t('common', 'Выполнить addgroup vip_status на сервере?'), 'method' => 'post']]) ?>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (Yii::$app->user->can(Role::ROLE_ADMIN)): ?>
                <div class="<?= $sectionClass ?> lg:w-72 flex-shrink-0">
                    <h3 class="<?= $sectionHeaderClass ?>"><?= Yii::t('common', 'Финансы') ?></h3>
                    <div>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 py-3 px-4 border-b <?= $borderDivider ?>">
                            <span class="text-xs font-medium text-zinc-400 sm:min-w-[100px]"><?= Yii::t('common', 'Баланс') ?></span>
                            <span class="text-sm font-medium text-zinc-100"><?= Yii::$app->formatter->asDecimal($user->getPersonalBalance()->getBalanceCeil(), 2) ?> ₽</span>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 py-3 px-4 border-b <?= $borderDivider ?>">
                            <span class="text-xs font-medium text-zinc-400 sm:min-w-[100px]"><?= Yii::t('common', 'К выводу') ?></span>
                            <span class="text-sm font-medium text-zinc-100"><?= Yii::$app->formatter->asDecimal($payoutTotal, 2) ?> ₽</span>
                        </div>
                        <?php $skinsBalance = $user->getSkinsBalance(); ?>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 py-3 px-4 border-b <?= $borderDivider ?>">
                            <span class="text-xs font-medium text-zinc-400 sm:min-w-[100px]"><?= Yii::t('common', 'Баланс скинов') ?></span>
                            <span class="text-sm font-medium text-zinc-100"><?= $skinsBalance ? Yii::$app->formatter->asDecimal($skinsBalance->getBalanceCeil(), 2) : '0.00' ?></span>
                        </div>
                        <div class="flex flex-wrap gap-2 py-3 px-4">
                            <button type="button" class="ds-btn ds-btn--secondary ds-btn--sm !border-0" data-bs-toggle="modal" data-bs-modal-form="payout_form" data-bs-target="#modalForm">
                                <i class="fas fa-money-bill-wave"></i> <?= Yii::t('common', 'Вывод') ?>
                            </button>
                            <button type="button" class="ds-btn ds-btn--secondary ds-btn--sm !border-0" data-bs-toggle="modal" data-bs-modal-form="bonus_form" data-bs-target="#modalForm">
                                <i class="fas fa-coins"></i> <?= Yii::t('common', 'Пополнить') ?>
                            </button>
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

        <!-- Правая колонка: Параметры (как drop-form-sidebar в форме предмета) -->
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
        <aside class="user-profile-sidebar admin-filters-content flex-shrink-0 w-full border-t <?= $borderDivider ?> pt-4 flex flex-col min-h-0 overflow-y-auto scrollbar-thin bg-[hsl(0_0%_20.4%_/_1)] lg:border-t-0 lg:pt-0">
            <div class="flex flex-col flex-1 min-h-0 space-y-6">
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
                                        return $u ? Html::a(Html::encode($model['name']), ['/user/profile', 'userId' => $u->id], ['class' => 'text-blue-400 hover:underline']) : Html::encode($model['name']);
                                    },
                                ],
                            ],
                        ]) ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($usersTree)): ?>
                <div class="<?= $sectionClass ?>">
                    <h3 class="<?= $sectionHeaderClass ?>"><?= Yii::t('common', 'Рефералы') ?></h3>
                    <div class="overflow-x-auto">
                        <?= \kartik\grid\GridView::widget([
                            'dataProvider' => $dataProvider,
                            'layout' => "{items}\n{pager}",
                            'tableOptions' => ['class' => 'table-auto w-full text-sm'],
                            'options' => ['class' => 'admin-grid-view-dark'],
                            'filterRowOptions' => ['style' => 'display: none;'],
                            'columns' => [
                                ['label' => Yii::t('common', 'Ник'), 'format' => 'raw', 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass], 'value' => function (UserTree $m) { return Html::encode($m->user->username); }],
                                ['label' => Yii::t('common', 'Более часа'), 'format' => 'raw', 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass], 'value' => function (UserTree $m) {
                                    $v = $m->user->userProfile->parent_bonus;
                                    return Html::tag('span', $v ? Yii::t('common', 'Да') : Yii::t('common', 'Нет'), ['class' => 'ds-badge ' . ($v ? 'ds-badge--success' : 'ds-badge--danger')]);
                                }],
                                ['label' => Yii::t('common', 'Дата'), 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass], 'value' => function (UserTree $m) { return Yii::$app->formatter->asDate($m->user->created_at); }],
                                ['label' => '', 'format' => 'raw', 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass], 'value' => function (UserTree $m) use ($user) {
                                    return Html::a(Yii::t('common', 'Отвязать'), ['/user/revoke', 'parentId' => $user->id, 'userId' => $m->user_id], ['class' => 'ds-btn ds-btn--danger ds-btn--sm', 'data' => ['confirm' => Yii::t('common', 'Отвязать пользователя?')]]);
                                }],
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

<!-- Модальные окна -->
<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="modalForm">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div id="role_form" style="display: none;"><?= $this->render('_form_role', compact('roleForm')) ?></div>
            <div id="bonus_form" style="display: none;"><?= $this->render('_form_personal_bonus', compact('bonusForm')) ?></div>
            <div id="payout_form" style="display: none;"><?= $this->render('_form_payout_form', compact('payoutForm')) ?></div>
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
</style>
<?php
$setUserBoolUrl = Url::to(['/user/set-user-bool']);
$csrfName = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->csrfToken;
$this->registerJs(<<<JS
(function() {
    $('[data-bs-modal-form]').on('click', function () {
        var form = $($(this).data().bsTarget);
        var element = $("#" + $(this).data().bsModalForm);
        form.find('.modal-content > *').hide();
        element.show();
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
?>
