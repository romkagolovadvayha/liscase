<?php

/** @var yii\web\View $this */
/** @var Select $model */

use common\models\box\Select;
use yii\bootstrap5\ActiveForm;
use frontend\widgets\Alert;
use yii\widgets\Pjax;
use yii\bootstrap5\Html;

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
    <figure class="mb-32 flex items-center justify-center w-full">
        <img src="<?= $model->imageOrig->getImagePubUrl() ?>" alt="<?= Yii::t('database', $model->name) ?>" class="w-300 h-300">
    </figure>

    <div class="access-card__list mb-10">
        <?= $form->field($model, 'drop_id',
                                                          [
                                                              'errorOptions'   => [
                                                                  'encode' => false,
                                                                  'class'  => 'help-block',
                                                              ],
                                                          ])
                                                  ->radioList($model->selectDrop, [
                                                      'class'  => 'access-card__list mb-10',
                                                      'item' => function ($index, \common\models\box\SelectDrop $label, $name, $checked, $value) use ($model) {
                                                          $id = 'option_' . $label->drop->id . '_' . $index;
                                                          $return = Html::radio($name, $label->drop_id == $model->drop_id, [
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
                                                          $wrapClass = ($label->drop_id == $model->drop_id) ? 'access-card access-card_active' : 'access-card';
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
    </div>

    <div class="relative z-1 grid gap-y-16">
        <h2 class="mb-8">
            <?=$model->drop->getRealPrice()?>
            <span class="icons icons_16px icons_16px_coin"></span>
        </h2>
        <p class="p3 text-text-teritiary">
            <?=Yii::t('database', $model->description)?>
        </p>
        <p class="p2 p-12 bg-background-teritiary rounded-8 flex items-center gap-x-12">
            <span class="icons icons_24px icons_24px_info"></span>
            <span>
                <?php if (!Yii::$app->settings->get('site_basketSite')): ?>
                    <?=Yii::t('common', 'Чтобы получить, введите /store в чат')?>
                <?php else: ?>
                    <?=Yii::t('common', 'Чтобы получить, перейдите на эту страницу')?> <a href="/store" target="_blank"><?=Yii::$app->settings->get('site_domain')?>/store</a>
                <?php endif; ?>
            </span>
        </p>
    </div>
</div>
<footer class="px-24 pb-24">
    <?= Alert::widget() ?>
    <?php if (Yii::$app->user->isGuest): ?>
            <a href="/auth/oauth?authclient=steam" class="market_entity_card_actions_btn btn_steam" title="Авторизация через Steam">
                <i class="fab fa-steam"></i> <span><?=Yii::t('common', 'Войти через Steam')?></span>
            </a>
    <?php else: ?>
        <input type="hidden" class="modal_form_product_buy" name="buy" value="1"/>
        <div class="modal_form_product_buttons">
            <button type="submit" id="buy_product" class="button-primary w-full">
                <span class="button__text"><?=Yii::t('common', 'Оплатить')?></span>
            </button>
        </div>
    <?php endif; ?>
</footer>
<?php ActiveForm::end(); ?>
<?php Pjax::end(); ?>
<div class="modal_preloader" id="product-loader"></div>
