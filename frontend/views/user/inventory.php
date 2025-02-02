<?php

use yii\web\View;
use common\models\user\UserDrop;
use yii\widgets\ActiveForm;
use frontend\widgets\Alert;

/** @var View $this */

$user = Yii::$app->user->identity;
$this->title = Yii::t('common', "Корзина") . " - {$user->userProfile->name}";

/** @var UserDrop[] $userDrops */
$userDrops = $user->getUserDrop()
    ->andWhere(['status' => UserDrop::STATUS_ACTIVE])
    ->orderBy(['id' => SORT_DESC])
    ->all();
?>

<?php
$lang = substr(Yii::$app->language, 0, 2);
$this->registerJs(<<<JS
        var blocked_products = $('.blocked_products_timer');
        for (var i = 0; i < blocked_products.length; i++) {
            var dateTime = $(blocked_products[i]).attr('data-time');
            var left = moment.unix(dateTime);
            $(blocked_products[i]).html(left.locale('{$lang}').fromNow());
        }
JS
);
?>
<?= Alert::widget() ?>
<section class="tasks">
    <h2 class="tasks__title">
        <?=Yii::t('common', 'Моя корзина')?>
        <span
                class="icons icons_24px icons_24px_info icons_hover"
                data-bs-toggle="tooltip"
                data-bs-placement="right"
                data-bs-title="<?=Yii::t('common', 'В этом разделе отображаются все вещи, которые вы можете вывести на сервере.')?>"
        ></span>
    </h2>
    <?php if (!empty($userDrops)):?>
        <section class="page-stats__block-without-hover">
            <div class="page-stats__categories">
                <?php foreach ($userDrops as $userDrop): ?>
                    <?php foreach ($userDrop->drop as $drop): ?>
                        <?php $blocked = !empty($drop->blocked_at) && strtotime($drop->blocked_at) > time(); ?>
                        <div class="page-stats__category category<?php if ($blocked): ?> blocked<?php endif; ?>">
                            <h5 class="category__count-and-img">
                                <span>x<?= $userDrop->count ?></span>
                                <img src="<?= $drop->imageOrig->getImagePubUrl() ?>" alt="<?=Yii::t('database', $drop->name)?>" class="w-64 h-64 object-contain">
                            </h5>
                            <p class="category__title"><?=Yii::t('database', $drop->name)?></p>
                            <div class="page-stats__category__footer mt-6">
                                <?php $form = ActiveForm::begin(); ?>
                                <input type="hidden" name="sell" value="<?=$userDrop->id?>"/>
                                <?php if (empty($userDrop->box_id) && empty($userDrop->sets_id)): ?>
                                    <button type="submit" class="button-secondary button-size__s h-36 w-full" style="padding-top: 6px; padding-bottom: 6px" data-bs-dismiss="modal">
                                        <span class="button__text"><?=Yii::t('common', 'Вернуть')?> <span class="badge bg-danger">+<?=$drop->getRealPrice()?></span></span>
                                    </button>
                                <?php else: ?>
                                    <button type="submit" class="button-secondary button-size__s h-36 w-full" style="padding-top: 6px; padding-bottom: 6px" disabled>
                                        <span class="button__text"><?=Yii::t('common', 'Нет возврата')?></span>
                                    </button>
                                <?php endif; ?>
                                <?php ActiveForm::end(); ?>
                            </div>
                            <?php if ($blocked): ?>
                                <span class="icons icons_24px icons_24px_info icons_hover page-stats__category__blocked__info" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?=Yii::t('common', 'Товар сейчас находится в вайп-блоке')?>"></span>
                                <div class="page-stats__category__blocked"></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php else:?>
        <p class="mt-4">
            <?=Yii::t('common', 'В вашем инвентаре пока нет вещей')?>
        </p>
    <?php endif;?>
</section>
