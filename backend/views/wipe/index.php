<?php

use common\models\servers\Servers;
use common\models\map\MapList;
use yii\bootstrap5\Html;

/** @var Servers[] $servers */

$this->title = Yii::t('common', 'Вайп');
$this->params['contentClass'] = 'content-no-padding';

$servers = Servers::find()
    ->with('mapList')
    ->cache(30)
    ->andWhere(['IN', 'status', [Servers::STATUS_NOACTIVE, Servers::STATUS_ACTIVE]])
    ->orderBy(['sort' => SORT_ASC])
    ->all();

$totalServers = count($servers);
$serversWithMaps = count(array_filter($servers, function($s) { return !empty($s->map_list_id); }));
$totalMaps = MapList::find()->count();
$fixedMapIds = Servers::find()
    ->select('map_list_id')
    ->andWhere(['IS NOT', 'map_list_id', null])
    ->column();
$unfixedMaps = !empty($fixedMapIds)
    ? MapList::find()->where(['NOT IN', 'id', $fixedMapIds])->count()
    : $totalMaps;
$activeServers = count(array_filter($servers, function($s) { return $s->status == Servers::STATUS_ACTIVE; }));
$serversWithoutMaps = $totalServers - $serversWithMaps;
?>
<?= \frontend\widgets\Alert::widget() ?>

<div class="content wipe-index-page">
    <div class="wipe-index-main p-4 lg:p-6">
        <!-- Серверы -->
        <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wide mb-3"><?= Yii::t('common', 'Серверы') ?></h2>
        <div class="row g-3">
            <?php foreach ($servers as $server): ?>
                <?php
                $blockDisabled = Yii::$app->cache->get("WIPE_actionBlock_{$server->id}");
                $topDisabled = Yii::$app->cache->get("WIPE_actionTop_{$server->tag}");
                $genDisabled = $server->secret_map ? true : Yii::$app->cache->get("WIPE_actionGenerateMap6_{$server->id}");
                ?>
                <div class="col-md-6 col-xl-4">
                    <div class="wipe-server-card">
                        <div class="wipe-server-card__header">
                            <?= Html::encode($server->name) ?>
                            <span class="wipe-server-card__tag"><?= Html::encode($server->tag) ?></span>
                        </div>
                        <div class="wipe-server-card__body">
                            <?= Html::a(
                                '<i class="bi bi-ban"></i> ' . Yii::t('common', 'Блок'),
                                ['/wipe/block', 'id' => $server->id],
                                [
                                    'class' => 'ds-btn ds-btn--sm ' . ($blockDisabled ? 'ds-btn--secondary disabled' : 'ds-btn--success'),
                                    'disabled' => $blockDisabled,
                                    'data' => $blockDisabled ? [] : ['confirm' => 'Заблокировать предметы?', 'method' => 'post'],
                                ]
                            ) ?>
                            <?= Html::a(
                                '<i class="bi bi-trophy"></i> ' . Yii::t('common', 'Топы'),
                                ['/wipe/top', 'server' => $server->tag],
                                [
                                    'class' => 'ds-btn ds-btn--sm ' . ($topDisabled ? 'ds-btn--secondary disabled' : 'ds-btn--success'),
                                    'disabled' => $topDisabled,
                                    'data' => $topDisabled ? [] : ['confirm' => 'Начислить награды за топы?', 'method' => 'post'],
                                ]
                            ) ?>
                            <?php if (!$server->secret_map): ?>
                                <?= Html::a(
                                    '<i class="bi bi-arrow-repeat"></i> ' . Yii::t('common', 'Генерация карт'),
                                    ['/wipe/generate-map', 'id' => $server->id],
                                    [
                                        'class' => 'ds-btn ds-btn--sm ' . ($genDisabled ? 'ds-btn--secondary disabled' : 'ds-btn--success'),
                                        'disabled' => $genDisabled,
                                        'data' => $genDisabled ? [] : ['confirm' => 'Сгенерировать карты?', 'method' => 'post'],
                                    ]
                                ) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Правая колонка: Общая статистика — как блок «Параметры» в добавлении предмета -->
    <aside class="wipe-index-sidebar admin-filters-content bg-[hsl(0_0%_20.4%_/_1)] h-full overflow-y-auto scrollbar-thin flex flex-col border-t lg:border-t-0">
        <div class="p-4 flex-1 flex flex-col">
            <div class="mb-6">
                <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide"><?= Yii::t('common', 'Общая статистика') ?></h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= Yii::t('common', 'Всего серверов') ?></label>
                        <span class="text-white font-medium"><?= $totalServers ?></span>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= Yii::t('common', 'Активных') ?></label>
                        <span class="text-white font-medium"><?= $activeServers ?></span>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= Yii::t('common', 'С картами') ?></label>
                        <span class="text-white font-medium"><?= $serversWithMaps ?></span>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= Yii::t('common', 'Без карт') ?></label>
                        <span class="text-white font-medium"><?= $serversWithoutMaps ?></span>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= Yii::t('common', 'Карт в базе') ?></label>
                        <span class="text-white font-medium"><?= $totalMaps ?></span>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= Yii::t('common', 'Не зафиксировано') ?></label>
                        <span class="font-medium <?= $unfixedMaps > 0 ? 'text-red-400' : 'text-white' ?>"><?= $unfixedMaps ?></span>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</div>
