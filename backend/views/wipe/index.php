<?php

use common\models\box\Box;
use common\models\servers\Servers;
use common\models\map\MapList;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

/** @var $dataProvider */
/** @var $searchModel */
/** @var $model Box */

$this->title = Yii::t('common', 'Вайп');
?>
<div class="content-header">
    <h1><?= Html::encode($this->title) ?></h1>
</div>

<div class="content">
    <?= \frontend\widgets\Alert::widget() ?>

    <!-- Выполнить вайп через RCON -->
    <div class="ds-card mb-4" style="border: 2px solid #dc3545;">
        <div class="ds-card__header" style="background: #dc3545; color: white;">
            <h5 class="ds-card__header-title" style="color: white;">
                <i class="bi bi-play-circle"></i> <?= Yii::t('common', 'Выполнить вайп через RCON') ?>
            </h5>
        </div>
        <div class="ds-card__body">
            <p class="mb-3">
                <strong>Выполнение вайпа через RCON команду autowipe.runnow</strong> Выберите сервер и тип вайпа, затем подтвердите выполнение:
            </p>
            <ul class="mb-3">
                <li>Выбор сервера для вайпа</li>
                <li>Выбор типа вайпа (wipe или global)</li>
                <li>Просмотр всех параметров и команды</li>
                <li>Подтверждение и выполнение вайпа</li>
            </ul>
            <?= Html::a(
                '<i class="bi bi-play-circle"></i> Перейти к выполнению вайпа',
                '/wipe/run-wipe',
                [
                    'class' => 'ds-btn ds-btn--danger',
                ]
            ) ?>
        </div>
    </div>

    <!-- Комплексный вайп серверов -->
    <div class="ds-card mb-4" style="border: 2px solid #198754;">
        <div class="ds-card__header" style="background: #198754; color: white;">
            <h5 class="ds-card__header-title" style="color: white;">
                <i class="bi bi-lightning-charge"></i> <?= Yii::t('common', 'Комплексный вайп серверов') ?>
            </h5>
        </div>
        <div class="ds-card__body">
            <p class="mb-3">
                <strong>Новый раздел для комплексного вайпа!</strong> Выберите серверы и выполните все этапы вайпа одним нажатием:
            </p>
            <ul class="mb-3">
                <li>Блокировка предметов в магазине</li>
                <li>Начисление наград за топы</li>
                <li>Фиксация карты</li>
                <li>Обнуление промокода WIPE</li>
                <li>Выполнение RCON команды</li>
            </ul>
            <?= Html::a(
                '<i class="bi bi-rocket-takeoff"></i> Перейти к комплексному вайпу',
                '/wipe/wipe-servers',
                [
                    'class' => 'ds-btn ds-btn--success',
                ]
            ) ?>
        </div>
    </div>

    <?php
    /** @var Servers[] $servers */
    $servers = Servers::find()
                      ->with('mapList')
                      ->cache(30)
                      ->andWhere(['IN', 'status', [Servers::STATUS_NOACTIVE, Servers::STATUS_ACTIVE]])
                      ->orderBy(['sort' => SORT_ASC])
                      ->all();
    
    $serversNotSecret = array_filter($servers, function($server) {
        return !$server->secret_map;
    });
    
    // Статистика
    $totalServers = count($servers);
    $serversWithMaps = count(array_filter($servers, function($s) { return !empty($s->map_list_id); }));
    $totalMaps = MapList::find()->count();
    
    // Не зафиксированные карты - используем тот же подход, что и в deleteUnfixedMaps
    $fixedMapIds = Servers::find()
        ->select('map_list_id')
        ->andWhere(['IS NOT', 'map_list_id', null])
        ->column();
    
    if (!empty($fixedMapIds)) {
        $unfixedMaps = MapList::find()
            ->where(['NOT IN', 'id', $fixedMapIds])
            ->count();
    } else {
        $unfixedMaps = $totalMaps;
    }
    
    // Дополнительная статистика
    $activeServers = count(array_filter($servers, function($s) { return $s->status == Servers::STATUS_ACTIVE; }));
    $serversWithoutMaps = $totalServers - $serversWithMaps;
    ?>

    <!-- Статистика -->
    <div class="ds-card mb-4">
        <h2 class="mb-4">Общая статистика</h2>
        <div class="row">
            <div class="col-md-2 mb-3">
                <div class="ds-counter">
                    <div class="ds-counter__value"><?= $totalServers ?></div>
                    <div class="ds-counter__label">Всего серверов</div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="ds-counter">
                    <div class="ds-counter__value"><?= $activeServers ?></div>
                    <div class="ds-counter__label">Активных серверов</div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="ds-counter">
                    <div class="ds-counter__value"><?= $serversWithMaps ?></div>
                    <div class="ds-counter__label">Серверов с картами</div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="ds-counter">
                    <div class="ds-counter__value"><?= $serversWithoutMaps ?></div>
                    <div class="ds-counter__label">Серверов без карт</div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="ds-counter">
                    <div class="ds-counter__value"><?= $totalMaps ?></div>
                    <div class="ds-counter__label">Всего карт в базе</div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="ds-counter">
                    <div class="ds-counter__value <?= $unfixedMaps > 0 ? 'ds-text--danger' : '' ?>"><?= $unfixedMaps ?></div>
                    <div class="ds-counter__label">Не зафиксированных карт</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Заблокировать предметы -->
    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">
                <i class="bi bi-ban"></i> <?= Yii::t('common', 'Заблокировать предметы') ?>
            </h5>
        </div>
        <div class="ds-card__body">
            <div class="row">
                <?php foreach ($servers as $server): ?>
                    <?php 
                    $disabled = Yii::$app->cache->get("WIPE_actionBlock_{$server->id}");
                    $btnClass = $disabled ? 'ds-btn--primary disabled' : 'ds-btn--success';
                    ?>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <?= Html::a(
                            '<i class="bi bi-server"></i> ' . Html::encode($server->name),
                            '/wipe/block?id=' . $server->id,
                            [
                                'class' => 'ds-btn ' . $btnClass . ' w-100',
                                'disabled' => $disabled,
                                'data' => $disabled ? [] : [
                                    'confirm' => 'Вы уверены, что хотите заблокировать предметы для сервера "' . Html::encode($server->name) . '"?',
                                    'method' => 'post',
                                ],
                            ]
                        ) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Начислить награды за топы -->
    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">
                <i class="bi bi-trophy"></i> <?= Yii::t('common', 'Начислить награды за топы') ?>
            </h5>
        </div>
        <div class="ds-card__body">
            <div class="row">
                <?php foreach ($servers as $server): ?>
                    <?php 
                    $disabled = Yii::$app->cache->get("WIPE_actionTop_{$server->tag}");
                    $btnClass = $disabled ? 'ds-btn--primary disabled' : 'ds-btn--success';
                    ?>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <?= Html::a(
                            '<i class="bi bi-server"></i> ' . Html::encode($server->name),
                            '/wipe/top?server=' . $server->tag,
                            [
                                'class' => 'ds-btn ' . $btnClass . ' w-100',
                                'disabled' => $disabled,
                                'data' => $disabled ? [] : [
                                    'confirm' => 'Вы уверены, что хотите начислить награды за топы для сервера "' . Html::encode($server->name) . '"?',
                                    'method' => 'post',
                                ],
                            ]
                        ) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Сгенерировать карты -->
    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">
                <i class="bi bi-arrow-repeat"></i> <?= Yii::t('common', 'Сгенерировать карты') ?>
            </h5>
        </div>
        <div class="ds-card__body">
            <div class="row">
                <?php foreach ($serversNotSecret as $server): ?>
                    <?php 
                    $disabled = Yii::$app->cache->get("WIPE_actionGenerateMap6_{$server->id}");
                    $btnClass = $disabled ? 'ds-btn--primary disabled' : 'ds-btn--success';
                    ?>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <?= Html::a(
                            '<i class="bi bi-server"></i> ' . Html::encode($server->name),
                            '/wipe/generate-map?id=' . $server->id,
                            [
                                'class' => 'ds-btn ' . $btnClass . ' w-100',
                                'disabled' => $disabled,
                                'data' => $disabled ? [] : [
                                    'confirm' => 'Вы уверены, что хотите сгенерировать карты для сервера "' . Html::encode($server->name) . '"?',
                                    'method' => 'post',
                                ],
                            ]
                        ) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Зафиксировать карту -->
    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">
                <i class="bi bi-pin-map"></i> <?= Yii::t('common', 'Зафиксировать карту') ?>
            </h5>
        </div>
        <div class="ds-card__body">
            <div class="row">
                <?php foreach ($serversNotSecret as $server): ?>
                    <div class="col-md-4 col-sm-6 mb-3">
                        <div class="ds-card ds-card--hoverable" style="padding: 1rem;">
                            <div class="mb-2">
                                <?= Html::a(
                                    '<i class="bi bi-server"></i> ' . Html::encode($server->name),
                                    '/wipe/select-map?id=' . $server->id,
                                    [
                                        'class' => 'ds-btn ds-btn--primary w-100',
                                        'data' => [
                                            'confirm' => 'Вы уверены, что хотите зафиксировать карту для сервера "' . Html::encode($server->name) . '"?',
                                            'method' => 'post',
                                        ],
                                    ]
                                ) ?>
                            </div>
                            <?php if (!empty($server->map_list_id) && $server->mapList): ?>
                                <?php
                                $map = $server->mapList;
                                $fileName = '';
                                if (!empty($map->url)) {
                                    $fileName = basename(parse_url($map->url, PHP_URL_PATH));
                                }
                                if (empty($fileName)) {
                                    $fileName = $server->tag . '.map';
                                }
                                ?>
                                <div class="ds-badge ds-badge--success" style="font-family: monospace; font-size: 0.875rem; word-break: break-all;">
                                    <i class="bi bi-file-earmark"></i> <?= Html::encode($fileName) ?>
                                </div>
                            <?php else: ?>
                                <div class="ds-badge ds-badge--primary">
                                    <i class="bi bi-info-circle"></i> <?= Yii::t('common', 'Карта не зафиксирована') ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Удалить не зафиксированные карты -->
    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">
                <i class="bi bi-trash"></i> <?= Yii::t('common', 'Удалить не зафиксированные карты') ?>
            </h5>
        </div>
        <div class="ds-card__body">
            <?php 
            $disabled = Yii::$app->cache->get("WIPE_actionDeleteUnfixedMaps");
            $btnClass = $disabled ? 'ds-btn--primary disabled' : 'ds-btn--danger';
            ?>
            <div class="ds-alert ds-alert--warning mb-3">
                <i class="bi bi-exclamation-triangle"></i> <strong>Внимание!</strong> Будет удалено <strong><?= $unfixedMaps ?></strong> не зафиксированных карт.
            </div>
            <?= Html::a(
                '<i class="bi bi-trash"></i> ' . Yii::t('common', 'Удалить'),
                '/wipe/delete-unfixed-maps',
                [
                    'class' => 'ds-btn ' . $btnClass,
                    'disabled' => $disabled,
                    'data' => $disabled ? [] : [
                        'confirm' => 'Вы уверены, что хотите удалить все не зафиксированные карты? Это действие нельзя отменить!',
                        'method' => 'post',
                    ],
                ]
            ) ?>
        </div>
    </div>

    <hr style="border: none; border-top: 1px solid var(--ds-border-color); margin: 2rem 0;">

    <!-- Обнулить промокод -->
    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">
                <i class="bi bi-ticket-perforated"></i> <?= Yii::t('common', 'Обнулить промокод') ?>
            </h5>
        </div>
        <div class="ds-card__body">
            <?= Html::a(
                '<i class="bi bi-arrow-clockwise"></i> ' . Yii::t('common', 'WIPE'),
                '/wipe/promocode',
                [
                    'class' => 'ds-btn ds-btn--success',
                    'data' => [
                        'confirm' => 'Вы уверены, что хотите обнулить промокод? Все пользователи смогут ввести его заново.',
                        'method' => 'post',
                    ],
                ]
            ) ?>
        </div>
    </div>

    <!-- Задания -->
    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">
                <i class="bi bi-list-task"></i> <?= Yii::t('common', 'Задания') ?>
            </h5>
        </div>
        <div class="ds-card__body">
            <div class="ds-flex ds-items-center ds-gap-md">
                <?= Html::a(
                    '<i class="bi bi-broom"></i> ' . Yii::t('common', 'Обнулить задания'),
                    '/wipe/task-clear',
                    [
                        'class' => 'ds-btn ds-btn--success',
                        'data' => [
                            'confirm' => 'Вы уверены, что хотите обнулить все задания? Это действие нельзя отменить!',
                            'method' => 'post',
                        ],
                    ]
                ) ?>
                <?= Html::a(
                    '<i class="bi bi-arrow-clockwise"></i> ' . Yii::t('common', 'Очистить кэш'),
                    '/wipe/clear-cache',
                    [
                        'class' => 'ds-btn ds-btn--info',
                        'data' => [
                            'confirm' => 'Вы уверены, что хотите очистить кэш?',
                            'method' => 'post',
                        ],
                    ]
                ) ?>
            </div>
        </div>
    </div>
</div>
