<?php
    /** @var array $response */

    $logo = \yii\helpers\ArrayHelper::getValue(\common\models\invoice\Deposit::getIconTypeList(), $response['type']);
?>
<div class="grid gap-y-24 px-24 mb-24" id="crypto-payment-form">
    <div class="relative payment-form z-1 grid gap-y-8">
        <div class="relative mt-12 text-center">
            <div class="mb-24 p1 text-center">
                <img src="<?=$logo?>" style="max-width: 180px;max-height: 80px;"/>
            </div>
            <?php if (extension_loaded('imagick')): ?>
                <img class="relative rounded-8 mb-24" src="<?= (new \Da\QrCode\QrCode($response['wallet']))->setSize(150)->writeDataUri(); ?>" alt="">
            <?php endif; ?>
            <div class="relative mb-12 btn-clipboard"
                 data-bs-toggle="tooltip"
                 data-bs-placement="right"
                 data-bs-title="<?=Yii::t('common', 'Скопировать кошелек')?>"
                 data-clipboard-text="<?=$response['wallet']?>"
                 data-message="<?=Yii::t('common', 'Кошелек скопирован в буфер обмена!')?>">
                <input class="search search_pay" value="<?=$response['wallet']?>" readonly="">
                <span class="icons icons_16px fas fa-copy"></span>
            </div>
            <p class="mt-24 p1 text-center">
                <?=Yii::t('common', 'Пополните кошелек выше на сумму')?> <b><?=$response['amount_exchange']?> <?=$response['exchange']?></b>
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
    <a href="/user/payment" id="buy_product" class="button button-teritiary">
        <span class="button__text"><i class="fas fa-long-arrow-alt-left"></i> <?=Yii::t('common', 'Другой способ оплаты')?></span>
    </a>
</footer>
<script>
    payClockdown(<?=$response['deadline']?>);
</script>