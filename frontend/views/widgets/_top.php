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
    <div class="boxHeader widget_top_header">
        <h2 id="top_title"><?=Yii::t('common', 'Топ игроков')?></h2>
        <span class="widget_top_header_crown"><i class="fas fa-crown"></i></span>
    </div>
    <div class="widget_live_body_wrap_wrap" id="top_body">
        <input class="widget_live_checkbox" id="max3_top" type="radio" name="widget_top_servers" value="1" checked>
        <input class="widget_live_checkbox" id="nolimit_top" name="widget_top_servers" value="2" type="radio">
        <input class="widget_live_checkbox" id="classicx2_top" name="widget_top_servers" value="3" type="radio">
        <input class="widget_live_checkbox" id="solo_top" name="widget_top_servers" value="4" type="radio">
        <div class="widget_live_servers">
            <label id="max3_top_label" class="widget_live_servers_item" for="max3_top">MAX3</label>
            <label id="nolimit_top_label" class="widget_live_servers_item" for="nolimit_top">NO LIMIT</label>
            <label id="classicx2_top_label" class="widget_live_servers_item" for="classicx2_top">X2</label>
            <label id="solo_top_label" class="widget_live_servers_item" for="solo_top">SOLO</label>
        </div>
        <div class="widget_top_body_wrap">
            <?php if ($this->beginCache('top_stats_wrap2' . Yii::$app->language, ['duration' => 300])): ?>
                <?= $this->render('@frontend/views/widgets/_top_stats_wrap'); ?>
                <?php $this->endCache(); ?>
            <?php endif; ?>
        </div>
    </div>
</div>