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

// SEO настройки
$this->title = Yii::t('common', 'Постройки игроков Rust - Галерея лучших баз и построек');
$this->registerMetaTag([
    'name' => 'description',
    'content' => Yii::t('common', 'Галерея построек игроков Rust. Смотрите лучшие базы, креативные постройки и архитектурные шедевры от нашего сообщества. Делитесь своими творениями и вдохновляйтесь идеями других игроков.')
]);
$this->registerMetaTag(['name' => 'keywords', 'content' => 'rust постройки, базы rust, галерея построек, rust база, лучшие постройки rust, креативные базы']);

// Open Graph
$this->registerMetaTag(['property' => 'og:title', 'content' => $this->title]);
$this->registerMetaTag(['property' => 'og:description', 'content' => Yii::t('common', 'Галерея построек игроков Rust. Лучшие базы и креативные постройки от нашего сообщества.')]);
$this->registerMetaTag(['property' => 'og:type', 'content' => 'website']);

// Структурированные данные
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => $this->title,
    'description' => 'Галерея построек игроков Rust с возможностью фильтрации по серверам',
    'mainEntity' => [
        '@type' => 'ItemList',
        'name' => 'Постройки игроков',
    ]
];
$this->registerMetaTag(['name' => 'schema', 'content' => json_encode($schema, JSON_UNESCAPED_UNICODE)], 'schema');
?>

<div class="server_info_page">
    <div class="buildings">
        <!-- SEO заголовок и описание -->
        <div class="buildings_header">
            <h1 class="buildings_header_title">
                <?= Yii::t('common', 'Постройки игроков Rust') ?>
            </h1>
            <div class="buildings_header_description">
                <p><?= Yii::t('common', 'Добро пожаловать в галерею построек нашего Rust сообщества! Здесь вы найдете самые креативные базы, впечатляющие архитектурные решения и уникальные постройки от талантливых игроков.') ?></p>
                <p><?= Yii::t('common', 'Изучайте работы других строителей, черпайте вдохновение для своих проектов и делитесь собственными творениями. Каждая постройка — это история, опыт и творческий подход к выживанию в мире Rust.') ?></p>
            </div>
        </div>

        <?php if (!$userBuildingsWait): ?>
            <div class="buildings_buttons">
                <?= Html::a('<span class="button__text"><i class="fa-solid fa-plus"></i> ' . Yii::t('common', 'Добавить свою постройку') . '</span>', ['create'], ['class' => 'button button-primary button-size__s h-36']) ?>
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

        <!-- Фильтры в современном дизайне -->
        <section class="buildings_filters_section">
            <?php $form = ActiveForm::begin([
                'id' => 'buildings-filter-form',
                'method' => 'get',
                'action' => ['index'],
                'options' => ['data-pjax' => 1, 'class' => 'buildings_filter_form'],
            ]); ?>

            <div class="buildings_filter_row">
                <!-- Поиск по названию -->
                <div class="buildings_filter_search">
                    <div class="buildings_filter_search_wrapper">
                        <i class="fa-solid fa-search buildings_filter_search_icon"></i>
                        <?= $form->field($searchModel, 'name', [
                            'template' => '{input}',
                            'options' => ['class' => 'buildings_filter_search_field']
                        ])->textInput([
                            'placeholder' => Yii::t('common', 'Поиск постройки...'),
                            'class' => 'buildings_filter_search_input',
                            'autocomplete' => 'off'
                        ]) ?>
                        <button type="submit" class="buildings_filter_search_submit" title="<?= Yii::t('common', 'Найти') ?>">
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Фильтр по серверам -->
                <div class="buildings_filter_servers">
                    <div class="buildings_filter_servers_content">
                        <?= $form->field($searchModel, 'server_tag')->widget(\kartik\select2\Select2::class, [
                            'data' => $servers,
                            'options' => [
                                'placeholder' => Yii::t('common', 'Выберите сервер(а)...'),
                                'multiple' => true,
                                'id' => 'server-select',
                            ],
                            'pluginOptions' => [
                                'allowClear' => true,
                                'closeOnSelect' => false,
                                'theme' => 'default',
                            ],
                            'pluginEvents' => [
                                "change" => "function(){ $('#buildings-filter-form').submit(); }",
                            ],
                            'showToggleAll' => true,
                        ])->label(false) ?>
                    </div>
                </div>
            </div>

            <?php ActiveForm::end(); ?>
        </section>

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
  var loadedPages = new Set(); // Отслеживаем загруженные страницы

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
      cache: false // Отключаем кэш чтобы избежать дублей
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
    
    // Проверяем, не загружали ли мы уже эту страницу
    if (loadedPages.has(url)) {
      console.log('Page already loaded:', url);
      return;
    }

    loading = true;
    loadedPages.add(url); // Добавляем URL в список загруженных
    $('#lazy-trigger').addClass('loading');
    
    // Временно отключаем observer во время загрузки
    if (observer) {
      observer.disconnect();
    }

    requestPjaxFragment(url).done(function(html){
      var \$html = $('<div>').html(html);

      // новые карточки
      var \$newItemsWrap = \$html.find('#buildings-list .buildings_content_list_items');
      if (!\$newItemsWrap.length) {
        \$newItemsWrap = \$html.find('.buildings_content_list_items');
      }
      var \$newItems = \$newItemsWrap.children();

      // Проверяем на дубликаты перед добавлением
      var existingIds = new Set();
      $('#buildings-list .buildings_content_list_items article').each(function(){
        var itemId = $(this).find('[data-id]').attr('data-id');
        if (itemId) {
          existingIds.add(itemId);
        }
      });

      // Фильтруем новые элементы, удаляя дубликаты
      var \$uniqueItems = \$newItems.filter(function(){
        var itemId = $(this).find('[data-id]').attr('data-id');
        return !itemId || !existingIds.has(itemId);
      });

      // новая пагинация
      var \$newPager = \$html.find('#buildings-list .pagination');
      if (!\$newPager.length) {
        \$newPager = \$html.find('.pagination');
      }

      // аппендим в текущий список только уникальные элементы
      if (\$uniqueItems.length > 0) {
        $('#buildings-list .buildings_content_list_items').append(\$uniqueItems);
      }

      // заменяем/убираем пагинацию
      var \$oldPager = $('#buildings-list .pagination');
      if (\$newPager.length) {
        \$oldPager.replaceWith(\$newPager);
      } else {
        \$oldPager.remove();
      }

      ensureTriggerAtBottom();
      
      // Если есть еще страницы, возобновляем observer
      if (getNextUrl()) {
        bindObserver();
      }
    }).fail(function(){
      // В случае ошибки удаляем URL из загруженных
      loadedPages.delete(url);
    }).always(function(){
      loading = false;
      $('#lazy-trigger').removeClass('loading');
    });
  }

  function bindObserver(){
    if (observer) { 
      observer.disconnect(); 
      observer = null; 
    }
    
    var target = document.getElementById('lazy-trigger');
    if (!target) return;

    observer = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (entry.isIntersecting && !loading && getNextUrl()) {
          loadNext();
        }
      });
    }, { 
      root: null, 
      rootMargin: '400px 0px', // Уменьшили с 600px чтобы не загружать слишком рано
      threshold: 0 
    });

    observer.observe(target);
  }

  // старт
  ensureTriggerAtBottom();
  bindObserver();

  // после любого PJAX-обновления (фильтры и т.п.) — перевешиваем
  $(document).on('pjax:success', '#buildings-pjax', function(){
    loadedPages.clear(); // Очищаем историю загруженных страниц при фильтрации
    ensureTriggerAtBottom();
    bindObserver();
  });
})();
JS;

$this->registerJs($js);
?>
