<?php

use common\models\box\Box;
use common\models\servers\Servers;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

/** @var $dataProvider */
/** @var $searchModel */
/** @var $model Box */

$this->title = Yii::t('common', 'Вайп');
?>
<?=\frontend\widgets\Alert::widget()?>

<style>
.wipe-page {
    padding: 24px;
    max-width: 1400px;
    margin: 0 auto;
}

.wipe-section {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    padding: 24px;
    margin-bottom: 24px;
}

.wipe-section-title {
    font-size: 18px;
    font-weight: 600;
    margin: 0 0 16px 0;
    color: #333;
    display: flex;
    align-items: center;
    gap: 8px;
}

.wipe-section-title i {
    color: #6c757d;
}

.wipe-buttons-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
}

.wipe-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px 20px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
    border: 1px solid transparent;
    white-space: nowrap;
}

.wipe-button:hover:not(.disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.wipe-button.btn-success {
    background: #28a745;
    color: #fff;
    border-color: #28a745;
}

.wipe-button.btn-success:hover:not(.disabled) {
    background: #218838;
    border-color: #1e7e34;
}

.wipe-button.btn-default,
.wipe-button.disabled {
    background: #e9ecef;
    color: #6c757d;
    border-color: #dee2e6;
    cursor: not-allowed;
    opacity: 0.6;
}

.wipe-button.btn-primary {
    background: #007bff;
    color: #fff;
    border-color: #007bff;
}

.wipe-button.btn-primary:hover:not(.disabled) {
    background: #0056b3;
    border-color: #004085;
}

.wipe-button.btn-danger {
    background: #dc3545;
    color: #fff;
    border-color: #dc3545;
}

.wipe-button.btn-danger:hover:not(.disabled) {
    background: #c82333;
    border-color: #bd2130;
}

.wipe-server-item {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.wipe-server-name {
    font-weight: 500;
}

.wipe-server-file {
    font-size: 12px;
    color: #6c757d;
    font-family: monospace;
    padding: 4px 8px;
    background: #f8f9fa;
    border-radius: 4px;
    word-break: break-all;
}

.wipe-divider {
    height: 1px;
    background: #e9ecef;
    margin: 32px 0;
    border: none;
}

@media (max-width: 768px) {
    .wipe-buttons-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="wipe-page">
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
    ?>

    <div class="wipe-section">
        <h3 class="wipe-section-title">
            <i class="fas fa-ban"></i>
            <?= Yii::t('common', 'Заблокировать предметы') ?>
        </h3>
        <div class="wipe-buttons-grid">
            <?php foreach ($servers as $server): ?>
                <?php 
                $disabled = Yii::$app->cache->get("WIPE_actionBlock_{$server->id}");
                $class = $disabled ? 'wipe-button btn-default disabled' : 'wipe-button btn-success';
                ?>
                <?= Html::a(
                    '<i class="fas fa-server"></i> ' . Html::encode($server->name),
                    '/wipe/block?id=' . $server->id,
                    ['class' => $class]
                ) ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="wipe-section">
        <h3 class="wipe-section-title">
            <i class="fas fa-trophy"></i>
            <?= Yii::t('common', 'Начислить награды за топы') ?>
        </h3>
        <div class="wipe-buttons-grid">
            <?php foreach ($servers as $server): ?>
                <?php 
                $disabled = Yii::$app->cache->get("WIPE_actionTop_{$server->tag}");
                $class = $disabled ? 'wipe-button btn-default disabled' : 'wipe-button btn-success';
                ?>
                <?= Html::a(
                    '<i class="fas fa-server"></i> ' . Html::encode($server->name),
                    '/wipe/top?server=' . $server->tag,
                    ['class' => $class]
                ) ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="wipe-section">
        <h3 class="wipe-section-title">
            <i class="fas fa-sync-alt"></i>
            <?= Yii::t('common', 'Сгенерировать карты') ?>
        </h3>
        <div class="wipe-buttons-grid">
            <?php foreach ($serversNotSecret as $server): ?>
                <?php 
                $disabled = Yii::$app->cache->get("WIPE_actionGenerateMap4_{$server->id}");
                $class = $disabled ? 'wipe-button btn-default disabled' : 'wipe-button btn-success';
                ?>
                <?= Html::a(
                    '<i class="fas fa-server"></i> ' . Html::encode($server->name),
                    '/wipe/generate-map?id=' . $server->id,
                    ['class' => $class]
                ) ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="wipe-section">
        <h3 class="wipe-section-title">
            <i class="fas fa-map-marked-alt"></i>
            <?= Yii::t('common', 'Зафиксировать карту') ?>
        </h3>
        <div class="wipe-buttons-grid">
            <?php foreach ($serversNotSecret as $server): ?>
                <div class="wipe-server-item">
                    <?= Html::a(
                        '<i class="fas fa-server"></i> <span class="wipe-server-name">' . Html::encode($server->name) . '</span>',
                        '/wipe/select-map?id=' . $server->id,
                        ['class' => 'wipe-button btn-primary']
                    ) ?>
                    <?php if (!empty($server->map_list_id) && $server->mapList): ?>
                        <?php
                        $map = $server->mapList;
                        // Получаем имя файла из URL карты или используем стандартное имя
                        $fileName = '';
                        if (!empty($map->url)) {
                            $fileName = basename(parse_url($map->url, PHP_URL_PATH));
                        }
                        if (empty($fileName)) {
                            $fileName = $server->tag . '.map';
                        }
                        ?>
                        <div class="wipe-server-file">
                            <i class="fas fa-file"></i> <?= Html::encode($fileName) ?>
                        </div>
                    <?php else: ?>
                        <div class="wipe-server-file" style="color: #adb5bd; font-style: italic;">
                            <i class="fas fa-info-circle"></i> <?= Yii::t('common', 'Карта не зафиксирована') ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="wipe-section">
        <h3 class="wipe-section-title">
            <i class="fas fa-trash-alt"></i>
            <?= Yii::t('common', 'Удалить не зафиксированные карты') ?>
        </h3>
        <div class="wipe-buttons-grid">
            <?php 
            $disabled = Yii::$app->cache->get("WIPE_actionDeleteUnfixedMaps");
            $class = $disabled ? 'wipe-button btn-default disabled' : 'wipe-button btn-danger';
            ?>
            <?= Html::a(
                '<i class="fas fa-trash"></i> ' . Yii::t('common', 'Удалить'),
                '/wipe/delete-unfixed-maps',
                ['class' => $class]
            ) ?>
        </div>
    </div>

    <hr class="wipe-divider">

    <div class="wipe-section">
        <h3 class="wipe-section-title">
            <i class="fas fa-ticket-alt"></i>
            <?= Yii::t('common', 'Обнулить промокод') ?>
        </h3>
        <div class="wipe-buttons-grid">
            <?= Html::a(
                '<i class="fas fa-redo"></i> ' . Yii::t('common', 'WIPE'),
                '/wipe/promocode',
                ['class' => 'wipe-button btn-success']
            ) ?>
        </div>
    </div>

    <div class="wipe-section">
        <h3 class="wipe-section-title">
            <i class="fas fa-tasks"></i>
            <?= Yii::t('common', 'Задания') ?>
        </h3>
        <div class="wipe-buttons-grid">
            <?= Html::a(
                '<i class="fas fa-broom"></i> ' . Yii::t('common', 'Обнулить задания'),
                '/wipe/task-clear',
                ['class' => 'wipe-button btn-success']
            ) ?>
            <?= Html::a(
                '<i class="fas fa-cache"></i> ' . Yii::t('common', 'Очистить кэш'),
                '/wipe/clear-cache',
                ['class' => 'wipe-button btn-success']
            ) ?>
        </div>
    </div>
</div>
