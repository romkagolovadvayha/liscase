<?php
use yii\widgets\ListView;
use yii\widgets\ActiveForm;
use yii\widgets\Pjax;
use yii\helpers\Html;
use common\models\blog\BlogCategory;

/** @var yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\blog\BlogSearch $searchModel */
/** @var \common\models\blog\BlogCategory[] $categories */

$request = Yii::$app->request;
$pagination = $dataProvider->getPagination();
$sort = $dataProvider->getSort();
$baseTitle = Yii::t('common', 'Баги и новости Rust');
$baseDescription = Yii::t('common', 'Новости и блог проекта: баги, ошибки, патчи, Twitch Drops, новые скины и обновления. Читайте полезные статьи и гайды для игроков Rust.');
$titleParts = [];
$descriptionParts = [];

if ($pagination) {
    $pageNumber = (int)$request->get($pagination->pageParam, 1);
    if ($pageNumber > 1) {
        $titleParts[] = Yii::t('common', 'Страница {number}', ['number' => $pageNumber]);
        $descriptionParts[] = Yii::t('common', 'Сейчас вы просматриваете страницу {number}.', ['number' => $pageNumber]);
    }
    $pageSizeParam = $pagination->pageSizeParam;
    if (!empty($pageSizeParam) && $request->get($pageSizeParam)) {
        $perPage = (int)$request->get($pageSizeParam);
        if ($perPage > 0 && $perPage !== $pagination->pageSize) {
            $titleParts[] = Yii::t('common', 'Показывать по {count} записей', ['count' => $perPage]);
            $descriptionParts[] = Yii::t('common', 'На странице отображается {count} публикаций.', ['count' => $perPage]);
        }
    }
}

if ($sort) {
    $sortValue = $request->get($sort->sortParam);
    if (!empty($sortValue)) {
        $sortLabels = [
            '-created_at' => Yii::t('common', 'Сортировка по дате: новые сверху'),
            'created_at'  => Yii::t('common', 'Сортировка по дате: старые сверху'),
            '-views'      => Yii::t('common', 'Сортировка по просмотрам'),
            'views'       => Yii::t('common', 'Сортировка по просмотрам (по возрастанию)'),
        ];
        $label = $sortLabels[$sortValue] ?? Yii::t('common', 'Сортировка: {value}', ['value' => $sortValue]);
        $titleParts[] = $label;
        $descriptionParts[] = $label . '.';
    }
}

if (!empty($searchModel->name)) {
    $queryLabel = Yii::t('common', 'Поиск: «{query}»', ['query' => $searchModel->name]);
    $titleParts[] = $queryLabel;
    $descriptionParts[] = Yii::t('common', 'Фильтр по названию: «{query}».', ['query' => $searchModel->name]);
}

$categorySlug = $request->get('categoryLinkNameChild') ?? $request->get('categoryLinkName');
if (!empty($categorySlug)) {
    $category = BlogCategory::find()->andWhere([
        'status' => BlogCategory::STATUS_ACTIVE,
        'link_name' => $categorySlug,
    ])->one();
    if ($category) {
        $titleParts[] = Yii::t('common', 'Категория: {name}', ['name' => Yii::t('database', $category->name)]);
        $descriptionParts[] = Yii::t('common', 'Материалы категории «{name}».', ['name' => Yii::t('database', $category->name)]);
    }
}

$this->title = $baseTitle . (!empty($titleParts) ? ' — ' . implode(' · ', $titleParts) : '');
$metaDescription = $baseDescription . (!empty($descriptionParts) ? ' ' . implode(' ', $descriptionParts) : '');

$this->params['meta_description'] = $metaDescription;
$this->registerMetaTag([
    'property' => 'og:title',
    'content' => $this->title,
], 'og:title');
$this->registerMetaTag([
    'property' => 'og:description',
    'content' => $metaDescription,
], 'og:description');

$this->params['h1'] = Yii::t('common', 'Блог');
$this->params['page'] = 'blog';
$this->params['_blog_comments_block'] = true;
$this->params['_blog_category_block'] = true;
$this->params['breadcrumbs'][] = ['label' => Yii::t('common', "Блог")];
?>

<?php Pjax::begin([
                      'id' => 'blog-pjax',
                      'timeout' => 0,
                      'enablePushState' => true,
                      'scrollTo' => false,
                      'linkSelector' => 'a[data-pjax=1]:not(#blog-list-view .pagination a)',
                      'formSelector' => '#blog-filter-form',
                  ]); ?>

    <!-- Шапка блога (если нужна) -->
    <?= $this->render('_header', ['dataProvider' => $dataProvider]) ?>

    <!-- Поиск -->
    <div class="blog-search-section">
        <?php $form = ActiveForm::begin([
            'id' => 'blog-filter-form',
            'method' => 'get',
            'action' => ['index'],
            'options' => ['data-pjax' => 1],
        ]); ?>

        <div class="blog-search-input-wrapper">
            <i class="fas fa-search blog-search-icon"></i>
            <?= Html::activeTextInput($searchModel, 'name', [
                'placeholder' => Yii::t('common', 'Поиск по названию поста...'),
                'autocomplete' => 'off',
                'class' => 'blog-search-input',
                'onkeyup' => 'if(event.key==="Enter") this.form.submit()',
            ]) ?>
            <button type="submit" class="blog-search-submit">
                <i class="fas fa-arrow-right"></i>
            </button>
        </div>

        <?php ActiveForm::end(); ?>
    </div>
    
    <!-- Категории -->
    <?= $this->render('_categories', ['categories' => $categories]) ?>

    <!-- Список в виде мозаики -->
<?= ListView::widget([
                         'id'           => 'blog-list-view',
                         'dataProvider' => $dataProvider,
                         'layout'       => '<div class="masonry">{items}</div>{pager}',
                         'itemView'     => '../blog/_item',          // твой текущий _item (чуть раньше мы подправили)
                         'itemOptions'  => ['tag' => 'div', 'class' => 'masonry-item'],
                         'options'      => ['tag' => 'div', 'class' => 'blog-list-wrap'],
                         'pager'        => [
                             'options'        => ['class' => 'pagination'],
                             'linkOptions'    => ['data-pjax' => 1],
                             'maxButtonCount' => 0,
                             'nextPageLabel'  => Yii::t('common','Показать ещё'),
                             'prevPageLabel'  => false,
                         ],
                     ]) ?>

    <!-- Сентинел для бесконечной подгрузки -->
    <div id="lazy-trigger" aria-hidden="true"></div>

<?php Pjax::end(); ?>

<?php
// Мозаика (если ещё не добавлял)
$this->registerCss(<<<CSS

#blog-list-view .pagination{display:none}
#lazy-trigger{height:1px;margin-top:1px;visibility:hidden}
#lazy-trigger.loading{visibility:visible;display:flex;justify-content:center;align-items:center;height:64px}
#lazy-trigger.loading::after{
  content:'';display:block;margin-top:12px;width:50px;height:50px;border-radius:50%;
  border:5px solid rgba(255,255,255,.2);border-top-color:rgba(255,255,255,.6);
  animation:spin .8s linear infinite
}
@keyframes spin{to{transform:rotate(360deg)}}
CSS);

// Бесконечная подгрузка
$this->registerJs(<<<JS
(function(){
  var loading=false, observer=null;

  function nextUrl(){
    var \$a = $('#blog-list-view .pagination a[rel="next"], #blog-list-view .pagination li.next a');
    return \$a.length ? \$a.attr('href') : null;
  }

  function requestFragment(url){
    return $.ajax({
      url: url, type: 'GET', cache: true,
      headers: {'X-PJAX':'true'}, data: {_pjax:'#blog-pjax'}
    });
  }

  function ensureTrigger(){
    var \$wrap = $('#blog-list-view .masonry');
    if (\$wrap.length) $('#lazy-trigger').appendTo(\$wrap);
  }

  function loadNext(){
    if(loading) return;
    var url = nextUrl();
    if(!url) return;
    loading=true; $('#lazy-trigger').addClass('loading');

    requestFragment(url).done(function(html){
      var \$html = $('<div>').html(html);
      var \$newItems = \$html.find('#blog-list-view .masonry .masonry-item');
      var \$newPager = \$html.find('#blog-list-view .pagination');

      if(\$newItems.length){
        // Проверяем дубликаты перед добавлением
        var existingIds = {};
        $('#blog-list-view .masonry .blog-card').each(function(){
          var id = $(this).attr('id');
          if(id) existingIds[id] = true;
        });
        
        \$newItems.each(function(){
          var \$card = $(this).find('.blog-card');
          var id = \$card.attr('id');
          if(!id || !existingIds[id]){
            $('#blog-list-view .masonry').append($(this));
            if(id) existingIds[id] = true;
          }
        });
      }
      if(\$newPager.length){
        $('#blog-list-view .pagination').replaceWith(\$newPager);
      } else {
        $('#blog-list-view .pagination').remove();
      }

      if(!nextUrl() && observer){ observer.disconnect(); }
      ensureTrigger();
    }).always(function(){
      loading=false; $('#lazy-trigger').removeClass('loading');
    });
  }

  function bindObserver(){
    if(observer){ observer.disconnect(); observer=null; }
    var el=document.getElementById('lazy-trigger'); if(!el) return;
    observer=new IntersectionObserver(function(entries){
      entries.forEach(function(e){
        if(e.isIntersecting && !loading && nextUrl()) loadNext();
      });
    }, {root:null, rootMargin:'600px 0px', threshold:0});
    observer.observe(el);
  }

  ensureTrigger(); bindObserver();

  // при смене фильтра/страницы через PJAX — перевешиваем
  $(document).on('pjax:success', '#blog-pjax', function(){
    ensureTrigger(); bindObserver();
  });

  // кликом по "Показать ещё" тоже грузим без перезагрузки
  $(document).on('click', '#blog-list-view .pagination a[rel="next"], #blog-list-view .pagination li.next a', function(e){
    e.preventDefault(); loadNext();
  });
})();
JS);
?>

<script>
// Categories dropdown interaction for touch devices
document.addEventListener('click', function(e){
    var item = e.target.closest('.blog-categories_item');
    
    // Click on category link with dropdown
    if (e.target.closest('.blog-categories_link')){
        var hasDrop = !!(item && item.querySelector('.blog-categories_dropdown'));
        
        if (hasDrop){
            // First tap - open dropdown
            if (!item.classList.contains('is-open')){
                e.preventDefault();
                item.classList.add('is-open');
                item.querySelector('.blog-categories_link').setAttribute('aria-expanded','true');
                return;
            }
            // Second tap - follow link
        }
    }
    
    // Click outside - close all dropdowns
    document.querySelectorAll('.blog-categories_item.is-open').forEach(function(it){
        if (!it.contains(e.target)){
            it.classList.remove('is-open');
            var link = it.querySelector('.blog-categories_link');
            if (link) link.setAttribute('aria-expanded','false');
        }
    });
});
</script>
