<?php

use yii\widgets\ListView;

/** @var common\models\blog\Blog $model */
/** @var yii\web\View $this */
/** @var integer $index */

$date = new DateTime($model->created_at);
$rating = $model->getBlogRatings()->sum('weight') ?? 0;
?>

<article id="<?=$model->id?>" tabindex="<?=$index?>" class="blog_item">
    <div class="blog_item_snippet blog_item_snippet_mini">
        <div class="blog_item_container">
            <div class="blog_item_snippet_meta">
                <span class="blog_item_snippet_meta_author">
                    <a href="/users/<?=$model->user->username?>/" class="blog_item_snippet_meta_author_userpic" title="<?=$model->user->username?>">
                        <div class="blog_item_snippet_meta_author_userpic_image">
                            <img alt="" height="24" src="<?=$model->user->getAvatar()?>" width="24">
                        </div>
                    </a>
                    <span class="blog_item_snippet_meta_author_user">
                        <a href="/users/<?=$model->user->username?>/" class="blog_item_snippet_meta_author_user_username"><?=$model->user->username?></a>
                        <span class="blog_item_snippet_meta_author_user_published">
                            <time datetime="<?=$date->format('c')?><" title="<?=$date->format('d.m.Y, H:i')?>"><?=$date->format('d.m.Y, H:i')?></time>
                        </span>
                    </span>
                </span>
            </div>
        </div>
        <h2 class="blog_item_title"><a href="<?=$model->getUrl()?>" class="tm-title__link"><?=Yii::t('database', $model->name)?></a></h2>
        <div class="blog_item_categories">
            <a href="<?=$model->blogCategory->parentCategory->getUrl()?>"><?=Yii::t('database', $model->blogCategory->parentCategory->name)?></a>, <a href="<?=$model->blogCategory->getUrl()?>"><?=Yii::t('database', $model->blogCategory->name)?></a>
        </div>
        <div class="blog_item_body">
            <?php if (!empty($model->blogImages[0])): ?>
            <div class="blog_item_body_cover">
                <img src="<?=$model->blogImages[0]->link?>" alt="<?=$model->blogImages[0]->description?>">
            </div>
            <?php endif; ?>
            <div class="blog_item_body_text">
                <?=Yii::t('database', $model->description)?>
            </div>
            <a href="<?=$model->getUrl()?>" class="blog_item_body_readmore btn"><span><?=Yii::t('common', 'Читать далее')?></span></a>
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
                    <span><?=$model->views?></span>
                </div>
            </div>
            <div class="blog_item_data_item blog_item_data_comments">
                <a href="<?=$model->getUrl()?>#comments" class="blog_item_data_item_icon_wrapper" title="<?=Yii::t('common', 'Количество комментариев')?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" class="blog_item_data_item_icon">
                        <path d="M19.619 21.671c-5.038 1.227-8.711-1.861-8.711-5.167 0-3.175 3.11-5.467 6.546-5.467 3.457 0 6.546 2.309 6.546 5.467 0 1.12-.403 2.22-1.117 3.073-.029 1 .558 2.435 1.088 3.479-1.419-.257-3.438-.824-4.352-1.385zm-10.711-5.167c0-4.117 3.834-7.467 8.546-7.467.886 0 1.74.119 2.544.338-.021-4.834-4.761-8.319-9.998-8.319-5.281 0-10 3.527-10 8.352 0 1.71.615 3.391 1.705 4.695.047 1.527-.851 3.718-1.661 5.313 2.168-.391 5.252-1.258 6.649-2.115.803.196 1.576.304 2.328.363-.067-.379-.113-.765-.113-1.16z"/>
                    </svg>
                    <span><?=$model->getCountComments()?></span>
                </a>
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