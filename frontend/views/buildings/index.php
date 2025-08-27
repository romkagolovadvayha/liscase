<?php

use common\models\building\Building;
use yii\helpers\Html;
use yii\widgets\ListView;
use frontend\assets\BuildingsAsset;
use yii\widgets\ActiveForm;
use yii\widgets\Pjax;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var \frontend\models\building\BuildingSearch $searchModel */
/** @var array $servers */

$userBuildingsWait = Building::find()
                             ->andWhere(['user_id' => Yii::$app->user->id])
                             ->andWhere(['status' => Building::STATUS_WAIT])
                             ->exists();

$userLikes = \common\models\building\BuildingLike::find()
                                                 ->select('DISTINCT(building_id)')
                                                 ->andWhere(['user_id' => Yii::$app->user->id])
                                                 ->createCommand()
                                                 ->queryColumn();

BuildingsAsset::register($this);
$this->title = Yii::t('common', 'Постройки игроков');
?>

<div class="server_info_page">
    <div class="buildings">
        <?php if (!$userBuildingsWait): ?>
            <div class="buildings_buttons">
                <?= Html::a('<span class="button__text">' . Yii::t('common', 'Добавить свою постройку') . '</span>', ['create'], ['class' => 'button button-primary button-size__s h-36']) ?>
            </div>
        <?php endif; ?>

        <?php Pjax::begin([
                              'id' => 'buildings-pjax',
                              'timeout' => 0,
                              'enablePushState' => true,   // фильтры меняют URL
                              'scrollTo' => false,
                              // не перехватываем клики по пагинации (её грузим вручную для lazy load)
                              'linkSelector' => 'a[data-pjax=1]:not(#buildings-list .pagination a)',
                              'formSelector' => '#buildings-filter-form',
                          ]); ?>

        <div class="buildings_filters">
            <?php $form = ActiveForm::begin([
                                                'id' => 'buildings-filter-form',
                                                'method' => 'get',
                                                'action' => ['index'],
                                                'options' => ['data-pjax' => 1],
                                            ]); ?>

            <div class="buildings_filter">
                <?= $form->field($searchModel, 'name')
                         ->textInput([
                                         'placeholder' => Yii::t('common','Название постройки'),
                                         'onchange' => 'this.form.submit()',
                                     ])->label(false) ?>

                <?= $form->field($searchModel, 'server_tag')->widget(\kartik\select2\Select2::class, [
                    'data' => $servers, // tag => Название сервера
                    'options' => [
                        'placeholder' => 'Выберите сервер(а)...',
                        'multiple' => true,
                        'onchange' => 'this.form.submit()',
                    ],
                    'pluginOptions' => [
                        'allowClear' => true,
                        'closeOnSelect' => false,
                    ],
                    'pluginEvents' => [
                        "change" => "function(){ $('#buildings-filter-form').submit(); }",
                    ],
                    'showToggleAll' => true,
                ])->label(false); ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>

        <div class="buildings_content">
            <?php if ($userBuildingsWait): ?>
                <div class="buildings_content_moderation">
                    <?= Yii::t('common', 'Ваша постройка ожидает проверки, как только ее проверят она появится в списке ниже.') ?>
                </div>
            <?php endif; ?>

            <div class="buildings_content_list">
                <?= ListView::widget([
                                         'dataProvider' => $dataProvider,
                                         'itemView' => '_item',
                                         'viewParams' => ['userLikes' => $userLikes],
                                         'options' => ['id' => 'buildings-list', 'class' => 'listview-wrapper'],
                                         'summary' => false,
                                         'layout' => '<div class="buildings_content_list_items">{items}</div>{pager}',
                                         'pager' => [
                                             'options'        => ['class' => 'pagination'],
                                             'linkOptions'    => ['data-pjax' => 1], // на случай ручного клика
                                             'maxButtonCount' => 0,
                                             'nextPageLabel'  => Yii::t('common','Показать ещё'),
                                             'prevPageLabel'  => false,
                                         ],
                                     ]); ?>

                <!-- Сентинел для ленивой подгрузки -->
                <div id="lazy-trigger" aria-hidden="true"></div>
            </div>
        </div>

        <?php Pjax::end(); ?>
    </div>
</div>

<style>
    #buildings-list .pagination { display: none; }
    #lazy-trigger {
        height: 1px;        /* занимает место в потоке */
        margin-top: 1px;
        visibility: hidden; /* можно скрыть визуально, но НЕ display:none */
    }
    #lazy-trigger.loading {
        visibility: visible;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 64px;
    }
    #lazy-trigger.loading::after {
        content: '';
        display: block;
        margin-top: 12px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        border: 5px solid var(--background-teritiary); border-top-color: var(--primary-colors-main);
        animation: spin .8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
</style>

<?php
$js = <<<JS
(function(){
  var loading = false;
  var observer = null;

  function getNextUrl(){
    var \$a = $('#buildings-list .pagination a[rel="next"], #buildings-list .pagination li.next a');
    return \$a.length ? \$a.attr('href') : null;
  }

  function requestPjaxFragment(url){
    return $.ajax({
      url: url,
      type: 'GET',
      headers: { 'X-PJAX': 'true' },
      data: { _pjax: '#buildings-pjax' },
      cache: true
    });
  }

  function ensureTriggerAtBottom(){
    var \$wrap = $('#buildings-list .buildings_content_list_items');
    if (\$wrap.length) {
      $('#lazy-trigger').appendTo(\$wrap);
    }
  }

  function loadNext(){
    if (loading) return;
    var url = getNextUrl();
    if (!url) return;

    loading = true;
    $('#lazy-trigger').addClass('loading');

    requestPjaxFragment(url).done(function(html){
      var \$html = $('<div>').html(html);

      // новые карточки
      var \$newItemsWrap = \$html.find('#buildings-list .buildings_content_list_items');
      if (!\$newItemsWrap.length) {
        \$newItemsWrap = \$html.find('.buildings_content_list_items');
      }
      var \$newItems = \$newItemsWrap.children();

      // новая пагинация
      var \$newPager = \$html.find('#buildings-list .pagination');
      if (!\$newPager.length) {
        \$newPager = \$html.find('.pagination');
      }

      // аппендим в текущий список
      $('#buildings-list .buildings_content_list_items').append(\$newItems);

      // заменяем/убираем пагинацию
      var \$oldPager = $('#buildings-list .pagination');
      if (\$newPager.length) {
        \$oldPager.replaceWith(\$newPager);
      } else {
        \$oldPager.remove();
      }

      // если больше нет страниц — снимаем наблюдатель
      if (!getNextUrl() && observer) {
        observer.disconnect();
      }

      ensureTriggerAtBottom();
    }).always(function(){
      loading = false;
      $('#lazy-trigger').removeClass('loading');
    });
  }

  function bindObserver(){
    if (observer) { observer.disconnect(); observer = null; }
    var target = document.getElementById('lazy-trigger');
    if (!target) return;

    observer = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (entry.isIntersecting && !loading && getNextUrl()) {
          loadNext();
        }
      });
    }, { root: null, rootMargin: '600px 0px', threshold: 0 });

    observer.observe(target);
  }

  // старт
  ensureTriggerAtBottom();
  bindObserver();

  // после любого PJAX-обновления (фильтры и т.п.) — перевешиваем
  $(document).on('pjax:success', '#buildings-pjax', function(){
    // window.scrollTo({ top: 0, behavior: 'smooth' }); // если хочешь поднимать при смене фильтра
    ensureTriggerAtBottom();
    bindObserver();
  });
})();
JS;

$this->registerJs($js);
?>
