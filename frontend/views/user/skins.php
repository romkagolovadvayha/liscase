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
/** @var \frontend\forms\user\SkinsForm $form */

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

// Статистика выигранных скинов
$totalWonAmount = 0;
$averageWinAmount = 0;
if (!empty($skins)) {
    $totalWonAmount = array_sum(array_column($skins, 'amount'));
    $averageWinAmount = $totalWonAmount / count($skins);
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
    // Убеждаемся, что statusKey существует
    if (!isset($item['statusKey'])) {
        $item['statusKey'] = null;
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

// Сортируем по дате создания (новые первыми)
usort($items, function($a, $b) {
    $dateA = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
    $dateB = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
    return $dateB - $dateA;
});

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
<section class="skins-page">
    <div class="skins-page__header">
        <h1 class="skins-page__title"><?=Yii::t('common', 'Получение скинов')?></h1>
        <p class="skins-page__subtitle"><?=Yii::t('common', 'Выводите выигранные скины или покупайте новые за баланс')?></p>
    </div>
    
    <!-- Главный блок с балансом и статистикой -->
    <section class="skins-page__balance-section">
        <div class="skins-page__balance-card">
            <div class="skins-page__balance-header">
                <div class="skins-page__balance-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="skins-page__balance-info">
                    <h3 class="skins-page__balance-label"><?=Yii::t('common', 'Ваш баланс')?></h3>
                    <div class="skins-page__balance-amount">
                        <span class="skins-page__balance-value"><?=number_format($balance->balance, 0, '.', ' ')?></span>
                        <span class="icons icons_24px icons_24px_coin_skins"></span>
                    </div>
                </div>
            </div>
            <div class="skins-page__balance-actions">
                    <?php if ($balance->balance > 0): ?>
                        <a
                                href="/user/transfer?type=skins"
                        class="skins-page__balance-action show-modal-link"
                                data-size="modal-sm"
                                data-content-overflow="unset"
                                data-top-image="<?=Yii::$app->settings->get('design_payPopupImage')?>"
                                data-top-class="modal-backdrop-image_pay active"
                                data-toggl="modal"
                                data-target="modal-dialog"
                                data-title="<?=Yii::t('common', 'Перевести в магазин')?>">
                        <i class="fas fa-exchange-alt"></i>
                        <span><?=Yii::t('common', 'Перевести в магазин')?></span>
                        </a>
                    <?php endif; ?>
                <a
                    href="/user/skins-operations"
                    class="skins-page__balance-action skins-page__balance-action--secondary show-modal-link"
                    data-size="modal-lg"
                    data-content-overflow="unset"
                    data-toggl="modal"
                    data-target="modal-dialog"
                    data-title="<?=Yii::t('common', 'История операций')?>">
                    <i class="fas fa-history"></i>
                    <span><?=Yii::t('common', 'История операций')?></span>
                </a>
            </div>
        </div>
        
        <div class="skins-page__stats-grid">
            <div class="skins-page__stat-card">
                <div class="skins-page__stat-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="skins-page__stat-content">
                    <div class="skins-page__stat-value"><?=number_format($skinCount, 0, '.', ' ')?></div>
                    <div class="skins-page__stat-label"><?=Yii::t('common', 'Выиграно скинов')?></div>
                </div>
            </div>
            
            <?php if ($skinCount > 0): ?>
                <div class="skins-page__stat-card">
                    <div class="skins-page__stat-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="skins-page__stat-content">
                        <div class="skins-page__stat-value"><?=number_format($totalWonAmount, 0, '.', ' ')?> <span class="icons icons_16px icons_16px_coin_skins"></span></div>
                        <div class="skins-page__stat-label"><?=Yii::t('common', 'Всего выиграно')?></div>
                    </div>
                </div>
                
                <div class="skins-page__stat-card">
                    <div class="skins-page__stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="skins-page__stat-content">
                        <div class="skins-page__stat-value"><?=number_format($averageWinAmount, 0, '.', ' ')?> <span class="icons icons_16px icons_16px_coin_skins"></span></div>
                        <div class="skins-page__stat-label"><?=Yii::t('common', 'Средний выигрыш')?></div>
                    </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Информационный блок -->
    <section class="skins-page__info-section">
        <div class="skins-page__info-card">
            <div class="skins-page__info-icon">
                <i class="fas fa-info-circle"></i>
            </div>
            <div class="skins-page__info-content">
                <h4 class="skins-page__info-title"><?=Yii::t('common', 'Как получить баланс?')?></h4>
                <p class="skins-page__info-text">
                    <?=Yii::t('common', 'Раздача скинов проводится автоматически каждые 60 минут среди всех игроков на сервере. Случайный игрок получает скин стоимостью от 20 до 120 рублей. Средства автоматически зачисляются на ваш баланс.')?>
                </p>
                <a href="/skindrops" class="skins-page__info-link">
                    <?=Yii::t('common', 'Подробнее о правилах')?>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            </div>
        </section>
    
    <!-- Секция со списком скинов для покупки -->
    <section class="skins-page__shop-section" id="skins">
        <div class="skins-page__shop-header">
            <h2 class="skins-page__shop-title">
                <i class="fas fa-shopping-bag"></i>
                <?=Yii::t('common', 'Каталог скинов')?>
            </h2>
        </div>
        <div class="skins-page__shop-content">
            <?php Pjax::begin([
                'id' => 'skins-list-pjax',
                'enablePushState' => true,
                'scrollTo' => false,
                'linkSelector' => 'a[data-pjax=1]:not(#skins-list-view .pagination a)',
                'formSelector' => '#skins-filter-form',
            ]); ?>
            <div class="skins-page__filter">
                <?php 
                $type = Yii::$app->request->get('type', 'rust');
                echo $this->render('blocks/_skin_filter', [
                    'model' => $filterSkins,
                    'type' => $type
                                                  ]);
                ?>
            </div>
            <?= \yii\widgets\ListView::widget([
                                                  'id'           => 'skins-list-view',
                                                  'dataProvider' => $providerSkins,
                                                  'layout'       => "<div class=\"skins-page__items-grid\">{items}</div>{pager}",
                                                  'itemView'     => 'blocks/_skin_item',
                                                  'viewParams' => [
                                                      'balance' => $balance->balance,
                                                      'type' => Yii::$app->request->get('type', 'rust'),
                                                  ],
                                                  'options' => [
                                                      'class' => 'list-view',
                                                  ],
                                                  'itemOptions' => [
                                                      'tag' => false,
                                                  ],
                                                  'pager' => [
                                                      'options'        => ['class' => 'pagination'],
                                                      'linkOptions'    => ['data-pjax' => 1],
                                                      'maxButtonCount' => 0,
                                                      'nextPageLabel'  => Yii::t('common','Показать ещё'),
                                                      'prevPageLabel'  => false,
                                                  ],
                                              ]) ?>
            
            <!-- Сентинел для ленивой подгрузки -->
            <div id="skins-lazy-trigger" aria-hidden="true"></div>
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

<style>
    #skins-list-view .pagination { display: none; }
    #skins-lazy-trigger {
        height: 1px;
        margin-top: 1px;
        visibility: hidden;
    }
    #skins-lazy-trigger.loading {
        visibility: visible;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 64px;
    }
    #skins-lazy-trigger.loading::after {
        content: '';
        display: block;
        margin-top: 12px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        border: 5px solid var(--background-teritiary);
        border-top-color: var(--primary-colors-main);
        animation: spin .8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
</style>

<?php
$js = <<<JS
(function(){
  var loading = false;
  var observer = null;
  var loadedPages = new Set();

  function getNextUrl(){
    var \$a = $('#skins-list-view .pagination a[rel="next"], #skins-list-view .pagination li.next a');
    return \$a.length ? \$a.attr('href') : null;
  }

  function requestPjaxFragment(url){
    return $.ajax({
      url: url,
      type: 'GET',
      headers: { 'X-PJAX': 'true' },
      data: { _pjax: '#skins-list-pjax' },
      cache: false
    });
  }

  function ensureTriggerAtBottom(){
    var \$wrap = $('#skins-list-view .skins-page__items-grid');
    if (\$wrap.length) {
      $('#skins-lazy-trigger').appendTo(\$wrap);
    }
  }

  function loadNext(){
    if (loading) return;
    var url = getNextUrl();
    if (!url) return;
    
    if (loadedPages.has(url)) {
      console.log('Page already loaded:', url);
      return;
    }

    loading = true;
    loadedPages.add(url);
    $('#skins-lazy-trigger').addClass('loading');
    
    if (observer) {
      observer.disconnect();
    }

    requestPjaxFragment(url).done(function(html){
      var \$html = $('<div>').html(html);

      var \$newItemsWrap = \$html.find('#skins-list-view .skins-page__items-grid');
      if (!\$newItemsWrap.length) {
        \$newItemsWrap = \$html.find('.skins-page__items-grid');
      }
      var \$newItems = \$newItemsWrap.children();

      var existingIds = new Set();
      $('#skins-list-view .skins-page__items-grid .skins_item').each(function(){
        var itemId = $(this).attr('data-id');
        if (itemId) {
          existingIds.add(itemId);
        }
      });

      var \$uniqueItems = \$newItems.filter(function(){
        var itemId = $(this).attr('data-id');
        return !itemId || !existingIds.has(itemId);
      });

      var \$newPager = \$html.find('#skins-list-view .pagination');
      if (!\$newPager.length) {
        \$newPager = \$html.find('.pagination');
      }

      if (\$uniqueItems.length > 0) {
        $('#skins-list-view .skins-page__items-grid').append(\$uniqueItems);
      }

      var \$oldPager = $('#skins-list-view .pagination');
      if (\$newPager.length) {
        \$oldPager.replaceWith(\$newPager);
      } else {
        \$oldPager.remove();
      }

      ensureTriggerAtBottom();
      
      if (getNextUrl()) {
        bindObserver();
      }
    }).fail(function(){
      loadedPages.delete(url);
    }).always(function(){
      loading = false;
      $('#skins-lazy-trigger').removeClass('loading');
    });
  }

  function bindObserver(){
    if (observer) { 
      observer.disconnect(); 
      observer = null; 
    }
    
    var target = document.getElementById('skins-lazy-trigger');
    if (!target) return;

    observer = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (entry.isIntersecting && !loading && getNextUrl()) {
          loadNext();
        }
      });
    }, { 
      root: null, 
      rootMargin: '400px 0px',
      threshold: 0 
    });

    observer.observe(target);
  }

  ensureTriggerAtBottom();
  bindObserver();

  $(document).on('pjax:success', '#skins-list-pjax', function(){
    loadedPages.clear();
    ensureTriggerAtBottom();
    bindObserver();
  });
  
  // Очищаем загруженные страницы при изменении фильтров
  $(document).on('submit', '#skins-filter-form', function(){
    loadedPages.clear();
  });
})();
JS;

$this->registerJs($js);
?>