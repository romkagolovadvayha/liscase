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

<main id="main" role="main">
    <div class="content_wrapper container">
        <div class="content">
            <?= Alert::widget() ?>
            <article id="<?=$blog->id?>" class="blog_item">
                <div class="blog_item_snippet">
                    <div class="blog_item_container">
                        <div class="blog_item_snippet_meta">
                            <span class="blog_item_snippet_meta_author">
                                <a href="/users/<?=$blog->user->username?>/" class="blog_item_snippet_meta_author_userpic" title="<?=$blog->user->username?>">
                                    <div class="blog_item_snippet_meta_author_userpic_image">
                                        <img alt="" height="24" src="<?=$blog->user->getAvatar()?>" width="24">
                                    </div>
                                </a>
                                <span class="blog_item_snippet_meta_author_user">
                                    <a href="/users/<?=$blog->user->username?>/" class="blog_item_snippet_meta_author_user_username"><?=$blog->user->username?></a>
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
                            <?=Yii::t('database', $blog->content)?>
                            <?=$blog->description?>
                        </div>
                    </div>
                    <div class="blog_item_data">
                        <div class="blog_item_data_item blog_item_data_rating">
                            <button href="#" class="blog_item_data_item_icon_wrapper" title="<?=Yii::t('common', 'Повысить рейтинг')?>" disabled>
                                <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="blog_item_data_item_icon">
                                    <path d="m16.843 13.789c.108.141.157.3.157.456 0 .389-.306.755-.749.755h-8.501c-.445 0-.75-.367-.75-.755 0-.157.05-.316.159-.457 1.203-1.554 3.252-4.199 4.258-5.498.142-.184.36-.29.592-.29.23 0 .449.107.591.291 1.002 1.299 3.044 3.945 4.243 5.498z"/>
                                </svg>
                            </button>
                            <span class="blog_item_data_item_counter <?=$rating > 0 ? 'plus' : ($rating < 0 ? 'minus' : '')?>"><?=$rating?></span>
                            <button href="#" class="blog_item_data_item_icon_wrapper" title="<?=Yii::t('common', 'Понизить рейтинг')?>" disabled>
                                <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="blog_item_data_item_icon">
                                    <path d="m16.843 10.211c.108-.141.157-.3.157-.456 0-.389-.306-.755-.749-.755h-8.501c-.445 0-.75.367-.75.755 0 .157.05.316.159.457 1.203 1.554 3.252 4.199 4.258 5.498.142.184.36.29.592.29.23 0 .449-.107.591-.291 1.002-1.299 3.044-3.945 4.243-5.498z"/>
                                </svg>
                            </button>
                        </div>
                        <div class="blog_item_data_item blog_item_data_views">
                            <div class="blog_item_data_item_icon_wrapper" title="<?=Yii::t('common', 'Количество просмотров')?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" class="blog_item_data_item_icon">
                                    <path d="M15 12c0 1.654-1.346 3-3 3s-3-1.346-3-3 1.346-3 3-3 3 1.346 3 3zm9-.449s-4.252 8.449-11.985 8.449c-7.18 0-12.015-8.449-12.015-8.449s4.446-7.551 12.015-7.551c7.694 0 11.985 7.551 11.985 7.551zm-7 .449c0-2.757-2.243-5-5-5s-5 2.243-5 5 2.243 5 5 5 5-2.243 5-5z"/>
                                </svg>
                                <span><?=$blog->views?></span>
                            </div>
                        </div>
                        <div class="blog_item_data_item blog_item_data_sharing">
                            <a href="#" class="blog_item_data_item_icon_wrapper" title="<?=Yii::t('common', 'Поделиться')?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" class="blog_item_data_item_icon">
                                    <path d="M6 17c2.269-9.881 11-11.667 11-11.667v-3.333l7 6.637-7 6.696v-3.333s-6.17-.171-11 5zm12 .145v2.855h-16v-12h6.598c.768-.787 1.561-1.449 2.339-2h-10.937v16h20v-6.769l-2 1.914z"/>
                                </svg>
                            </a>
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
        <div class="side">
            <div class="side_container">
                <?=$this->render('../layouts/_side_similar_posts', [
                    'model' => $blog
                ])?>
                <?=$this->render('../layouts/_side_category_list', [
                    'category' => $blog->blogCategory
                ])?>
                <?=$this->render('../layouts/_side_popular_posts')?>
                <?=$this->render('../layouts/_side_comments_list')?>
            </div>
        </div>
    </div>
</main>

