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
<div class="container-fluid mb-5">
    <div class="main_wrap">
        <aside>
            <?= $this->render('@frontend/views/widgets/_profile'); ?>
            <?php echo $this->render('@frontend/views/layouts/_promocode_line'); ?>
        </aside>
        <main id="main" role="main">
            <div class="main_child">
                <div class="profile_content">
                    <div class="profile_content_header">
                        <?=Yii::t('common', "Профиль")?>
                    </div>
                    <div class="profile_content_body">
                        <?= Alert::widget() ?>
                        <?php $form = ActiveForm::begin(); ?>
                        <label class="form-label" for="profileform-trade_link">
                            <?=Yii::t('common', "Вставьте свою")?> <a href="https://steamcommunity.com/id/me/tradeoffers/privacy#trade_offer_access_url" target="_blank"><?=Yii::t('common', "ссылку")?></a> <?=Yii::t('common', "на обмен")?>
                        </label>
                        <?= $form->field($model, 'trade_link')->label(false)->textInput(); ?>
                        <button type="submit" class="btn"><?=Yii::t('common', 'Сохранить')?></button>
                        <?php ActiveForm::end(); ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>