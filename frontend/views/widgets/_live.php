<?php

use yii\web\View;

/** @var View $this */
/** @var \common\models\servers\Servers[] $servers */

$servers = \common\models\servers\Servers::find()
    ->andWhere(['!=', 'db_host', ''])
    ->cache(30)
    ->all();

?>

<div class="Widgets-module__widgetWrapper widget_live">
    <input class="widget_live_checkbox" id="live_page" type="radio" name="widget_live_page" value="1" checked>
    <input class="widget_live_checkbox" id="top_page" type="radio" name="widget_live_page" value="2">
    <div class="boxHeader widget_top_header">
        <h2 id="top_title"><?=Yii::t('common', 'Топ')?></h2>
        <h2 id="live_title"><?=Yii::t('common', 'Прямой эфир')?></h2>
        <div class="widget_live_pages">
            <label id="live_page_label" class="widget_live_servers_item" for="live_page"><i class="fa-solid fa-heart-pulse"></i> LIVE</label>
            <label id="top_page_label" class="widget_live_servers_item" for="top_page"><i class="fa-solid fa-person-arrow-up-from-line"></i> <?=Yii::t('common', 'Топ')?></label>
        </div>
    </div>
    <div class="widget_live_body_wrap_wrap" id="live_body">
        <input class="widget_live_checkbox" id="max3_live" type="radio" name="widget_live_servers" value="1" checked>
        <input class="widget_live_checkbox" id="nolimit_live" name="widget_live_servers" value="2" type="radio">
        <input class="widget_live_checkbox" id="pve_live" name="widget_live_servers" value="3" type="radio">
        <div class="widget_live_servers">
            <label id="max3_live_label" class="widget_live_servers_item" for="max3_live">MAX3</label>
            <label id="nolimit_live_label" class="widget_live_servers_item" for="nolimit_live">NO LIMIT</label>
            <label id="pve_live_label" class="widget_live_servers_item" for="pve_live">PVE</label>
        </div>
        <div class="widget_live_body_wrap">
            <?php if ($this->beginCache('live_stats_wrap' . Yii::$app->language, ['duration' => 30])): ?>
                <?= $this->render('@frontend/views/widgets/_live_stats_wrap'); ?>
                <?php $this->endCache(); ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="widget_live_body_wrap_wrap" id="top_body">
        <input class="widget_live_checkbox" id="max3_top" type="radio" name="widget_top_servers" value="1" checked>
        <input class="widget_live_checkbox" id="nolimit_top" name="widget_top_servers" value="2" type="radio">
        <input class="widget_live_checkbox" id="pve_top" name="widget_top_servers" value="3" type="radio">
        <div class="widget_live_servers">
            <label id="max3_top_label" class="widget_live_servers_item" for="max3_top">MAX3</label>
            <label id="nolimit_top_label" class="widget_live_servers_item" for="nolimit_top">NO LIMIT</label>
            <label id="pve_top_label" class="widget_live_servers_item" for="pve_top">PVE</label>
        </div>
        <div class="widget_top_body_wrap">
            <?php if ($this->beginCache('top_stats_wrap' . Yii::$app->language, ['duration' => 300])): ?>
                <?= $this->render('@frontend/views/widgets/_top_stats_wrap'); ?>
            <?php $this->endCache(); ?>
            <?php endif; ?>
        </div>
    </div>
</div>