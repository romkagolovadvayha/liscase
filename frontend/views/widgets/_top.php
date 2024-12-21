<?php

use yii\web\View;

/** @var View $this */
/** @var \common\models\servers\Servers[] $servers */

$servers = \common\models\servers\Servers::find()
                                         ->cache(30)
                                         ->andWhere(['status' => \common\models\servers\Servers::STATUS_ACTIVE])
                                         ->orderBy(['sort' => SORT_ASC])
                                         ->all();

ini_set('memory_limit', '512M');
?>

<div class="Widgets-module__widgetWrapper widget_live">
    <div class="boxHeader widget_top_header">
        <h2 id="top_title"><?=Yii::t('common', 'Топ игроков')?></h2>
        <span class="widget_top_header_crown"><i class="fas fa-crown"></i></span>
    </div>
    <div class="widget_live_body_wrap_wrap" id="top_body">
        <?php $checked = false; ?>
        <?php foreach ($servers as $server): ?>
            <?php if ($server->tag === 'max3'): ?>
                <input class="widget_live_checkbox" id="max3_top" type="radio" name="widget_top_servers" value="1" <?=$checked ? '' : 'checked'?>>
                <?php $checked = true; ?>
            <?php endif; ?>
            <?php if ($server->tag === 'nolimit'): ?>
                <input class="widget_live_checkbox" id="nolimit_top" name="widget_top_servers" value="2" type="radio" <?=$checked ? '' : 'checked'?>>
                <?php $checked = true; ?>
            <?php endif; ?>
            <?php if ($server->tag === 'classicx2'): ?>
                <input class="widget_live_checkbox" id="classicx2_top" name="widget_top_servers" value="3" type="radio" <?=$checked ? '' : 'checked'?>>
                <?php $checked = true; ?>
            <?php endif; ?>
            <?php if ($server->tag === 'solo'): ?>
                <input class="widget_live_checkbox" id="solo_top" name="widget_top_servers" value="4" type="radio" <?=$checked ? '' : 'checked'?>>
                <?php $checked = true; ?>
            <?php endif; ?>
        <?php endforeach; ?>
        <div class="widget_live_servers">
            <?php foreach ($servers as $server): ?>
                <?php if ($server->tag === 'max3'): ?>
                    <label id="max3_top_label" class="widget_live_servers_item" for="max3_top">MAX3</label>
                <?php endif; ?>
                <?php if ($server->tag === 'nolimit'): ?>
                    <label id="nolimit_top_label" class="widget_live_servers_item" for="nolimit_top">NO LIMIT</label>
                <?php endif; ?>
                <?php if ($server->tag === 'classicx2'): ?>
                    <label id="classicx2_top_label" class="widget_live_servers_item" for="classicx2_top">X2</label>
                <?php endif; ?>
                <?php if ($server->tag === 'solo'): ?>
                    <label id="solo_top_label" class="widget_live_servers_item" for="solo_top">SOLO</label>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div class="widget_top_body_wrap">
            <?php if ($this->beginCache('top_stats_wrap_' . Yii::$app->language, ['duration' => 180])): ?>
                <?= $this->render('@frontend/views/widgets/_top_stats_wrap'); ?>
                <?php $this->endCache(); ?>
            <?php endif; ?>
        </div>
    </div>
</div>