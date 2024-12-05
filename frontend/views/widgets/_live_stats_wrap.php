<?php

/** @var \common\models\servers\Servers[] $servers */

use common\models\servers\Servers;

$servers = \common\models\servers\Servers::find()
                                         ->cache(30)
                                         ->andWhere(['status' => Servers::STATUS_ACTIVE])
                                         ->orderBy(['sort' => SORT_ASC])
                                         ->all();

?>

<?php foreach ($servers as $server): ?>
<div class="boxBody widget_live_body" id="<?=$server->tag?>_live_body">
<?=$this->render('@frontend/views/widgets/_live_stats', ['server' => $server])?>
    <div class="footer_button_stats_wrap">
        <a href="/stats?server=<?=$server->tag?>" class="footer_button_stats"><i class="fa-solid fa-chart-pie"></i> <?=Yii::t('common', 'Вся статистика')?></a>
    </div>
</div>
<?php endforeach; ?>