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

$date = new DateTime($blog->created_at);
$rating = $blog->getBlogRatings()->sum('weight') ?? 0;
?>

<div class="container-fluid mb-5">
    <div class="main_wrap">
        <aside>
            <?=$this->render('../layouts/_side_similar_posts', [
                'model' => $blog
            ])?>
            <?=$this->render('../layouts/_side_category_list', [
                'category' => $blog->blogCategory
            ])?>
            <?= $this->render('@frontend/views/widgets/_servers'); ?>
            <?php echo $this->render('@frontend/views/layouts/_promocode_line'); ?>
            <?= $this->render('@frontend/views/widgets/_live'); ?>
            <!--            --><?php //echo $this->render('@frontend/views/widgets/_bonuses'); ?>
            <?= $this->render('@frontend/views/widgets/_banners'); ?>
        </aside>
        <main id="main" role="main">
            <div class="main_child">
                <?= Alert::widget() ?>
                <article id="<?=$blog->id?>" class="blog_item">
                    <div class="blog_item_snippet">
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
                        <h1 class="blog_item_title"><?=Yii::t('database', $blog->name)?></h1>
                        <div class="blog_item_categories">
                            <a href="<?=$blog->blogCategory->parentCategory->getUrl()?>"><?=Yii::t('database', $blog->blogCategory->parentCategory->name)?></a>, <a href="<?=$blog->blogCategory->getUrl()?>"><?=Yii::t('database', $blog->blogCategory->name)?></a>
                        </div>
                        <div class="blog_item_body">
                            <div class="blog_item_body_text">
                                <div class="blog_item_body_text_images">
                                <?php foreach ($blog->blogImages as $image): ?>
                                    <div class="blog_item_body_text_images_item">
                                        <img src="<?="/uploads" . $image->link?>" alt="<?=$image->description?>">
                                    </div>
                                <?php endforeach; ?>
                                </div>
                                <?=Yii::t('database', $blog->content)?>
                            </div>
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
                </article>
                <div id="comments">
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
            </div>
        </main>
    </div>
</div>
