<?php

use yii\web\View;
use frontend\forms\profile\ProfileForm;
use frontend\widgets\Alert;
use yii\bootstrap5\ActiveForm;

/** @var View $this */
/** @var ProfileForm $model */
/** @var \common\models\user\User $user */

$this->title = Yii::t('common', "Как получать скины в Rust бесплатно");

$authCompleted = !Yii::$app->user->isGuest;
$tradeLinkCompleted = false;
if (!Yii::$app->user->isGuest && !empty($user->userProfile->trade_link)) {
    $tradeLinkCompleted = true;
}
$usernameCompleted = false;
$prefix = "prostoj";
if (!Yii::$app->user->isGuest
    && (substr(mb_strtolower($user->username), 0, strlen($prefix)) === $prefix || substr(mb_strtolower($user->username), strlen($prefix) * -1) === $prefix) ) {
    $usernameCompleted = true;
}
$allCompleted = $usernameCompleted && $tradeLinkCompleted && $authCompleted;
if (!Yii::$app->user->isGuest) {
    $updateUser = \common\components\oauth\Steam::updateUser($user->id);
    if (!empty($updateUser)) {
        $user = $updateUser;
    }
}

?>
<main id="main" role="main" class="mt-5">
    <div class="container">
        <div class="faq-how-works">
            <div class="how-works">
                <h2 class="how-works__title">
                    <?=Yii::t('common', "КАК ЭТО РАБОТАЕТ?")?>
                </h2>
                <p class="how-works__p"><?=Yii::t('common', "Розыгрыш скинов на серверах проходит каждые ~60 минут среди всех участников.")?></p>
                <div class="how-works__content">
                    <div class="how-works__item<?=$authCompleted ? '' : ' danger'?>">
                        <div class="how-works__icon"><i class="fab fa-steam-symbol"></i></div>
                        <div class="how-works__desc"><?=Yii::t('common', 'Войдите на сайт')?> <?=Yii::$app->user->isGuest ? \yii\helpers\Html::a(Yii::t('common', 'через Steam'), '/auth/oauth?authclient=steam') : Yii::t('common', 'Войдите на сайт через Steam')?></div>
                    </div>
                    <div class="how-works__separator<?=$authCompleted ? '' : ' danger'?>">
                        <div class="how-works__separator-item"><i class="fas fa-angle-right"></i></div>
                    </div>
                    <div class="how-works__item<?=$tradeLinkCompleted ? '' : ' danger'?>">
                        <div class="how-works__icon"><i class="fas fa-link"></i></div>
                        <div class="how-works__desc"><?=Yii::t('common', "Введите Trade-URL")?></div>
                    </div>
                    <div class="how-works__separator<?=$tradeLinkCompleted ? '' : ' danger'?>">
                        <div class="how-works__separator-item"><i class="fas fa-angle-right"></i></div>
                    </div>
                    <div class="how-works__item<?=$usernameCompleted ? '' : ' danger'?>">
                        <div class="how-works__icon"><i class="fas fa-spell-check"></i></div>
                        <div class="how-works__desc"><?=Yii::t('common', "Сделайте приписку к нику в начале или в конце Prostoj")?></div>
                    </div>
                    <div class="how-works__separator<?=$usernameCompleted ? '' : ' danger'?>">
                        <div class="how-works__separator-item"><i class="fas fa-angle-right"></i></div>
                    </div>
                    <div class="how-works__item<?=$allCompleted ? '' : ' danger'?>">
                        <div class="how-works__icon"><i class="fas fa-gamepad"></i></div>
                        <div class="how-works__desc"><?=Yii::t('common', "Играйте на сервере и получайте скины!")?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php if (!Yii::$app->user->isGuest): ?>
        <div class="trade_link_form">
            <?= Alert::widget() ?>
            <?php $form = ActiveForm::begin(); ?>
            <label class="form-label" for="profileform-trade_link">
                <?=Yii::t('common', "Вставьте свою")?> <a href="https://steamcommunity.com/id/me/tradeoffers/privacy#trade_offer_access_url" target="_blank"><?=Yii::t('common', "ссылку")?></a> <?=Yii::t('common', "на обмен")?>
            </label>
            <?= $form->field($model, 'trade_link')->label(false)->textInput(); ?>
            <button type="submit" class="btn"><?=Yii::t('common', 'Сохранить')?></button>
            <?php ActiveForm::end(); ?>
        </div>
        <?php endif; ?>
        <div class="faq">
            <h1 class="faq__title">
                <?=Yii::t('common', "Часто задаваемые вопросы")?>
            </h1>
            <ul class="questions">
                <li class="questions__item questions__item--active">
                    <div class="questions__question">
                        <div class="questions__separator">
                            <span class="questions__separator-line"></span>
                            <span class="questions__separator-line"></span>
                        </div>
                        <div class="questions__question-title">
                            <?=Yii::t('common', 'Где найти ссылку на трейд?')?>
                        </div>
                    </div>
                    <div aria-hidden="true" class="questions__answer-wrapper rah-static--height-zero">
                        <div class="questions__answer">
                            <?=Yii::t('common', 'Найти свою ссылку на трейд можно в Steam Инвентарь » Предложения обмена » Кто может отправлять мне предложения обмена?')?>
                        </div>
                    </div>
                </li>
                <li class="questions__item questions__item--active">
                    <div class="questions__question">
                        <div class="questions__separator">
                            <span class="questions__separator-line"></span>
                            <span class="questions__separator-line"></span>
                        </div>
                        <div class="questions__question-title">
                            <?=Yii::t('common', "Условия участия в розыгрыше")?>
                        </div>
                    </div>
                    <div aria-hidden="true" class="questions__answer-wrapper rah-static--height-zero">
                        <div class="questions__answer">
                            -Розыгрыш скинов на серверах происходит каждые ~60 минут среди всех участников.<br/>
                            -Розыгрыш скинов будет отменён, если на сервере нет минимум 10 онлайна.<br/>
                            -У вас должна быть приписка в нике Prostoj (Пример: ept Prostoj)<br/>
                            -У победителя будет 5 минут, что бы принять трейд от бота в Steam. (иногда задержка выдачи приза до 20 мин)<br/>
                            -Если настройки приватности для получения трейда были закрыты или вы не успели принять трейд в течении 5 минут, то выдача скина повторно не проводится.
                        </div>
                    </div>
                </li>
                <li class="questions__item questions__item--active">
                    <div class="questions__question">
                        <div class="questions__separator">
                            <span class="questions__separator-line"></span>
                            <span class="questions__separator-line"></span>
                        </div>
                        <div class="questions__question-title">
                            <?=Yii::t('common', 'Что делать, если предмет не пришел?')?>
                        </div>
                    </div>
                    <div aria-hidden="true" class="questions__answer-wrapper rah-static--height-zero">
                        <div class="questions__answer">
                            <?=Yii::t('common', 'Если скин не пришел в течении 15 минут, то напишите администратору в Discord, в течении 20 минут после выйгрыша.')?>
                        </div>
                    </div>
                </li>
                <li class="questions__item questions__item--active">
                    <div class="questions__question">
                        <div class="questions__separator">
                            <span class="questions__separator-line"></span>
                            <span class="questions__separator-line"></span>
                        </div>
                        <div class="questions__question-title">
                            <?=Yii::t('common', 'Как повысить шанс на выйгрыш?')?>
                        </div>
                    </div>
                    <div aria-hidden="true" class="questions__answer-wrapper rah-static--height-zero">
                        <div class="questions__answer">
                            <?=Yii::t('common', 'Никак, раздача абсолютно рандомная. Цена полученного предмета всегда разная, от 10 до 120 руб')?>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</main>
