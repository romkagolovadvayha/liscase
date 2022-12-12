<?php

use yii\web\View;
use common\models\user\UserDrop;
use yii\widgets\ActiveForm;
use frontend\forms\battle\AddBattleForm;
use frontend\widgets\Alert;

/** @var View $this */
/** @var AddBattleForm $model */

$user = Yii::$app->user->identity;
$this->title = Yii::t('common', "Создать сражение") . " - {$user->userProfile->name}";

/** @var UserDrop[] $userDrops */
$userDrops = $user->getUserDrop()
                  ->andWhere(['status' => UserDrop::STATUS_ACTIVE])
                  ->all();
$priceTotal = 0;
?>

<main id="main" role="main" class="mt-5">
    <div class="container">
        <?= $this->render('@frontend/views/layouts/_battle_menu'); ?>
        <div class="tab-content mt-2">
            <?= Alert::widget() ?>
            <p><?=Yii::t('common', 'Выберите вашу ставку для создания сражения')?></p>
            <?php if (!empty($userDrops)):?>
                <div class="box_cards_wrapper">
                    <div class="box_cards mt-4">
                        <?php foreach ($userDrops as $userDrop): ?>
                            <?php foreach ($userDrop->drop as $drop): ?>
                                <div class="box_cards_card<?=' drop_card level' . $drop->getLevel()?>">
                                    <div class="box_cards_card_info">
                                        <div class="box_cards_card_info_title"><?=Yii::t('common', $drop->getShortName())?></div>
                                    </div>
                                    <div class="box_cards_card_image">
                                        <img src="<?= $drop->imageOrig->getImagePubUrl() ?>" alt="<?=Yii::t('database', $drop->name)?>">
                                    </div>
                                    <?php $form = ActiveForm::begin(); ?>
                                    <?= $form->field($model, 'user_drop_id')->label(false)->hiddenInput(['value' => $userDrop->id]); ?>
                                    <button type="submit" class="btn box_cards_card_btn" data-bs-dismiss="modal">
                                        <?=Yii::t('common', 'Ставка')?>
                                        <span class="badge bg-danger"><?=$drop->priceCeil?></span>
                                    </button>
                                    <?php ActiveForm::end(); ?>
                                </div>
                                <?php $priceTotal += $drop->priceCeil; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else:?>
                <p class="mt-4">
                    <?=Yii::t('common', 'В вашем инвентаре пока нет вещей')?>
                <div class="actions_btns">
                    <a href="/user/inventory" class="btn"><?=Yii::t('common', 'Перейти в инвентарь')?></a>
                </div>
                </p>
            <?php endif;?>
        </div>
    </div>
</main>
