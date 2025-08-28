<?php
use yii\widgets\ListView;
use yii\widgets\ActiveForm;
use yii\widgets\Pjax;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\blog\BlogSearch $searchModel */

$this->title = Yii::t('common', 'Баги и новости Rust');
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

    <!-- Фильтр по названию -->
    <div class="blog-filter">
        <?php $form = ActiveForm::begin([
                                            'id' => 'blog-filter-form',
                                            'method' => 'get',
                                            'action' => ['index'],
                                            'options' => ['data-pjax' => 1],
                                        ]); ?>

        <?= $form->field($searchModel, 'name')->textInput([
                                                              'placeholder' => Yii::t('common', 'Поиск по названию…'),
                                                              'autocomplete' => 'off',
                                                              'onchange' => 'this.form.submit()',
                                                          ])->label(false) ?>

        <?php ActiveForm::end(); ?>
    </div>

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
.masonry{column-count:3;column-gap:16px}
.masonry-item{break-inside:avoid;margin:0 0 16px;display:inline-block;width:100%}
@media (max-width:1200px){.masonry{column-count:2}}
@media (max-width:640px){.masonry{column-count:1;column-gap:12px}.masonry-item{margin-bottom:12px}}

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
        $('#blog-list-view .masonry').append(\$newItems);
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
