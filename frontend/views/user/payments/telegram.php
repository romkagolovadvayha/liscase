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
                <?=Yii::t('common', 'Чтобы пополнить зарубежной картой напишите нашему боту в Telegram ваш steamId')?>
            </p>
            <p class="mt-24 p1 text-center">
                <a href="https://t.me/<?=$response['username']?>" target="_blank" class="mt-24 p1 mb-24">
                    <span class="button__text">@<?=$response['username']?></span>
                </a>
            </p>
        </div>
    </div>
</div>
<footer class="px-24 pb-24 flex justify-content-center">
    <a href="/user/payment" class="button button-teritiary button-size__s h-36" style="padding-top: 6px; padding-bottom: 6px">
        <span class="button__text"><i class="fas fa-long-arrow-alt-left"></i> <?=Yii::t('common', 'Другой способ оплаты')?></span>
    </a>
</footer>