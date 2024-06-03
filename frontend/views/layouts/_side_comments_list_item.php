<?php

use yii\widgets\ListView;
use common\models\comment\Comment;

/** @var yii\web\View $this */
/** @var Comment $model */

$blog = \common\models\blog\Blog::findOne($model->entityId);

?>
<li class="block_body_article_list_item">
    <article>
        <p class="tm-article-title__link">
            <?=Yii::t('common', 'Пользователь')?>
            <a href="/users/<?=$model->createdByUser->username?>"><?=$model->createdByUser->username?></a>
            <?=Yii::t('common', 'оставил коментарий к записи')?>
            <a href="<?=$blog->getUrl()?>"><?=Yii::t('database', $blog->name)?></a>
        </p>
        <div class="block_body_article_list_item_data">
            <div class="block_body_article_list_item_data_item">
                <div class="block_body_article_list_item_data_item_icon_wrapper" title="<?=Yii::t('common', 'Дата комментария')?>">
                    <?=date('d.m.Y H:i:s', $model->createdAt)?>
                </div>
            </div>
        </div>
    </article>
</li>