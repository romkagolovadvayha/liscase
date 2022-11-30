<?php

use yii\web\View;
use common\models\user\UserDrop;
use yii\widgets\ActiveForm;

/** @var View $this */

$user = Yii::$app->user->identity;
$this->title = Yii::t('common', "Мои вещи") . " - {$user->userProfile->name}";

/** @var UserDrop[] $userDrops */
$userDrops = $user->getUserDrop()
    ->andWhere(['status' => UserDrop::STATUS_ACTIVE])
    ->all();
$priceTotal = 0;
?>

<main id="main" role="main" class="mt-5">
    <div class="container">
        <?= $this->render('@frontend/views/layouts/_inventory_menu'); ?>
        <div class="tab-content mt-2">
            <div class="box_cards_wrapper">
                <?php if (!empty($userDrops)):?>
                    <div class="box_cards mt-4">
                        <?php foreach ($userDrops as $userDrop): ?>
                            <?php foreach ($userDrop->drop as $drop): ?>
                                <div class="box_cards_card<?=' drop_card level' . $drop->getLevel()?>">
                                    <div class="box_cards_card_info">
                                        <div class="box_cards_card_info_title"><?=Yii::t('common', $drop->getShortName())?></div>
                                    </div>
                                    <div class="box_cards_card_image">
                                        <img src="<?= $drop->imageOrig->getImagePubUrl() ?>" alt="<?=Yii::t('common', $drop->name)?>">
                                    </div>
                                    <?php $form = ActiveForm::begin(); ?>
                                    <input type="hidden" name="sell" value="<?=$userDrop->id?>"/>
                                    <button type="submit" class="btn box_cards_card_btn" data-bs-dismiss="modal">
                                        <?=Yii::t('common', 'Продать')?>
                                        <span class="badge bg-danger">+<?=$drop->price?></span>
                                    </button>
                                    <?php ActiveForm::end(); ?>
                                </div>
                                <?php $priceTotal += $drop->price; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                    <div class="actions_btns">
                        <a href="/payout/index" class="btn"><?=Yii::t('common', 'Вывод')?></a>
                        <?php $form = ActiveForm::begin(); ?>
                        <input type="hidden" name="sell" value="all"/>
                        <button type="submit" class="btn" data-bs-dismiss="modal">
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">+<?=$priceTotal?> ₽</span>
                            <?=Yii::t('common', 'Продать все')?>
                        </button>
                        <?php ActiveForm::end(); ?>
                    </div>
                </div>
            <?php else:?>
                <p class="mt-4">
                    В вашем инвентаре пока нет вещей
                </p>
            <?php endif;?>
        </div>
    </div>
</main>
