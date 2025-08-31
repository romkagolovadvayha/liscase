<?php

/** @var yii\web\View $this */
/** @var \frontend\forms\market\BuyForm $drop */

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use frontend\widgets\Alert;
use yii\widgets\Pjax;
use common\models\box\Drop;
use common\models\box\DropDrop;

$user = Yii::$app->user->identity;
?>
<?php Pjax::begin(
    [
        'id'              => 'buy-container-pjax',
        'enablePushState' => false
    ]
); ?>
<?php $form = ActiveForm::begin(
    [
        'enableClientValidation' => false,
        'enableAjaxValidation'   => false,
        'id'                     => 'buy-container',
        'options'                => [
            'data-pjax' => 1,
        ],
    ]
); ?>
<div class="grid gap-y-24 px-24 mb-24">
    <figure class="mb-6 flex items-center justify-center w-full relative">
        <img src="/images/design/modal/light.png" alt="" class="absolute">
        <img src="<?= $drop->imageOrig->getImagePubUrl() ?>" alt="<?= Yii::t('database', $drop->name) ?>" class="w-180 h-180 relative z-1">
    </figure>

    <div class="relative z-1 grid gap-y-16">
        <?php if ($drop->drop_type !== Drop::TYPE_SELECT && !empty($drop->subDrops)): ?>
            <div class="inventory__list mb-10">
                <?php foreach ($drop->subDrops as $subDrop): ?>
                    <?php $blocked = !empty($subDrop->drop->blocked_at) && strtotime($subDrop->drop->blocked_at) > time(); ?>
                    <div class="inventory"<?=$blocked ? ' aria-disabled="true"' : ''?>>
                        <p class="inventory__boost">x<?=$subDrop->count?></p>
                        <img class="inventory__image" src="<?= $subDrop->drop->imageOrig->getImagePubUrl() ?>" alt="<?= Yii::t('database', $subDrop->drop->name) ?>">
                        <p class="inventory__title"><?= Yii::t('database', $subDrop->drop->name) ?></p>
                    </div>
                    <!--<?php if ($blocked): ?>
                            <div class="modal_form_products_item_blocked_wrap">
                                <div class="modal_form_products_item_blocked_title"><?=Yii::t('common', 'Вайп блок')?></div>
                                <div class="modal_form_products_item_blocked_timer blocked_products_timer" data-time="<?=strtotime($subDrop->drop->blocked_at)?>"><?=$subDrop->drop->blocked_at?></div>
                            </div>
                        <?php endif; ?>-->
                <?php endforeach; ?>
            </div>
        <?php elseif ($drop->drop_type === Drop::TYPE_SELECT): ?>
                    <?= $form->field($drop, 'drop_id',
                             [
                                 'errorOptions'   => [
                                     'encode' => false,
                                     'class'  => 'help-block',
                                 ],
                             ])
                     ->radioList($drop->subDrops, [
                         'class'  => 'access-card__list mb-10',
                         'item' => function ($index, DropDrop $label, $name, $checked, $value) use ($drop) {
                             $id = 'option_' . $label->drop->id . '_' . $index;
                             $return = Html::radio($name, $label->drop_id == $drop->drop_id, [
                                 'id'    => $id,
                                 'value' => $label->drop->id,
                                 'class' => 'modal_form_product_select_item_radio access-card__radio',
                             ]);
                             $img = Html::img($label->drop->imageOrig->getImagePubUrl(), [
                                 'class' => 'access-card__image',
                             ]);
                             $text = Html::tag('span', $label->drop->getRealPrice());
                             $textWrap = Html::tag('span', $text . ' <span class="icons icons_16px icons_16px_coin"></span>', [
                                 'class' => 'access-card__title',
                             ]);
                             $wrapClass = ($label->drop_id == $drop->drop_id) ? 'access-card access-card_active' : 'access-card';
                             $return .= Html::label($img . $textWrap, $id, [
                                 'class' => $wrapClass,
                             ]);
                             $wrap = Html::tag('div', $return, [
                                 'class' => 'access-card__wrap',
                                 'data-bs-toggle' => 'tooltip',
                                 'data-bs-placement' => 'bottom',
                                 'data-bs-title' => Yii::t('database', $label->drop->name),
                             ]);
                             return $wrap;
                         },
                     ])
                     ->label(false); ?>
        <?php endif; ?>
        <?php if (!empty($drop->drop)): ?>
            <div class="mb-1">
                <?=$drop->drop->name?>
            </div>
        <?php endif; ?>
        <h2 class="mb-8">
            <?php if (!empty($drop->drop)): ?>
                <?=$drop->drop->getRealPrice()?>
            <?php else: ?>
                <?=$drop->getRealPrice()?>
            <?php endif; ?>
            <span class="icons icons_16px icons_16px_coin"></span>
        </h2>
        <p class="p3 text-text-teritiary">
            <?php if (!empty($drop->drop)): ?>
                <?=Yii::t('database', $drop->drop->description)?>
            <?php else: ?>
                <?=Yii::t('database', $drop->description)?>
            <?php endif; ?>
        </p>
        <?php if (empty($drop->subDrops)): ?>
            <p class="p2 text-text-secondary"><?=Yii::t('common', 'Количество')?>: х<?=$drop->count?></p>
        <?php endif; ?>
        <p class="p2 p-12 bg-background-teritiary rounded-8 flex items-center gap-x-12">
            <span class="icons icons_24px icons_24px_info"></span>
            <span>
                <?php if (!Yii::$app->settings->get('site_basketSite')): ?>
                    <?=Yii::t('common', 'Чтобы получить, введите /store в чат')?>
                <?php else: ?>
                    <?=Yii::t('common', 'Чтобы получить, перейдите на эту страницу')?> <a href="/store" target="_blank"><?=Yii::$app->settings->get('site_domain')?>/store</a>
                <?php endif; ?>
            </span>
<!--            <span>Чтобы получить, введите <span class="text-link-color-default command_to_bot cursor-pointer" data-bs-toggle="tooltip" data-bs-title="Скопировать команду">/store</span> в чат</span>-->
        </p>
        <?php if ($user->getFloatingPricePercent() > 0): ?>
        <p class="p3 text-center gap-x-12" data-bs-toggle="tooltip"
           data-bs-placement="top"
           data-bs-html="true"
           data-bs-title="<?=Yii::t('common', 'Учитываются покупки этого товара за последние 3 дня, каждая следующая покупка увеличивает стоимость на +{PARAM_PERCENT_DROP}% за этот товар.<br/><br/>Покупки товара другими игроками, не влияют на вашу стоимость.', ['PARAM_PERCENT_DROP' => $user->getFloatingPricePercent()])?>">
<!--            <span>-->
                    <?=Yii::t('common', 'На этот товар действует плавающая цена: <span class="text-link-color-default">+{PARAM_PERCENT_DROP}%</span> за покупку.', ['PARAM_PERCENT_DROP' => $user->getFloatingPricePercent()])?>
<!--            </span>-->
<!--            <span>Чтобы получить, введите <span class="text-link-color-default command_to_bot cursor-pointer" data-bs-toggle="tooltip" data-bs-title="Скопировать команду">/store</span> в чат</span>-->
        </p>
        <?php endif; ?>
    </div>
</div>
<footer class="px-24 pb-24">
    <?php if (Yii::$app->user->isGuest): ?>
        <button class="button-danger w-full">
            <a href="/auth/oauth?authclient=steam" class="button__text" title="Авторизация через Steam">
                <i class="fab fa-steam"></i> <span><?=Yii::t('common', 'Войти через Steam')?></span>
            </a>
        </button>
    <?php else: ?>
        <?= Alert::widget() ?>
        <input type="hidden" class="modal_form_product_buy" name="buy" value="1"/>
        <div class="modal_form_product_buttons">
            <button class="button-primary w-full" id="buy_product" type="submit">
                <span class="button__text"><?=Yii::t('common', 'Оплатить')?></span>
            </button>
        </div>
    <?php endif; ?>
</footer>
<?php ActiveForm::end(); ?>
<?php Pjax::end(); ?>
<div class="modal_preloader" id="product-loader"></div>