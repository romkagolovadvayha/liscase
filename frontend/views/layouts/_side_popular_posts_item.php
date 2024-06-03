<?php

use yii\widgets\ListView;

/** @var yii\web\View $this */
/** @var common\models\blog\Blog $model */

?>
<li class="block_body_article_list_item">
    <article>
        <h2>
            <a href="<?=$model->getUrl()?>" class="tm-article-title__link">
                <span><?=Yii::t('database', $model->name)?></span>
            </a>
        </h2>
        <div class="block_body_article_list_item_data">
            <div class="block_body_article_list_item_data_item block_body_article_list_item_data_views">
                <div class="block_body_article_list_item_data_item_icon_wrapper" title="<?=Yii::t('common', 'Количество просмотров')?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" class="block_body_article_list_item_data_item_icon">
                        <path d="M15 12c0 1.654-1.346 3-3 3s-3-1.346-3-3 1.346-3 3-3 3 1.346 3 3zm9-.449s-4.252 8.449-11.985 8.449c-7.18 0-12.015-8.449-12.015-8.449s4.446-7.551 12.015-7.551c7.694 0 11.985 7.551 11.985 7.551zm-7 .449c0-2.757-2.243-5-5-5s-5 2.243-5 5 2.243 5 5 5 5-2.243 5-5z"/>
                    </svg>
                    <span><?=$model->views?></span>
                </div>
            </div>
            <div class="block_body_article_list_item_data_item block_body_article_list_item_data_comments">
                <a href="<?=$model->getUrl()?>#comments" class="block_body_article_list_item_data_item_icon_wrapper" title="<?=Yii::t('common', 'Количество комментариев')?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" class="block_body_article_list_item_data_item_icon">
                        <path d="M19.619 21.671c-5.038 1.227-8.711-1.861-8.711-5.167 0-3.175 3.11-5.467 6.546-5.467 3.457 0 6.546 2.309 6.546 5.467 0 1.12-.403 2.22-1.117 3.073-.029 1 .558 2.435 1.088 3.479-1.419-.257-3.438-.824-4.352-1.385zm-10.711-5.167c0-4.117 3.834-7.467 8.546-7.467.886 0 1.74.119 2.544.338-.021-4.834-4.761-8.319-9.998-8.319-5.281 0-10 3.527-10 8.352 0 1.71.615 3.391 1.705 4.695.047 1.527-.851 3.718-1.661 5.313 2.168-.391 5.252-1.258 6.649-2.115.803.196 1.576.304 2.328.363-.067-.379-.113-.765-.113-1.16z"/>
                    </svg>
                    <span><?=$model->getCountComments()?></span>
                </a>
            </div>
        </div>
    </article>
</li>