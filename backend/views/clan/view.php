<?php

use common\components\helpers\Role;
use common\models\clan\Clan;
use common\models\clan\ClanMember;
use common\models\clan\ClanMemberStatistics;
use common\models\clan\ClanStatistics;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var Clan $model */
/** @var ClanMember[] $members */
/** @var array<int, ClanMemberStatistics> $statsByMemberId */
/** @var ClanStatistics|null $clanStat */
/** @var string|null $wipe */
/** @var string|null $publicUrl */
/** @var array<int, string> $transferChoices */

$this->title = Yii::t('common', 'Клан') . ': ' . Html::encode($model->name);
$this->params['contentClass'] = 'content-no-padding';

$isAdmin = Yii::$app->user->can(Role::ROLE_ADMIN);

$this->params['headerActions'] = array_values(array_filter([
    [
        'label' => '<i class="fas fa-arrow-left"></i> ' . Yii::t('common', 'К списку'),
        'url' => ['index'],
        'class' => 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
    ],
    $publicUrl ? [
        'label' => '<i class="fas fa-external-link-alt"></i> ' . Yii::t('common', 'На сайте'),
        'url' => $publicUrl,
        'class' => 'bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
        'target' => '_blank',
        'rel' => 'noopener noreferrer',
    ] : null,
    [
        'label' => '<i class="fas fa-user-plus"></i> ' . Yii::t('common', 'Добавить участника'),
        'url' => ['member-create', 'clanId' => $model->id],
        'class' => 'bg-teal-600 hover:bg-teal-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
    ],
    [
        'label' => '<i class="fas fa-edit"></i> ' . Yii::t('common', 'Редактировать'),
        'url' => ['update', 'id' => $model->id],
        'class' => 'bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
    ],
    [
        'label' => '<i class="fas fa-sync"></i> ' . Yii::t('common', 'Пересчёт статистики (очередь)'),
        'url' => ['queue-statistics', 'id' => $model->id],
        'class' => 'bg-indigo-600 hover:bg-indigo-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
        'data-method' => 'post',
        'data-confirm' => Yii::t('common', 'Поставить в очередь полный пересчёт кланов для сервера этого клана?'),
    ],
    $isAdmin ? [
        'label' => '<i class="fas fa-trash"></i> ' . Yii::t('common', 'Удалить клан'),
        'url' => ['delete', 'id' => $model->id],
        'class' => 'bg-red-700 hover:bg-red-800 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
        'data-method' => 'post',
        'data-confirm' => Yii::t('common', 'Удалить клан безвозвратно? Все связанные данные будут удалены.'),
    ] : null,
]));
?>

<div class="p-4 lg:p-6 space-y-8 text-white max-w-6xl">
    <section class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div class="rounded border border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_18%_/_1)] p-4 space-y-2">
            <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wide"><?= Yii::t('common', 'Основное') ?></h2>
            <p><span class="text-gray-400">ID</span> <?= (int)$model->id ?></p>
            <p><span class="text-gray-400"><?= Html::encode($model->getAttributeLabel('name')) ?></span> <?= Html::encode($model->name) ?></p>
            <p><span class="text-gray-400"><?= Html::encode($model->getAttributeLabel('tag')) ?></span> <?= Html::encode($model->tag) ?></p>
            <p><span class="text-gray-400"><?= Yii::t('common', 'Сервер') ?></span>
                <?= $model->server ? Html::encode($model->server->name . ' (' . $model->server->tag . ')') : '—' ?></p>
            <p><span class="text-gray-400"><?= Yii::t('common', 'Лидер') ?></span>
                <?= $model->leaderUser ? Html::encode($model->leaderUser->username) . ' (#' . (int)$model->leader_user_id . ')' : '—' ?></p>
            <p><span class="text-gray-400"><?= Html::encode($model->getAttributeLabel('privacy')) ?></span> <?= Html::encode($model->getPrivacyLabel()) ?></p>
            <p><span class="text-gray-400"><?= Html::encode($model->getAttributeLabel('level')) ?></span> <?= (int)$model->level ?></p>
            <p><span class="text-gray-400"><?= Html::encode($model->getAttributeLabel('experience')) ?></span> <?= (int)$model->experience ?></p>
        </div>
        <div class="rounded border border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_18%_/_1)] p-4 space-y-2">
            <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wide"><?= Yii::t('common', 'Статистика клана (текущий вайп)') ?></h2>
            <?php if ($wipe): ?>
                <p class="text-gray-400 text-xs break-all"><?= Html::encode($wipe) ?></p>
            <?php endif; ?>
            <?php if ($clanStat): ?>
                <p><span class="text-gray-400"><?= Yii::t('common', 'Убийства') ?></span> <?= (int)$clanStat->total_kills ?></p>
                <p><span class="text-gray-400"><?= Yii::t('common', 'Смерти') ?></span> <?= (int)$clanStat->total_deaths ?></p>
                <p class="text-xs text-gray-500"><?= Yii::t('common', 'Обновлено') ?>: <?= Yii::$app->formatter->asDatetime($clanStat->updated_at) ?></p>
            <?php else: ?>
                <p class="text-gray-400"><?= Yii::t('common', 'Нет данных за текущий вайп (ожидайте cron или запустите пересчёт).') ?></p>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($model->motto || $model->description): ?>
        <section class="rounded border border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_18%_/_1)] p-4 space-y-2">
            <?php if ($model->motto): ?>
                <p class="text-gray-400 text-xs uppercase"><?= Html::encode($model->getAttributeLabel('motto')) ?></p>
                <p><?= Html::encode($model->motto) ?></p>
            <?php endif; ?>
            <?php if ($model->description): ?>
                <p class="text-gray-400 text-xs uppercase"><?= Html::encode($model->getAttributeLabel('description')) ?></p>
                <p class="whitespace-pre-wrap"><?= Html::encode($model->description) ?></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section>
        <h2 class="text-sm font-semibold text-white mb-3"><?= Yii::t('common', 'Участники') ?></h2>

        <?php if (!empty($transferChoices)): ?>
            <div class="rounded border border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_18%_/_1)] p-4 mb-4">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2"><?= Yii::t('common', 'Передача лидерства') ?></h3>
                <?= Html::beginForm(['transfer-leadership', 'id' => $model->id], 'post', ['class' => 'flex flex-wrap items-end gap-2']) ?>
                <div class="ds-select-wrapper min-w-[220px]">
                    <?= Html::dropDownList(
                        'new_leader_user_id',
                        null,
                        $transferChoices,
                        ['class' => 'ds-select form-control', 'prompt' => Yii::t('common', '— выберите участника —')]
                    ) ?>
                    <i class="fas fa-chevron-down ds-select-arrow"></i>
                </div>
                <?= Html::submitButton(Yii::t('common', 'Передать'), ['class' => 'ds-btn ds-btn--secondary']) ?>
                <?= Html::endForm() ?>
            </div>
        <?php endif; ?>

        <div class="overflow-x-auto rounded border border-[hsl(0_0%_15.3%_/_1)]">
            <table class="min-w-full text-sm">
                <thead>
                <tr class="bg-[hsl(0_0%_20.4%_/_1)] text-left text-xs text-gray-400 uppercase">
                    <th class="px-3 py-2"><?= Yii::t('common', 'Пользователь') ?></th>
                    <th class="px-3 py-2"><?= Yii::t('common', 'Роль') ?></th>
                    <th class="px-3 py-2"><?= Yii::t('common', 'Вступил') ?></th>
                    <th class="px-3 py-2"><?= Yii::t('common', 'Вышел') ?></th>
                    <th class="px-3 py-2"><?= Yii::t('common', 'Стат. вайп') ?></th>
                    <th class="px-3 py-2"><?= Yii::t('common', 'K/D') ?></th>
                    <th class="px-3 py-2"><?= Yii::t('common', 'Действия') ?></th>
                </tr>
                </thead>
                <tbody class="divide-y divide-[hsl(0_0%_15.3%_/_1)]">
                <?php foreach ($members as $m): ?>
                    <?php
                    /** @var ClanMemberStatistics|null $ms */
                    $ms = $statsByMemberId[$m->id] ?? null;
                    $active = $m->isActive();
                    ?>
                    <tr class="bg-[hsl(0_0%_18%_/_1)]">
                        <td class="px-3 py-2">
                            <?= $m->user ? Html::encode($m->user->username) . ' (#' . (int)$m->user_id . ')' : '#' . (int)$m->user_id ?>
                        </td>
                        <td class="px-3 py-2"><?= Html::encode($m->role) ?></td>
                        <td class="px-3 py-2"><?= Html::encode($m->join_date) ?></td>
                        <td class="px-3 py-2"><?= $m->leave_date ? Html::encode($m->leave_date) : '—' ?></td>
                        <td class="px-3 py-2">
                            <?php if ($ms): ?>
                                <span class="text-xs"><?= Html::encode($ms->member_status ?? 'active') ?></span>
                                <?php if (!empty($ms->frozen_at)): ?>
                                    <span class="text-gray-500 text-xs"> @ <?= Yii::$app->formatter->asDatetime($ms->frozen_at) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2">
                            <?php if ($ms): ?>
                                <?= (int)$ms->kills ?> / <?= (int)$ms->deaths ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <?php if ($active && !$m->isLeader()): ?>
                                <?= Html::a(Yii::t('common', 'Роль'), ['member-update', 'id' => $m->id], ['class' => 'text-teal-400 hover:underline mr-2']) ?>
                                <?= Html::a(Yii::t('common', 'Права'), ['member-permissions', 'id' => $m->id], ['class' => 'text-indigo-400 hover:underline mr-2']) ?>
                                <?= Html::a(Yii::t('common', 'Исключить'), ['member-remove', 'id' => $m->id], [
                                    'class' => 'text-red-400 hover:underline',
                                    'data' => [
                                        'method' => 'post',
                                        'confirm' => Yii::t('common', 'Исключить участника из клана?'),
                                    ],
                                ]) ?>
                            <?php elseif ($active && $m->isLeader()): ?>
                                <span class="text-gray-500 text-xs"><?= Yii::t('common', 'Лидер') ?></span>
                            <?php else: ?>
                                <span class="text-gray-500">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
