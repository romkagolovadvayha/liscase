<?php

use common\models\servers\Servers;
use yii\web\View;

/** @var View $this */
/** @var \common\models\servers\Servers[] $servers */

$servers = \common\models\servers\Servers::find()
    ->cache(30)
    ->andWhere(['status' => Servers::STATUS_ACTIVE])
    ->all();
?>

<div class="Widgets-module__widgetWrapper widget_live">
    <div class="boxHeader widget_top_header">
        <h2 id="live_title"><?=Yii::t('common', 'Прямой эфир')?></h2>
        <span class="widget_top_header_heartbeat"><i class="fas fa-heartbeat"></i></span>
    </div>
    <div class="widget_live_body_wrap_wrap" id="live_body">
        <?php $checked = false; ?>
        <?php foreach ($servers as $server): ?>
            <?php if ($server->tag === 'max3'): ?>
                <input class="widget_live_checkbox" id="max3_live" type="radio" name="widget_live_servers" value="1" <?=$checked ? '' : 'checked'?>>
                <?php $checked = true; ?>
            <?php endif; ?>
            <?php if ($server->tag === 'nolimit'): ?>
                <input class="widget_live_checkbox" id="nolimit_live" name="widget_live_servers" value="2" type="radio" <?=$checked ? '' : 'checked'?>>
                <?php $checked = true; ?>
            <?php endif; ?>
            <?php if ($server->tag === 'classicx2'): ?>
                <input class="widget_live_checkbox" id="classicx2_live" name="widget_live_servers" value="3" type="radio" <?=$checked ? '' : 'checked'?>>
                <?php $checked = true; ?>
            <?php endif; ?>
            <?php if ($server->tag === 'solo'): ?>
                <input class="widget_live_checkbox" id="solo_live" name="widget_live_servers" value="4" type="radio" <?=$checked ? '' : 'checked'?>>
                <?php $checked = true; ?>
            <?php endif; ?>
        <?php endforeach; ?>
        <div class="widget_live_servers">
            <?php foreach ($servers as $server): ?>
                <?php if ($server->tag === 'max3'): ?>
                    <label id="max3_live_label" class="widget_live_servers_item" for="max3_live">MAX3</label>
                <?php endif; ?>
                <?php if ($server->tag === 'nolimit'): ?>
                    <label id="nolimit_live_label" class="widget_live_servers_item" for="nolimit_live">NO LIMIT</label>
                <?php endif; ?>
                <?php if ($server->tag === 'classicx2'): ?>
                    <label id="classicx2_live_label" class="widget_live_servers_item" for="classicx2_live">X2</label>
                <?php endif; ?>
                <?php if ($server->tag === 'solo'): ?>
                    <label id="solo_live_label" class="widget_live_servers_item" for="solo_live">SOLO</label>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div class="widget_live_body_wrap">
            <?php if ($this->beginCache('live_stats_wrap_' . Yii::$app->language, ['duration' => 30])): ?>
                <?= $this->render('@frontend/views/widgets/_live_stats_wrap'); ?>
                <?php $this->endCache(); ?>
            <?php endif; ?>
        </div>
    </div>
</div>