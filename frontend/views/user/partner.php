<?php

use common\models\statistics\Statistics;
use common\models\user\User;
use common\models\user\UserTree;
use common\models\invoice\Deposit;
use yii\helpers\Html;
use yii\web\View;
use frontend\widgets\Alert;
use yii\web\NotFoundHttpException;

/** @var View $this */

$user = Yii::$app->user->identity;
$this->title = Yii::t('common', "Партнерская программа") . " - {$user->userProfile->name}";

$serverPlay = UserTree::find()
    ->alias('ut')
    ->joinWith(['user.userProfile up'])
    ->andWhere(['ut.parent_user_id' => $user->id])
    ->andWhere(['NOT IN', 'ut.user_id', [$user->id]])
    ->andWhere(['up.parent_bonus' => 1])
    ->count();

$usersTree = UserTree::find()
    ->andWhere(['parent_user_id' => $user->id])
    ->andWhere(['NOT IN', 'user_id', [$user->id]])
    ->orderBy(['created_at' => SORT_DESC])
    ->limit(100)
    ->all();

$dataProvider = new \yii\data\ArrayDataProvider([
                                                    'allModels' => $usersTree,
                                                    'totalCount' => count($usersTree),
                                                    'pagination' => [
                                                        'pageSize' => 10,
                                                    ],
                                                ]);
$referralBalance = $user->getReferralBalance();
?>

<?= Alert::widget() ?>
<section class="tasks">
    <h2 class="tasks__title">
        <?=Yii::t('common', 'Реферальная система')?>
    </h2>
    <div class="page-stats__two-blocks">
        <section class="page-stats__block-without-hover w-50p">
            <header class="flex items-center justify-space-between mb-24 transition-all">
                <h4 class="flex items-center gap-x-12">
                    <?=Yii::t('common', "Ваша персональная ссылка")?>
                </h4>
            </header>
            <div class="relative mb-12 btn-clipboard"
                 style="max-width: 360px;"
                 data-bs-toggle="tooltip"
                 data-bs-placement="right"
                 data-bs-title="<?=Yii::t('common', 'Скопировать ссылку')?>"
                 data-clipboard-text="<?=$user->getPartnerLink()?>"
                 data-message="<?=Yii::t('common', 'Ссылка скопирован в буфер обмена!')?>">
                <input class="search search_pay" value="<?=$user->getPartnerLink()?>" readonly/>
                <span class="icons icons_16px fas fa-copy"></span>
            </div>
            <button type="button"
                    class="button-primary show-modal-link"
                    data-href="/user/promocode"
                    data-size="modal-sm"
                    data-content-overflow="unset"
                    data-top-image="<?=Yii::$app->settings->get('design_promoPopupImage')?>"
                    data-top-class="modal-backdrop-image_promo active"
                    data-toggl="modal"
                    data-target="modal-dialog"
                    data-title="<?=Yii::t('common', 'Персональный промокод')?>">
                <span class="button__text"><?=Yii::t('common', 'Мой промокод')?></span>
            </button>
            <a href="/referral" class="button button-secondary"><?=Yii::t('common', 'Условия и правила')?></a>
        </section>
        <section class="page-stats__block-without-hover w-50p">
            <div class="page-stats__categories">
                <div class="page-stats__category category">
                    <h5 class="category__count-and-img">
                        <span><?=$user->getReferralBonus()?>%</span>
                        <!--                                <img src="/uploads/drop/312_f1bbbd27a6a2da9e0f82b83ae1ac284a.png" alt="Серная руда" class="w-64 h-64 object-contain">-->
                    </h5>
                    <p class="category__title"><?=Yii::t('common', "Ваш процент")?></p>
                </div>
                <div class="page-stats__category category">
                    <h5 class="category__count-and-img">
                        <span><?=$user->userProfile->referral_click?></span>
                        <!--                                <img src="/uploads/drop/312_f1bbbd27a6a2da9e0f82b83ae1ac284a.png" alt="Серная руда" class="w-64 h-64 object-contain">-->
                    </h5>
                    <p class="category__title"><?=Yii::t('common', "Переходов по ссылке")?></p>
                </div>
                <div class="page-stats__category category">
                    <h5 class="category__count-and-img">
                        <span><?=count($usersTree)?></span>
                        <!--                                <img src="/uploads/drop/312_f1bbbd27a6a2da9e0f82b83ae1ac284a.png" alt="Серная руда" class="w-64 h-64 object-contain">-->
                    </h5>
                    <p class="category__title"><?=Yii::t('common', "Зарегистрированных")?></p>
                </div>
                <div class="page-stats__category category">
                    <h5 class="category__count-and-img">
                        <span><?=$serverPlay?></span>
                        <!--                                <img src="/uploads/drop/312_f1bbbd27a6a2da9e0f82b83ae1ac284a.png" alt="Серная руда" class="w-64 h-64 object-contain">-->
                    </h5>
                    <p class="category__title"><?=Yii::t('common', "Поигравших более часа")?></p>
                </div>
                <div class="page-stats__category category">
                    <h5 class="category__count-and-img">
                        <span><div class="line_sum"><?=number_format($referralBalance, 0, '.', ' ')?> <span class="referal_slots_item_title_currency"><i class="fas fa-ruble-sign"></i></span></div></span>
                        <!--                                <img src="/uploads/drop/312_f1bbbd27a6a2da9e0f82b83ae1ac284a.png" alt="Серная руда" class="w-64 h-64 object-contain">-->
                    </h5>
                    <?php if ($referralBalance > 0): ?>
                        <a
                                href="/user/transfer?type=referral"
                                class="show-modal-link z-1"
                                data-size="modal-sm"
                                data-content-overflow="unset"
                                data-top-image="<?=Yii::$app->settings->get('design_payPopupImage')?>"
                                data-top-class="modal-backdrop-image_pay active"
                                data-toggl="modal"
                                data-target="modal-dialog"
                                data-title="<?=Yii::t('common', 'Перевести в магазин')?>">
                            <?=Yii::t('common', 'Перевести в магазин')?>
                        </a>
                    <?php else: ?>
                        <p class="category__title"><?=Yii::t('common', "Доступно к выводу")?></p>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
    <section class="page-stats__block-without-hover mt-12">
        <header class="flex items-center justify-space-between mb-24 transition-all">
            <h4 class="flex items-center gap-x-12">
                <?=Yii::t('common', "Ваши приглашенные")?>
            </h4>
        </header>
        <div>
            <?= \kartik\grid\GridView::widget([
                                                  'dataProvider' => $dataProvider,
                                                  'layout'       => "{items} {pager}",
                                                  'columns'      => [
                                                      [
                                                          'attribute' => 'avatar',
                                                          'label'     => '',
                                                          'options'   => ['width' => '50'],
                                                          'format'    => 'raw',
                                                          'value'          => function (UserTree $model) {
                                                              if (empty($model->user)) {
                                                                  return null;
                                                              }
                                                              return Html::img($model->user->getAvatar(), ['class' => 'block w-32 h-32 min-w-32 min-h-32 rounded-6 object-cover']);
                                                          },
                                                      ],
                                                      [
                                                          'attribute' => 'username',
                                                          'label'     => Yii::t('common', "Ник"),
                                                          'format'    => 'raw',
                                                          'value'          => function (UserTree $model) {
                                                              if (empty($model->user)) {
                                                                  return null;
                                                              }
                                                              return $model->user->username;
                                                          },
                                                      ],
                                                      [
                                                          'attribute' => 'online',
                                                          'options'   => ['width' => '200'],
                                                          'label'     => Yii::t('common', "Более часа на сервере"),
                                                          'format'    => 'raw',
                                                          'value'          => function (UserTree $model) {
                                                              if (empty($model->user)) {
                                                                  return null;
                                                              }
                                                              if ($model->user->parent_skin_send || $model->user->userProfile->parent_bonus) {
                                                                  return Yii::t('common', "Да");
                                                              }
                                                              if ($model->user->hasHourInServer()) {
                                                                  return Yii::t('common', "Да");
                                                              }
                                                              return Yii::t('common', "Нет");
                                                          },
                                                      ],
                                                      [
                                                          'attribute' => 'created_at',
                                                          'options'   => ['width' => '200'],
                                                          'label'     => Yii::t('common', "Дата регистрации"),
                                                          'format'    => 'raw',
                                                          'value'          => function (UserTree $model) {
                                                              if (empty($model->user)) {
                                                                  return null;
                                                              }
                                                              return \common\components\helpers\DateHelper::passed($model->user->created_at);
                                                          },
                                                      ],
                                                      [
                                                          'attribute' => 'created_at',
                                                          'options'   => ['width' => '200'],
                                                          'label'     => Yii::t('common', "Награда"),
                                                          'format'    => 'raw',
                                                          'value'          => function (UserTree $model) {
                                                              if (empty($model->user)) {
                                                                  return null;
                                                              }
//                                                              User::parentBonus($model->user);
                                                              if ($model->user->parent_skin_send && $model->user->userProfile->parent_bonus) {
                                                                  $button = \yii\helpers\Html::button('<span class="button__text">' . Yii::t('common', "Получено") . '</span>', [
                                                                      'class' => 'button button-secondary button-size__s justify-content-center flex h-36 w-full',
                                                                      'style' => 'padding-top: 6px; padding-bottom: 6px;width: 100%;display: flex;align-items: center;',
                                                                      'disabled' => 'disabled'
                                                                  ]);
                                                                  return '<div class="flex align-items-center justify-content-center">' . $button . '</div>';
                                                              }
                                                              if ($model->user->hasHourInServer()) {
                                                                  $button = \yii\helpers\Html::a('<span class="button__text">' . Yii::t('common', "Получить награду") . '</span>', '/user/partner-bonus?id=' . $model->user_id, [
                                                                      'class' => 'button button-secondary justify-content-center flex button-size__s h-36 w-full',
                                                                      'style' => 'padding-top: 6px; padding-bottom: 6px;width: 100%;display: flex;align-items: center;'
                                                                  ]);
                                                                  return '<div class="flex align-items-center justify-content-center">' . $button . '</div>';
                                                              }
                                                              $button = \yii\helpers\Html::button('<span class="button__text">' . Yii::t('common', "Недоступно") . '</span>', [
                                                                  'class' => 'button button-secondary button-size__s justify-content-center flex h-36 w-full',
                                                                  'style' => 'padding-top: 6px; padding-bottom: 6px;width: 100%;display: flex;align-items: center;',
                                                                  'disabled' => 'disabled'
                                                              ]);
                                                              return '<div class="flex align-items-center justify-content-center">' . $button . '</div>';
                                                          },
                                                      ],
                                                  ],
                                              ]);
            ?>
        </div>
    </section>
</section>