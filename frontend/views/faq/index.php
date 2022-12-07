<?php

use yii\web\View;

/** @var View $this */

$user        = Yii::$app->user->identity;
$this->title = Yii::t('common', "Faq");
?>

<main id="main" role="main" class="mt-5">
    <div class="container">
        <div class="faq-how-works">
            <div class="how-works">
                <h2 class="how-works__title">
                    <?=Yii::t('common', "КАК ЭТО РАБОТАЕТ?")?>
                </h2>
                <div class="how-works__content">
                    <div class="how-works__item">
                        <div class="how-works__icon"><img class="how-works__icon-image" src="/images/faq/steam.svg" alt=""></div>
                        <div class="how-works__desc"><?=Yii::t('common', "Войдите через Steam и перейдите на страницу аккаунта")?></div>
                    </div>
                    <div class="how-works__separator">
                        <div class="how-works__separator-item"><img class="how-works__arrow-image" src="/images/faq/arrow-right.svg" alt=""></div>
                    </div>
                    <div class="how-works__item">
                        <div class="how-works__icon"><img class="how-works__icon-image" src="/images/faq/link.svg" alt=""></div>
                        <div class="how-works__desc"><?=Yii::t('common', "Введите Trade-URL")?></div>
                    </div>
                    <div class="how-works__separator">
                        <div class="how-works__separator-item"><img class="how-works__arrow-image" src="/images/faq/arrow-right.svg" alt=""></div>
                    </div>
                    <div class="how-works__item">
                        <div class="how-works__icon"><img class="how-works__icon-image"
                                                          src="/images/faq/pay.svg" alt=""></div>
                        <div class="how-works__desc"><?=Yii::t('common', "Пополните счёт на сайте любым удобным способом, даже ненужными скинами")?></div>
                    </div>
                    <div class="how-works__separator">
                        <div class="how-works__separator-item"><img class="how-works__arrow-image" src="/images/faq//arrow-right.svg" alt="">
                        </div>
                    </div>
                    <div class="how-works__item">
                        <div class="how-works__icon"><img class="how-works__icon-image" src="/images/faq/bag.svg" alt=""></div>
                        <div class="how-works__desc"><?=Yii::t('common', "Открывайте кейсы по самым выгодным ценам!")?></div>
                    </div>
                    <div class="how-works__separator">
                        <div class="how-works__separator-item"><img class="how-works__arrow-image" src="/images/faq/arrow-right.svg" alt="">
                        </div>
                    </div>
                    <div class="how-works__item">
                        <div class="how-works__icon"><img class="how-works__icon-image" src="/images/faq/random.svg" alt=""></div>
                        <div class="how-works__desc"><?=Yii::t('common', "Получите предмет от нашего бота или продайте сайту по цене Steam")?></div>
                    </div>
                </div>
            </div>
        </div>
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
                            <?=Yii::t('common', "Что делать, если после пополнения баланса деньги не поступили на счет?")?>
                        </div>
                    </div>
                    <div aria-hidden="true" class="questions__answer-wrapper rah-static--height-zero">
                        <div class="questions__answer">
                            <?=Yii::t('common', 'После оплаты необходимо подождать, если спустя час, деньги не поступили, пожалуйста, обратитесь в поддержку, указав реквизиты платежа.')?>
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
                            <?=Yii::t('common', 'Что делать, если предмет не пришел или вы не можете его забрать?')?>
                        </div>
                    </div>
                    <div aria-hidden="true" class="questions__answer-wrapper rah-static--height-zero">
                        <div class="questions__answer">
                            <?=Yii::t('common', 'Всё просто! Наиболее частые причины проблем с выводом предмета – бан трейда, скрытый инвентарь или отсутствие ссылки на обмен. В таком случае вам необходимо обратиться в нашу службу поддержки, и мы постараемся максимально быстро решить проблему.')?>
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
                            <?=Yii::t('common', 'Что делать, если к вам добавился в стиме под видом нашего сотрудника и предложил двойной баланс за ваши вещи?')?>
                        </div>
                    </div>
                    <div aria-hidden="true" class="questions__answer-wrapper rah-static--height-zero">
                        <div class="questions__answer">
                            <?=Yii::t('common', 'Остерегайтесь подобных сообщений! Мы никогда не добавимся в стим, тем более не станем просить вещи! Мошенники будут пытаться любым способом получить Ваши предметы! Будьте бдительны!')?>
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
                            <?=Yii::t('common', 'Можно ли перевести средства со счета Steam?')?>
                        </div>
                    </div>
                    <div aria-hidden="true" class="questions__answer-wrapper rah-static--height-zero">
                        <div class="questions__answer">
                            <?=Yii::t('common', 'Нет, к сожалению Steam не предоставляет никаких способов для вывода средств с их счета.')?>
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
                            <?=Yii::t('common', 'Как продать или вывести предмет с сайта?')?>
                        </div>
                    </div>
                    <div aria-hidden="true" class="questions__answer-wrapper rah-static--height-zero">
                        <div class="questions__answer">
                            <?=Yii::t('common', 'В профиле пользователя, на иконке скина, нужно выбрать действие, которое вы решили совершить. "Запросить с маркета" - система сформирует запрос на покупку, а затем отправит вам выбранный предмет. "Продать" - продажа предмета сайту, мгновенно получаете его стоимость на баланс.')?>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</main>
