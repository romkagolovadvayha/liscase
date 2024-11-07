<?php
/** @var yii\web\View $this */
?>
<style>
    .servers_daily_bonus {
        text-align: left;
    }
    .servers_daily_bonus_commands {
        margin-top: 10px;
    }
    .servers_daily_bonus_command {
        color: #aaf16e;
        font-weight: 700;
    }
</style>
<div class="servers_daily_bonus">
    <p>Связи с тем, что платежные системы против размещения на сайте ежедневного крейта.</p>
    <p>Ежедневный бонус мы перенесли в наш телеграм бот <a href="https://t.me/<?=Yii::$app->params['tgPersonalBot']?>" target="_blank">@<?=Yii::$app->params['tgPersonalBot']?></a></p>
    <div class="servers_daily_bonus_commands">
        Напишите <span class="servers_daily_bonus_command"><b>/bonus</b></span> ТГ боту, чтобы получить награду
    </div>
</div>