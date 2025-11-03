<?php

use yii\widgets\ListView;
use yii\widgets\ActiveForm;
use yii\widgets\Pjax;
use yii\web\View;
use frontend\widgets\Alert;
use common\models\blog\BlogCategory;

/** @var View $this */
/** @var BlogCategory $blogCategory */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\blog\BlogSearch $searchModel */
/** @var BlogCategory[] $categories */

$this->title = Yii::t('database', $blogCategory->name);
$this->params['breadcrumbs'][] = ['label' => Yii::t('common', "Блог"), 'url' => ["/posts"]];
if (!empty($blogCategory->parentCategory)) {
    $this->params['breadcrumbs'][] = ['label' => Yii::t('database', $blogCategory->parentCategory->name), 'url' => [$blogCategory->parentCategory->getUrl()]];
}
$this->params['h1'] = Yii::t('database', $blogCategory->name);
$this->params['page'] = 'blog';
$this->params['_blog_category'] = $blogCategory;
$this->params['_blog_category_block'] = true;
$this->params['_blog_comments_block'] = true;
$this->params['breadcrumbs'][] = $this->title;
$this->params['meta_keywords'] = Yii::t('database', $blogCategory->keywords);
$this->params['meta_description'] = Yii::t('database', $blogCategory->description);
?>

<?= Alert::widget() ?>

<?php Pjax::begin([
                      'id' => 'blog-pjax',
                      'timeout' => 0,
                      'enablePushState' => true,
                      'scrollTo' => false,
                      'linkSelector' => 'a[data-pjax=1]:not(#blog-list-view .pagination a)',
                      'formSelector' => '#blog-filter-form',
                  ]); ?>

<!-- Шапка блога (если нужна) -->
<?= $this->render('_header', [
    'dataProvider' => $dataProvider,
    'categoryId' => $blogCategory->id,
]) ?>

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
        <?= \yii\helpers\Html::activeTextInput($searchModel, 'name', [
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
