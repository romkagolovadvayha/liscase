<?php
use yii\helpers\Html;
use yii\widgets\ListView;
use yii\widgets\ActiveForm;
use yii\widgets\Pjax;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var frontend\models\serverskin\ServerSkinSearch $searchModel */
/** @var array $categories */

$this->title = Yii::t('common', 'Скины из мастерской для SkinBox');
\frontend\assets\CustomSkinAsset::register($this);
?>

<?= \frontend\widgets\Alert::widget() ?>

<div class="server_info_page">
    <div class="custom-skins">
        <div class="custom-skins_buttons">
            <?= Html::a('<span class="button__text">' . Yii::t('common', 'Добавить скин') . '</span>', ['create'], [
                'class' => 'button button-primary button-size__s h-36 show-modal-link',
                'data-title' => Yii::t('common', 'Добавить скин'),
                'data-size' => 'modal-lg',
                'data-toggl' => 'modal',
                'data-target' => 'modal-dialog'
            ]) ?>
        </div>

        <?php Pjax::begin([
                              'id' => 'skins-pjax',
                              'timeout' => 0,
                              'enablePushState' => true,  // фильтры меняют URL
                              'scrollTo' => false,
                              'linkSelector' => 'a[data-pjax=1]:not(#skins-list .pagination a)',
                              'formSelector' => '#skins-filter-form',
                          ]); ?>

        <!-- Фильтры -->
        <div class="custom-skins_filters">
            <?php $form = ActiveForm::begin([
                                                'id' => 'skins-filter-form',
                                                'method' => 'get',
                                                'action' => ['index'],
                                                'options' => ['data-pjax' => 1],
                                            ]); ?>

            <div class="custom-skins_filter">
                <?= $form->field($searchModel, 'name')
                         ->textInput([
                                         'placeholder' => Yii::t('common','Название скина'),
                                         'onchange' => 'this.form.submit()',
                                     ])->label(false) ?>

                <?= $form->field($searchModel, 'server_skin_category_id')->widget(\kartik\select2\Select2::class, [
                    'data' => $categories, // id => name
                    'options' => [
                        'placeholder' => 'Выберите категорию...',
                        'multiple' => true,
                        'onchange' => 'this.form.submit()',
                    ],
                    'pluginOptions' => [
                        'allowClear' => true,
                        'closeOnSelect' => false,
                    ],
                    'pluginEvents' => [
                        // Надёжнее, чем 'onchange' на теге <select>, и работает с PJAX
                        "change" => "function(){ $('#skins-filter-form').submit(); }",
                    ],
                    'showToggleAll' => true,
                ])->label(false); ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>

        <?php
        // информация для пользователя про модерацию (как у тебя было)
        $userBuildingsWait = \common\models\serverskin\ServerSkin::find()
                                                                 ->andWhere(['user_id' => Yii::$app->user->id])
                                                                 ->andWhere(['status' => \common\models\serverskin\ServerSkin::STATUS_WAIT])
                                                                 ->exists();

        if ($userBuildingsWait): ?>
            <div class="custom-skins_content_moderation">
                <?= Yii::t('common', 'Ваш скин проверки, как только его проверят он появится в списке ниже и на серверах.') ?>
            </div>
        <?php endif; ?>

        <?php
        $userLikes = \common\models\serverskin\ServerSkinLike::find()
                                                             ->select('DISTINCT(server_skin_id)')
                                                             ->andWhere(['user_id' => Yii::$app->user->id])
                                                             ->createCommand()
                                                             ->queryColumn();
        ?>

        <div class="custom-skins_content">
            <div class="custom-skins_content_list" id="skins-list">
                <?= ListView::widget([
                                         'dataProvider' => $dataProvider,
                                         'itemView' => '_item',
                                         'viewParams' => ['userLikes' => $userLikes],
                                         'options' => ['id' => 'skins-list', 'class' => 'listview-wrapper'],
                                         'summary' => false,
                                         'layout' => '<div class="custom-skins_content_list_items">{items}</div>{pager}',
                                         'pager' => [
                                             'options'        => ['class' => 'pagination'],
                                             'linkOptions'    => ['data-pjax' => 1], // PJAX для ручного клика, если понадобится
                                             'maxButtonCount' => 0,
                                             'nextPageLabel'  => Yii::t('common','Показать ещё'),
                                             'prevPageLabel'  => false,
                                         ],
                                     ]); ?>
            </div>

            <!-- Сентинел для триггера автоподгрузки -->
            <div id="lazy-trigger" aria-hidden="true"></div>
        </div>

        <?php Pjax::end(); ?>
    </div>
</div>

<?php
$js = <<<JS
(function(){
  var loading = false;
  var observer = null;

  function getNextUrl(){
    var \$a = $('#skins-list .pagination a[rel="next"], #skins-list .pagination li.next a');
    return \$a.length ? \$a.attr('href') : null;
  }

  function requestPjaxFragment(url){
    // Просим у сервера тот же HTML, что PJAX бы подменил
    return $.ajax({
      url: url,
      type: 'GET',
      headers: { 'X-PJAX': 'true' },
      data: { _pjax: '#skins-pjax' },
      cache: true
    });
  }

  function ensureTriggerAtBottom(){
    var \$wrap = $('#skins-list .custom-skins_content_list_items');
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

      // новые items
      var \$newItemsWrap = \$html.find('#skins-list .custom-skins_content_list_items');
      if (!\$newItemsWrap.length) {
        \$newItemsWrap = \$html.find('.custom-skins_content_list_items');
      }
      var \$newItems = \$newItemsWrap.children();

      // новая пагинация
      var \$newPager = \$html.find('#skins-list .pagination');
      if (!\$newPager.length) {
        \$newPager = \$html.find('.pagination');
      }

      // аппендим карточки
      $('#skins-list .custom-skins_content_list_items').append(\$newItems);

      // заменяем пагинацию (или убираем, если закончилась)
      var \$oldPager = $('#skins-list .pagination');
      if (\$newPager.length) {
        \$oldPager.replaceWith(\$newPager);
      } else {
        \$oldPager.remove();
      }

      // если страниц больше нет — отписываемся
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

  // первоначальная инициализация
  ensureTriggerAtBottom();
  bindObserver();

  // после любого PJAX-обновления (фильтры и т.п.) — перевешиваем
  $(document).on('pjax:success', '#skins-pjax', function(){
    // при смене фильтров можешь вернуть скролл наверх:
    // window.scrollTo({ top: 0, behavior: 'smooth' });
    ensureTriggerAtBottom();
    bindObserver();
  });

  // отладка (если "ничего не происходит", раскомментируй):
  // console.log('lazy loader bound');
})();
JS;

$this->registerJs($js);
?>

<style>
    #skins-list .pagination { display: none; }
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