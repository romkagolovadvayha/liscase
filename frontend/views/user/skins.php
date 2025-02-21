<?php

use common\models\profit\Profit;
use yii\web\View;
use frontend\widgets\Alert;
use yii\web\NotFoundHttpException;
use common\models\user\UserPayoutSkins;
use common\models\skindrops\Skindrops;
use yii\helpers\ArrayHelper;
use yii\widgets\Pjax;

/** @var View $this */
/** @var $providerSkins */
/** @var $filterSkins */

$user = Yii::$app->user->identity;
$this->title = Yii::t('common', "Выдача скинов") . " - {$user->userProfile->name}";

$balance = $user->getSkinsBalance();

$skinCount = Skindrops::find()
                        ->andWhere(['steam_id' => $user->steam_id])
                        ->count();

$skins = Skindrops::find()
                                           ->select([
                                                        'name' => 'name',
                                                        'image' => 'image',
                                                        'amount' => 'real_price',
                                                        'created_at' => 'created_at'
                                           ])
                                           ->andWhere(['steam_id' => $user->steam_id])
                                           ->asArray()
                                           ->orderBy(['created_at' => SORT_DESC])
                                           ->all();

// Добавление текста в поле comment
foreach ($skins as &$skin) {
    $skin['status'] = Yii::t('common', "Зачислено");
}

$payouts = UserPayoutSkins::find()
     ->select([
        'statusKey' => 'status',
        'amount',
        'image',
        'image300',
        'name',
        'created_at'
     ])
    ->andWhere(['user_id' => $user->id])
    ->asArray()
    ->orderBy(['created_at' => SORT_DESC])
    ->all();

// Добавление текста в поле comment
foreach ($payouts as &$payout) {
    $payout['amount'] = $payout['amount'] * (-1);
    $payout['status'] = ArrayHelper::getValue(UserPayoutSkins::getStatusList(), $payout['statusKey']);
    if ($payout['statusKey'] == UserPayoutSkins::STATUS_REJECT) {
        $payout['amount'] = 0;
    }
}

$trades = [];
foreach ($payouts as $item) {
    if (count($trades) >= 4) {
        break;
    }
    $trades[] = $item;
}

$personalBalance = $user->getPersonalBalance();
$transfers = Profit::find()
                   ->select([
                        'amount',
                        'created_at'
                   ])
                   ->andWhere(['IN', 'type', [Profit::TYPE_TRANSFER_SKINS]])
                   ->andWhere(['user_balance_id' => $personalBalance->id])
                   ->asArray()
                   ->orderBy(['created_at' => SORT_DESC])
                   ->all();

// Добавление текста в поле comment
foreach ($transfers as &$transfer) {
    $transfer['amount'] = $transfer['amount'] * (-1);
    $transfer['status'] = Yii::t('common', "Перевод в магазин");
}

$items = ArrayHelper::merge($payouts, $skins);
$items = ArrayHelper::merge($items, $transfers);

$dataProvider = new \yii\data\ArrayDataProvider([
                                                    'allModels' => $items,
                                                    'totalCount' => count($items),
                                                    'pagination' => [
                                                        'pageSize' => 3,
                                                    ],
                                                    'sort'  => [
                                                        'attributes' => ['created_at', 'amount'],
                                                        'defaultOrder' => ['created_at' => SORT_DESC],
                                                    ],
                                                ]);

?>

<?= Alert::widget() ?>
<section class="tasks">
    <h2 class="tasks__title">
        <?=Yii::t('common', 'Получение скинов')?>
    </h2>
    <div class="page-stats__two-blocks">
        <section class="page-stats__block-without-hover w-50p">
            <div class="page-stats__categories">
                <div class="page-stats__category category">
                    <h5 class="category__count-and-img">
                        <span><div class="line_sum"><?=number_format($balance->balance, 0, '.', ' ')?> <span class="icons icons_16px icons_16px_coin_skins"></span></div></span>
                    </h5>
                    <?php if ($balance->balance > 0): ?>
                        <a
                                href="/user/transfer?type=skins"
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
                        <p class="category__title"><?=Yii::t('common', "Ваш баланс")?></p>
                    <?php endif; ?>
                </div>
                <div class="page-stats__category category">
                    <h5 class="category__count-and-img">
                        <span><?=number_format($skinCount, 0, '.', ' ')?></span>
                    </h5>
                    <p class="category__title"><?=Yii::t('common', "Вы выиграли скинов")?></p>
                </div>
            </div>
            <h4 class="flex items-center gap-x-12 mt-40 mb-24">
                <?=Yii::t('common', "Полученные скины")?>
                <span
                        class="icons icons_24px icons_24px_info icons_hover"
                        data-bs-toggle="tooltip"
                        data-bs-placement="right"
                        data-bs-title="<?=Yii::t('common', "Ниже будут отображаться последние полученные скины и их статус.")?>"></span>
            </h4>
            <?php if (empty($trades)): ?>
                <p><?=Yii::t('common', "Вы не покупали скины.")?></p>
            <?php else: ?>
            <div class="page-stats__awards">
                <?php foreach ($trades as $i => $item): ?>
                    <div class="award<?php if ($item['statusKey'] != UserPayoutSkins::STATUS_SUCCESS): ?> award_is-not-completed<?php endif; ?>">
                        <img src="<?=$item['image']?>" alt="<?=$item['name']?>" class="award__image">
                        <p class="p2"><?=$item['status']?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="mt-12">
                <a href="/skindrops" class="button button-secondary"><?=Yii::t('common', 'Условия и правила')?></a>
            </div>
        </section>
        <section class="page-stats__block-without-hover w-50p">
            <header class="flex items-center justify-space-between mb-24 transition-all">
                <h4 class="flex items-center gap-x-12">
                    <?=Yii::t('common', "Последние операции")?>
                    <span
                            class="icons icons_24px icons_24px_info icons_hover"
                            data-bs-toggle="tooltip"
                            data-bs-placement="right"
                            data-bs-title="<?=Yii::t('common', "Ваши последние операции, покупка скинов и выигрыши.")?>"></span>
                </h4>
            </header>
            <div>
                <?php Pjax::begin(['id'              => 'operations-list-pjax']); ?>
                <?= \kartik\grid\GridView::widget([
                                                      'dataProvider' => $dataProvider,
                                                      'layout'       => "{items}{pager}",
                                                      'columns'      => [
                                                          [
                                                              'attribute' => 'created_at',
                                                              'options'   => ['width' => '200'],
                                                              'label'     => Yii::t('common', "Дата операции"),
                                                              'format'    => 'raw',
                                                              'value'          => function ($model) {
                                                                  return \common\components\helpers\DateHelper::passed($model['created_at']);
                                                              },
                                                          ],
                                                          [
                                                              'attribute' => 'status',
                                                              'options'   => ['width' => '150'],
                                                              'label'     => Yii::t('common', "Статус"),
                                                              'format'    => 'raw',
                                                              'value'          => function ($model) {
                                                                  return $model['status'];
                                                              },
                                                          ],
                                                          [
                                                              'attribute' => 'amount',
                                                              'options'   => ['width' => '50'],
                                                              'label'     => Yii::t('common', "Сумма"),
                                                              'format'    => 'raw',
                                                              'value'          => function ($model) {
                                                                  if ($model['amount'] == 0) {
                                                                      return '';
                                                                  }
                                                                  $class = '';
                                                                  $amount = number_format($model['amount'], 0, '.', ' ');
                                                                  if ($model['amount'] < 0) {
                                                                      $class = 'line_sum_munus';
                                                                      $amount = '-' . $amount;
                                                                  } else {
                                                                      $amount = '+' . $amount;
                                                                  }
                                                                  return "<div class=\"line_sum {$class}\">{$amount} <span class=\"icons icons_16px icons_16px_coin_skins\"></span></div>";
                                                              },
                                                          ],
                                                      ],
                                                  ]);
                ?>
                <?php Pjax::end(); ?>
            </div>
        </section>
    </div>
    <section class="page-stats__block-without-hover mt-12" id="skins">
        <div>
            <?php Pjax::begin(['id' => 'skins-list-pjax']); ?>
            <?php echo $this->render('blocks/_skin_filter', ['model' => $filterSkins]); ?>
            <?= \yii\widgets\ListView::widget([
                                                  'id'           => 'skins-list-view',
                                                  'dataProvider' => $providerSkins,
                                                  'layout'       => "<div class=\"page-stats__categories mb-24\">{items}</div>{pager}",
                                                  'itemView'     => 'blocks/_skin_item',
                                                  'viewParams' => [
                                                      'balance' => $balance->balance,
                                                  ],
                                                  'options' => [
                                                      'class' => 'list-view',
                                                  ],
                                                  'itemOptions' => [
                                                      'tag' => false,
                                                  ],
                                              ]) ?>
            <?=\lo\widgets\magnific\MagnificPopup::widget(
                [
                    'target' => '.category__count-and-img',
                    'options' => [
                        'delegate'=> 'a',
                        'gallery' => [
                            'enabled' => true
                        ],
                    ],
                    'effect' => 'with-zoom' //for zoom effect
                ]
            );?>
            <?php Pjax::end(); ?>
        </div>
    </section>
</section>
<div class="loader" id="skin_loader"></div>