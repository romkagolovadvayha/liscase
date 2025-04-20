<?php
/** @var array $response */

$logo = \yii\helpers\ArrayHelper::getValue(\common\models\invoice\Deposit::getIconTypeList(), $response['type']);
?>
<div class="grid gap-y-24 px-24 mb-24" id="crypto-payment-form">
    <div class="relative payment-form z-1 grid gap-y-8">
        <div class="relative mt-12 text-center">
            <div class="mb-24 p1 text-center">
                <img src="<?=$logo?>" style="width: 180px;max-height: 50px;"/>
            </div>
            <p class="mt-24 p1 text-center">
                <?=Yii::t('common', 'После покупки ожидайте администратора, не забывайте предоставить свой SteamID64!')?>
            </p>
            <p class="mt-24 p1 text-center">
                <a href="<?=$response['url']?>" target="_blank" class="button button-primary">
                    <span class="button__text">
                        <?=Yii::t('common', 'Перейти к оплате')?>
                    </span>
                </a>
            </p>
            <div class="flex justify-content-center items-center justify-space-between mt-36 mb-12 transition-all">
                <h4 class="flex justify-content-center items-center gap-x-12"><?=Yii::t('common', 'Осталось времени')?></h4>
            </div>
            <div class="clockdown mt-12">
                <span class="clockdown_minutes">10</span>
                <span class="clockdown_separator">:</span>
                <span class="clockdown_seconds">00</span>
            </div>
        </div>
    </div>
</div>
<footer class="px-24 pb-24 flex justify-content-center">
    <a href="/user/payment" class="button button-teritiary button-size__s h-36" style="padding-top: 6px; padding-bottom: 6px">
        <span class="button__text"><i class="fas fa-long-arrow-alt-left"></i> <?=Yii::t('common', 'Другой способ оплаты')?></span>
    </a>
</footer>
<script>
    payClockdown(<?=$response['deadline']?>);
</script>