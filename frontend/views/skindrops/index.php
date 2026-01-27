<?php

use yii\web\NotFoundHttpException;
use yii\web\View;
use frontend\forms\profile\ProfileForm;
use frontend\widgets\Alert;
use yii\bootstrap5\ActiveForm;

/** @var View $this */
/** @var ProfileForm $model */
/** @var \common\models\user\User $user */

if (!Yii::$app->settings->get('section_skindrops')) {
    throw new NotFoundHttpException(Yii::t('common', "Страница не найдена"));
}

$this->title = Yii::t('common', "Как получать скины в Rust бесплатно");
$this->params['meta_description'] = Yii::t('common', "Гайд по получению скинов в Rust бесплатно. Подробные ответы на вопросы, а так же информация о последних раздачах скинов на сервере.");
$this->params['meta_keywords'] = Yii::t('common', "раздача скинов, rust скины, бесплатные скины rust, розыгрыш скинов на сервере");

$authCompleted = !Yii::$app->user->isGuest;
$tradeLinkCompleted = false;
if (!Yii::$app->user->isGuest && !empty($user->userProfile->trade_link)) {
    $tradeLinkCompleted = true;
}
$usernameCompleted = false;
$prefix = Yii::$app->settings->get('skindrops_prefix');
if (!Yii::$app->user->isGuest && strpos(mb_strtolower($user->username), strtolower($prefix)) !== false) {
    $usernameCompleted = true;
}
$allCompleted = $usernameCompleted && $tradeLinkCompleted && $authCompleted;

$result = [];
/** @var \common\models\user\UserPayoutSkins[] $skindrops */
$skindrops = \common\models\user\UserPayoutSkins::find()
    ->alias('p')
    ->joinWith(['user'])
    ->andWhere(['p.status' => \common\models\user\UserPayoutSkins::STATUS_SUCCESS])
    ->limit(10)
    ->cache(60)
    ->orderBy(['p.id' => SORT_DESC])
    ->all();

foreach ($skindrops as $item) {
    $userAvatar = $item->user->getAvatar();
    $userName = $item->user->username;
    $result[] = [
        'id' => $item->id,
        'image' => $item->image,
        'name' => $item->name,
        'bgImage' => "/images/skindrops/skindrops.png",
        'bgName' => "SkinDrops",
        'count' => $item->amount . " RUB",
        'userAvatar' => $userAvatar,
        'userName' => $userName,
        'type' => 1,
        'created_at' => $item->created_at,
    ];
}

?>
<div class="skindrops-container">
    <div class="skindrops-header">
        <h1><?=Yii::t('common', "Бесплатные скины Rust каждый час — раздача от SkinDrops")?></h1>
        <p><?=Yii::t('common', "Получайте скины Steam совершенно бесплатно за игру на наших серверах Rust! Розыгрыш проводится каждые 60 минут среди всех активных игроков. Стоимость скинов от 20 до 120 рублей. Всё, что нужно — авторизоваться через Steam, указать Trade-URL и добавить префикс к нику.")?></p>
    </div>
    
    <div class="skindrops-recent">
        <h2><?=Yii::t('common', "Последние выигрыши")?></h2>
        <p class="skindrops-recent-subtitle"><?=Yii::t('common', "Игроки получают скины каждый час. Присоединяйтесь к розыгрышу прямо сейчас!")?></p>
        <div class="skindrops-recent-grid">
            <?php foreach ($result as $item): ?>
                <div class="skindrops-win-item">
                    <div class="skindrops-win-image">
                        <img src="<?=$item['image']?>" alt="<?=$item['name']?>" loading="lazy">
                    </div>
                    <div class="skindrops-win-info">
                        <div class="skindrops-win-name"><?=$item['name']?></div>
                        <div class="skindrops-win-price"><?=$item['count']?></div>
                    </div>
                    <div class="skindrops-win-user">
                        <img src="<?=$item['userAvatar']?>" alt="<?=$item['userName']?>" class="skindrops-win-avatar">
                        <span class="skindrops-win-username"><?=$item['userName']?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="skindrops-section">
        <h2><?=Yii::t('common', "Как участвовать в розыгрыше бесплатных скинов Rust?")?></h2>
        <p class="skindrops-subtitle"><?=Yii::t('common', "Раздача скинов Rust проводится автоматически каждые 60 минут среди всех игроков на сервере. Для участия выполните 4 простых шага:")?></p>
        
        <div class="skindrops-steps-grid">
            <div class="skindrops-card<?=$authCompleted ? ' active' : ''?>">
                <div class="skindrops-card-icon"><i class="fab fa-steam-symbol"></i></div>
                <div class="skindrops-card-title"><?=Yii::t('common', 'Войдите на сайт')?></div>
                <div class="skindrops-card-text">
                    <?=Yii::$app->user->isGuest ? \yii\helpers\Html::a(Yii::t('common', 'через Steam'), '/auth/oauth?authclient=steam') : Yii::t('common', 'через Steam')?>
                </div>
                <?php if ($authCompleted): ?>
                    <div class="skindrops-card-badge"><i class="fas fa-check"></i></div>
                <?php endif; ?>
            </div>
            
            <div class="skindrops-card<?=$tradeLinkCompleted ? ' active' : ''?>">
                <div class="skindrops-card-icon"><i class="fas fa-link"></i></div>
                <div class="skindrops-card-title"><?=Yii::t('common', "Trade-URL")?></div>
                <div class="skindrops-card-text"><?=Yii::t('common', "Введите свой Trade-URL")?></div>
                <?php if ($tradeLinkCompleted): ?>
                    <div class="skindrops-card-badge"><i class="fas fa-check"></i></div>
                <?php endif; ?>
            </div>
            
            <div class="skindrops-card<?=$usernameCompleted ? ' active' : ''?>">
                <div class="skindrops-card-icon"><i class="fas fa-spell-check"></i></div>
                <div class="skindrops-card-title"><?=Yii::t('common', "Приписка к нику")?></div>
                <div class="skindrops-card-text">"<?=Yii::$app->settings->get('skindrops_prefix')?>"</div>
                <?php if ($usernameCompleted): ?>
                    <div class="skindrops-card-badge"><i class="fas fa-check"></i></div>
                <?php endif; ?>
            </div>
            
            <div class="skindrops-card<?=$allCompleted ? ' active' : ''?>">
                <div class="skindrops-card-icon"><i class="fas fa-gamepad"></i></div>
                <div class="skindrops-card-title"><?=Yii::t('common', "Играйте!")?></div>
                <div class="skindrops-card-text"><?=Yii::t('common', "Выигрывайте скины")?></div>
                <?php if ($allCompleted): ?>
                    <div class="skindrops-card-badge"><i class="fas fa-check"></i></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="skindrops-section">
        <h2><?=Yii::t('common', "Процесс получения скинов в Steam")?></h2>
        <p class="skindrops-subtitle"><?=Yii::t('common', "После победы в розыгрыше средства автоматически зачисляются на ваш игровой баланс. Вы сами выбираете, какой скин вывести — можно получить предмет сразу или накопить на более дорогой. Трейд-офер придёт в Steam в течение 10 минут.")?></p>
        
        <div class="skindrops-process-grid">
            <div class="skindrops-process-item">
                <i class="fas fa-trophy"></i>
                <span><?=Yii::t('common', "Выигрыш")?></span>
            </div>
            <i class="fas fa-arrow-right skindrops-arrow"></i>
            <div class="skindrops-process-item">
                <i class="fas fa-wallet"></i>
                <span><?=Yii::t('common', "Баланс")?></span>
            </div>
            <i class="fas fa-arrow-right skindrops-arrow"></i>
            <div class="skindrops-process-item">
                <i class="fas fa-shopping-cart"></i>
                <span><?=\yii\helpers\Html::a(Yii::t('common', 'Вывод'), '/user/skins')?></span>
            </div>
            <i class="fas fa-arrow-right skindrops-arrow"></i>
            <div class="skindrops-process-item">
                <i class="fas fa-exchange-alt"></i>
                <span><?=Yii::t('common', "Трейд")?></span>
            </div>
        </div>
    </div>
    
    <div class="skindrops-seo-text">
        <h2><?=Yii::t('common', "Что такое SkinDrops и как работает система раздачи скинов?")?></h2>
        <p><?=Yii::t('common', "<strong>SkinDrops</strong> — это уникальная система автоматической раздачи скинов Rust на наших игровых серверах. Каждый час система случайным образом выбирает одного игрока из всех находящихся на сервере и награждает его скином стоимостью от 20 до 120 рублей.")?></p>
        
        <h3><?=Yii::t('common', "Преимущества системы SkinDrops:")?></h3>
        <ul class="skindrops-benefits">
            <li><?=Yii::t('common', "<strong>Полностью бесплатно</strong> — никаких донатов или скрытых платежей")?></li>
            <li><?=Yii::t('common', "<strong>Честная система</strong> — победитель выбирается случайно среди всех игроков")?></li>
            <li><?=Yii::t('common', "<strong>Реальные скины Steam</strong> — все предметы можно продать, обменять или использовать в игре")?></li>
            <li><?=Yii::t('common', "<strong>Регулярные розыгрыши</strong> — каждый час новая возможность выиграть")?></li>
            <li><?=Yii::t('common', "<strong>Гибкий вывод</strong> — копите баланс или выводите скины сразу")?></li>
        </ul>
        
        <h3><?=Yii::t('common', "Условия участия в раздаче скинов:")?></h3>
        <p><?=Yii::t('common', "Для участия в розыгрыше необходимо находиться на сервере в момент проведения раздачи (каждые 60 минут). Розыгрыш автоматически отменяется, если онлайн на сервере меньше 20 человек — это гарантирует, что скины получают реальные активные игроки, а не AFK-пользователи.")?></p>
        
        <p><?=Yii::t('common', "Важно: в вашем игровом нике должна быть указана приписка <strong>{PREFIX}</strong>. Например, если ваш ник «Player123», измените его на «Player123 {PREFIX}». Без этой приписки система не сможет вас идентифицировать и вы не будете участвовать в розыгрыше.", [
            'PREFIX' => Yii::$app->settings->get('skindrops_prefix')
        ])?></p>
    </div>
</div>
<?php if (!Yii::$app->user->isGuest): ?>
    <div class="mt-40">
        <div class="trade_link_form">
            <?= Alert::widget() ?>
            <?php $form = ActiveForm::begin(); ?>
            <label class="form-label" for="profileform-trade_link">
                <?=Yii::t('common', "Вставьте свою")?> <a href="https://steamcommunity.com/id/me/tradeoffers/privacy#trade_offer_access_url" target="_blank"><?=Yii::t('common', "ссылку")?></a> <?=Yii::t('common', "на обмен")?>
            </label>
            <?=$form->field($model, 'trade_link', [
                'inputOptions' => [
                    'class' => 'search search_pay'
                ],
                'template' => "{input}"
            ])
                    ->label(false)
                    ->textInput(['placeholder' => Yii::t('common', 'Вставьте трейд ссылку...')]); ?>
            <button type="submit" class="button button-primary">
                <span class="button__text"><?=Yii::t('common', 'Сохранить')?></span>
            </button>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
<?php endif; ?>
<div class="faq">
    <h2 class="faq__title">
        <?=Yii::t('common', "Часто задаваемые вопросы")?>
    </h2>
    <ul class="questions">
        <li class="questions__item questions__item--active">
            <div data-bs-toggle="collapse" data-bs-target="#question1" aria-expanded="false" aria-controls="question1" class="questions__question collapsed">
                <div class="questions__separator">
                    <span class="questions__separator-line"></span>
                    <span class="questions__separator-line"></span>
                </div>
                <div class="questions__question-title">
                    <?=Yii::t('common', 'Где найти ссылку на трейд?')?>
                </div>
            </div>
            <div id="question1" class="collapse questions__answer-wrapper rah-static--height-zero">
                <div class="questions__answer">
                    <?=Yii::t('common', 'Найти свою ссылку на трейд можно в <b>Steam Инвентарь</b> » <b>Предложения обмена</b> » <b>Кто может отправлять мне предложения обмена?</b>')?>
                </div>
            </div>
        </li>
        <li class="questions__item questions__item--active">
            <div data-bs-toggle="collapse" data-bs-target="#question2" aria-expanded="false" aria-controls="question2" class="questions__question collapsed">
                <div class="questions__separator">
                    <span class="questions__separator-line"></span>
                    <span class="questions__separator-line"></span>
                </div>
                <div class="questions__question-title">
                    <?=Yii::t('common', "Условия участия в розыгрыше")?>
                </div>
            </div>
            <div id="question2" class="collapse questions__answer-wrapper rah-static--height-zero">
                <div class="questions__answer">
                    <ol>
                        <li><?=Yii::t('common', 'Розыгрыш скинов на серверах происходит каждые <b>~60 минут</b> среди всех участников.')?></li>
                        <li><?=Yii::t('common', 'Розыгрыш скинов будет отменён, если на сервере нет минимум <b>20 онлайн</b>.')?></li>
                        <li><?=Yii::t('common', 'У вас должна быть <b>приписка в нике {PARAM_PREFIX}</b> (Пример: ept {PARAM_PREFIX})', [
                                'PARAM_PREFIX' => Yii::$app->settings->get('skindrops_prefix')
                            ])?></li>
                    </ol>
                </div>
            </div>
        </li>
        <li class="questions__item questions__item--active">
            <div data-bs-toggle="collapse" data-bs-target="#question3" aria-expanded="false" aria-controls="question3" class="questions__question collapsed">
                <div class="questions__separator">
                    <span class="questions__separator-line"></span>
                    <span class="questions__separator-line"></span>
                </div>
                <div class="questions__question-title">
                    <?=Yii::t('common', 'Что делать, если предмет не пришел?')?>
                </div>
            </div>
            <div id="question3" class="collapse questions__answer-wrapper rah-static--height-zero">
                <div class="questions__answer">
                    <?=Yii::t('common', 'Если скин не пришел, то средтсва вернутся к вам на счет, после чего вы можете повторно вывести скин.')?>
                </div>
            </div>
        </li>
        <li class="questions__item questions__item--active">
            <div data-bs-toggle="collapse" data-bs-target="#question4" aria-expanded="false" aria-controls="question4" class="questions__question collapsed">
                <div class="questions__separator">
                    <span class="questions__separator-line"></span>
                    <span class="questions__separator-line"></span>
                </div>
                <div class="questions__question-title">
                    <?=Yii::t('common', 'Как повысить шанс на выйгрыш?')?>
                </div>
            </div>
            <div id="question4" class="collapse questions__answer-wrapper rah-static--height-zero">
                <div class="questions__answer">
                    <?=Yii::t('common', 'Никак, раздача абсолютно рандомная. Цена полученного предмета всегда разная, <b>от 20 до 120 руб</b>')?>
                </div>
            </div>
        </li>
        <li class="questions__item questions__item--active">
            <div data-bs-toggle="collapse" data-bs-target="#question5" aria-expanded="false" aria-controls="question5" class="questions__question collapsed">
                <div class="questions__separator">
                    <span class="questions__separator-line"></span>
                    <span class="questions__separator-line"></span>
                </div>
                <div class="questions__question-title">
                    <?=Yii::t('common', 'Можно ли продать этот скин в Steam?')?>
                </div>
            </div>
            <div id="question5" class="collapse questions__answer-wrapper rah-static--height-zero">
                <div class="questions__answer">
                    <?=Yii::t('common', 'В отличии от скинов Twitch Drops, вы можете делать с этими скинами все что угодно! Продавайте, обменивайте копите!')?>
                </div>
            </div>
        </li>
    </ul>
</div>
