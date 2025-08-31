<?php

use yii\web\View;
use frontend\widgets\Alert;
use common\models\blog\Blog;
use yii\bootstrap5\Breadcrumbs;

/** @var View $this */
/** @var Blog $blog */

$this->title = Yii::t('database', $blog->name);
$this->params['breadcrumbs'][] = ['label' => Yii::t('common', "Блог"), 'url' => ["/posts"]];
if (!empty($blog->blogCategory->parentCategory)) {
    $this->params['breadcrumbs'][] = ['label' => Yii::t('database', $blog->blogCategory->parentCategory->name), 'url' => [$blog->blogCategory->parentCategory->getUrl()]];
}
$this->params['breadcrumbs'][] = ['label' => Yii::t('database', $blog->blogCategory->name), 'url' => [$blog->blogCategory->getUrl()]];
$this->params['breadcrumbs'][] = $this->title;
$this->params['meta_keywords'] = Yii::t('database', $blog->keywords);
$this->params['meta_description'] = Yii::t('database', $blog->description);
$this->params['page'] = 'blog';

$date = new DateTime($blog->created_at);
$ratings = $blog->blogRatings;
$rating = 0;
foreach ($ratings as $_rating) {
    $rating += $_rating->weight;
}

$this->params['_blog_category'] = $blog->blogCategory;
$this->params['_blog_model'] = $blog;
$this->params['_blog_comments_block'] = true;
$this->params['_blog_category_block'] = true;
$this->params['_blog_similar_block'] = true;

$blog->views++;
$blog->save();

?>

<?= Alert::widget() ?>
    <article id="<?=$blog->id?>" class="blog_item">
        <div class="blog_item_container_wrap">
        <div class="blog_item_snippet">
            <h1 class="blog_item_title"><?=Yii::t('database', $blog->name)?></h1>
            <div class="blog_item_categories">
                <a href="<?=$blog->blogCategory->parentCategory->getUrl()?>"><?=Yii::t('database', $blog->blogCategory->parentCategory->name)?></a>, <a href="<?=$blog->blogCategory->getUrl()?>"><?=Yii::t('database', $blog->blogCategory->name)?></a>
            </div>
            <div class="blog_item_container">
                <div class="blog_item_snippet_meta">
                            <span class="blog_item_snippet_meta_author">
                                <span class="blog_item_snippet_meta_author_user">
                                    <span class="blog_item_snippet_meta_author_user_published">
                                        <time datetime="<?=$date->format('c')?><" title="<?=$date->format('d.m.Y, H:i')?>"><?=$date->format('d.m.Y, H:i')?></time>
                                    </span>
                                </span>
                            </span>
                </div>
            </div>
            <div class="blog_item_body">
                <?php if (strtotime($blog->created_at) < strtotime('2025-08-28 12:00')): ?>
                    <?php if (!empty($blog->blogImages)): ?>
                    <div class="blog_item_body_text_images mb-24">
                        <?php foreach ($blog->blogImages as $image): ?>
                            <?=$this->render('_file', [
                                'url' => $image->getPublicUrl(),
                                'name' => $image->link
                            ]); ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="mb-24 tinymce-content"><?=Yii::t('database', $blog->content)?></div>
                <?php if ($blog->created_at < '2025-03-30 00:00:00'): ?>
                    <p class="p2">
                        <?=Yii::t('common', 'Поставщик новостей')?>: <a href="https://discord.gg/rust-ru" rel="nofollow" class="p2" target="_blank">RustRu</a>
                    </p>
                <?php endif; ?>
            </div>
            <div class="blog_item_data">
                <div class="blog_item_data_item blog_item_data_views">
                    <div class="blog_item_data_item_icon_wrapper" title="<?=Yii::t('common', 'Количество просмотров')?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" class="blog_item_data_item_icon">
                            <path d="M15 12c0 1.654-1.346 3-3 3s-3-1.346-3-3 1.346-3 3-3 3 1.346 3 3zm9-.449s-4.252 8.449-11.985 8.449c-7.18 0-12.015-8.449-12.015-8.449s4.446-7.551 12.015-7.551c7.694 0 11.985 7.551 11.985 7.551zm-7 .449c0-2.757-2.243-5-5-5s-5 2.243-5 5 2.243 5 5 5 5-2.243 5-5z"/>
                        </svg>
                        <span><?=$blog->views?></span>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </article>
        <?=$this->render('../layouts/_side_similar_posts', ['model' => $this->params['_blog_model']]);?>
    <div id="comments" class="mt-12">
        <?php echo \yii2mod\comments\widgets\Comment::widget([
                                                                 'model' => $blog,
                                                                 'commentView' => '@frontend/views/blog/comments/index',
                                                                 'maxLevel' => 2,
                                                                 'dataProviderConfig' => [
                                                                     'pagination' => [
                                                                         'pageSize' => 10
                                                                     ],
                                                                 ],
                                                                 'listViewConfig' => [
                                                                     'emptyText' => Yii::t('common', 'Нет комментариев.'),
                                                                 ],
                                                             ]); ?>
    </div>
<?=\lo\widgets\magnific\MagnificPopup::widget(
    [
        'target' => '.blog_item_body_text_images_item_preview_wrap',
        'options' => [
            'delegate'=> 'a',
            'gallery' => [
                'enabled' => true
            ],
        ],
        'effect' => 'with-zoom' //for zoom effect
    ]
);?>