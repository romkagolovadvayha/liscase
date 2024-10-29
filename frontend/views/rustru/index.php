<?php

use frontend\forms\profile\RustruForm;
use yii\web\View;
use frontend\widgets\Alert;
use yii\bootstrap5\ActiveForm;

/** @var View $this */
/** @var RustruForm $model */
/** @var \common\models\user\User $user */

$this->title = Yii::t('common', "Активация бонусной программы RustRu");

?>
<main id="main" role="main" class="mt-5">
    <div class="container">
        <h1 class="how-works__title">
            <?=Yii::t('common', "Получайте скрап RustRu за игру на наших серверах!")?>
        </h1>
        <p class="how-works__p"><?=Yii::t('common', "Скрап в Discord {link_rustru} выдается за активность на наших серверах, подробнее читайте ниже в разделе вопрос/ответ.", [
                'link_rustru' => '<a href="https://discord.gg/rust-ru" target="_blank">RustRu</a>'
            ])?></p>
        <p class="how-works__p"><a href="https://discord.gg/rust-ru" target="_blank"><img src="<?=Yii::$app->params['cdnUrl']?>/images/rustru.png"/></a></p>

        <div class="trade_link_form">
            <?php if (Yii::$app->user->isGuest || !$user->rustru_activated): ?>
                <?= Alert::widget() ?>
                <?php $form = ActiveForm::begin(); ?>
                <button type="submit" class="btn"><?=Yii::t('common', 'Участвовать в раздаче скрапа')?></button>
                <?php ActiveForm::end(); ?>
            <?php else: ?>
                <p class="how-works__title" style="color: #a8dc4d;">
                    <?=Yii::t('common', "Вы участник бонусной системы RustRu!")?>
                </p>
            <?php endif; ?>
        </div>
        <div class="faq">
            <ul class="questions">
                <li class="questions__item questions__item--active">
                    <div class="questions__question">
                        <div class="questions__separator">
                            <span class="questions__separator-line"></span>
                            <span class="questions__separator-line"></span>
                        </div>
                        <div class="questions__question-title">
                            <?=Yii::t('common', 'Как получать срап в RustRu?')?>
                        </div>
                    </div>
                    <div aria-hidden="true" class="questions__answer-wrapper rah-static--height-zero">
                        <div class="questions__answer">
                            <?=Yii::t('common', 'Для того, чтобы получать скрап вам нужно: <b>Авторизоваться на нашем сайте</b> » <b>Нажать кнопку выше "Участвовать в раздаче скрапа"</b> » <b>Играть на любом нашем сервере</b>')?>
                            <br/>
                            <?=Yii::t('common', 'Скрап будет автоматически начисляться вам в Discord RustRu в течении 24 часов')?>
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
                            <?=Yii::t('common', "Сколько срапа я получу?")?>
                        </div>
                    </div>
                    <div aria-hidden="true" class="questions__answer-wrapper rah-static--height-zero">
                        <div class="questions__answer">
                            <?=Yii::t('common', 'Точное количество срапа высчитавается исходя из вашей активности в течении дня.')?>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</main>
