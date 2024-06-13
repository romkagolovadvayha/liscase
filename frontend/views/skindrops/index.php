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
if (!Yii::$app->user->isGuest && strpos(mb_strtolower($user->username), $prefix) !== false) {
    $usernameCompleted = true;
}
$allCompleted = $usernameCompleted && $tradeLinkCompleted && $authCompleted;
if (!Yii::$app->user->isGuest) {
    $updateUser = \common\components\oauth\Steam::updateUser($user->id);
    if (!empty($updateUser)) {
        $user = $updateUser;
    }
}

$skindropsSum = \common\models\skindrops\Skindrops::find()
    ->sum('price') + 200000;

$skindropsCount = \common\models\skindrops\Skindrops::find()
    ->count() + 3000;

$skindropsKD = $skindropsSum / $skindropsCount;

$skindropsSum = round($skindropsSum);
$skindropsKD = round($skindropsKD);


$result = [];
/** @var \common\models\skindrops\Skindrops[] $skindrops */
$skindrops = \common\models\skindrops\Skindrops::find()
                                               ->limit(20)
                                               ->cache(30)
                                               ->orderBy(['id' => SORT_DESC])
                                               ->all();
foreach ($skindrops as $item) {
    /** @var \common\models\user\Auth $userAuth */
    $userAuth = \common\models\user\Auth::find()
                                        ->andWhere(['source_id' => $item->steam_id])
                                        ->one();
    $userAvatar = null;
    $userName = null;
    if (!empty($userAuth)) {
        $userAvatar = $userAuth->user->userProfile->avatar;
        $userName = $userAuth->user->username;
    }
    $result[] = [
        'id' => $item->id,
        'image' => $item->image,
        'name' => $item->name,
        'bgImage' => "/images/skindrops/skindrops.png",
        'bgName' => "SkinDrops",
        'count' => $item->price . " RUB",
        'userAvatar' => $userAvatar,
        'userName' => $userName,
        'type' => 1,
        'created_at' => $item->created_at,
    ];
}

?>
<main id="main" role="main" class="mt-5">
    <div class="container">
        <div class="faq-how-works">
            <div class="how-works">
                <h1 class="how-works__title">
                    <?=Yii::t('common', "Получайте скины играя бесплатно!")?>
                </h1>
                <p class="how-works__p"><?=Yii::t('common', "Самый честный розыгрыш на наших серверах. Просто укажите ссылку и приписку к нику в Steam.")?></p>
                <div class="container-fluid how-works_drops_wrapper">
                    <div class="how-works_counters">
                        <div class="how-works_counters_item">
                            <div class="how-works_counters_item_label">
                                <span class="counter" data-num="<?=substr($skindropsCount, 0, -3)?>"><?=number_format(substr($skindropsCount, 0, -3), 0, '.', ' ')?></span>
                                <span class="counter" data-num="<?=substr($skindropsCount, -3)?>"><?=number_format(substr($skindropsCount, -3), 0, '.', ' ')?></span>
                            </div>
                            <div class="how-works_counters_item_text"><?=Yii::t('common', "Разыграно скинов")?></div>
                        </div>
                        <div class="how-works_counters_item">
                            <div class="how-works_counters_item_label">
                                <span class="counter" data-num="<?=substr($skindropsSum, 0, -3)?>"><?=number_format(substr($skindropsSum, 0, -3), 0, '.', ' ')?></span>
                                <span class="counter" data-num="<?=substr($skindropsSum, -3)?>"><?=number_format(substr($skindropsSum, -3), 0, '.', ' ')?></span>
                            </div>
                            <div class="how-works_counters_item_text"><?=Yii::t('common', "Общая стоимость")?></div>
                        </div>
                        <div class="how-works_counters_item">
                            <div class="how-works_counters_item_label">
                                <span class="counter" data-num="<?=$skindropsKD?>"><?=number_format($skindropsKD, 0, '.', ' ')?></span>
                            </div>
                            <div class="how-works_counters_item_text"><?=Yii::t('common', "Средняя стоимость")?></div>
                        </div>
                    </div>
                    <div class="last_drops">
                        <?php foreach ($result as $item): ?>
                            <?= $this->render('@frontend/views/widgets/_last_drops_item', [
                                'item' => $item,
                            ]); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
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
                        <div class="how-works__desc"><?=Yii::t('common', "Сделайте приписку к нику Prostoj")?></div>
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
