<?php

use yii\widgets\ListView;

/** @var yii\web\View $this */
/** @var common\models\blog\Blog $model */

?>

<li class="flex items-center bg-background-teritiary py-14 px-16 rounded-8 gap-x-8 relative transition-all">
    <div class="block w-full">
        <p class="p3 flex items-center gap-x-4 mb-12">
            <a class="p3" href="<?=$model->getUrl()?>">
                <span><?=Yii::t('database', $model->name)?></span>
            </a>
        </p>
        <div class="stat-block__list__footer">
            <p class="p3 text-text-teritiary" title="<?=Yii::t('common', 'Количество просмотров')?>">
                <i class="fas fa-eye"></i>
                <span><?=$model->views?></span>
            </p>
            <p class="p3">
                <a href="<?=$model->getUrl()?>#comments" class="block_body_article_list_item_data_item_icon_wrapper" title="<?=Yii::t('common', 'Количество комментариев')?>">
                    <i class="fas fa-comments"></i>
                    <span><?= count($model->comments) ?></span>
                </a>
            </p>
        </div>
    </div>
</li>