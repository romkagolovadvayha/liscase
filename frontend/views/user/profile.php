<?php

use frontend\forms\profile\ProfileForm;
use yii\web\View;
use frontend\widgets\Alert;
use yii\bootstrap5\ActiveForm;

/** @var View $this */
/** @var ProfileForm $model */

$user = Yii::$app->user->identity;
$this->title = Yii::t('common', "Профиль") . " - {$user->userProfile->name}";
?>

<main id="main" role="main" class="mt-5">
    <div class="container">
        <?= $this->render('@frontend/views/layouts/_inventory_menu'); ?>
        <div class="tab-content mt-2">
            <?= Alert::widget() ?>
            <?php $form = ActiveForm::begin(); ?>
            <label class="form-label" for="profileform-trade_link">
                Вставьте свою <a href="https://steamcommunity.com/id/me/tradeoffers/privacy#trade_offer_access_url" target="_blank">ссылку</a> на обмен
            </label>
            <?= $form->field($model, 'trade_link')->label(false)->textInput(); ?>
            <button type="submit" class="btn"><?=Yii::t('common', 'Сохранить')?></button>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</main>
