<?php

use yii\widgets\ListView;

/** @var yii\web\View $this */
/** @var common\models\blog\Blog $model */

?>

<li class="flex items-flex-start bg-background-teritiary py-14 px-16 rounded-8 gap-x-8 relative transition-all">
    <?php if (!empty($model->blogImages)): ?>
        <div class="stat-block__list__avatar  stat-block__list__avatar_offline">
            <img src="<?=$model->blogImages[0]->getPublicUrl()?>"
                 class="block w-36 h-36 min-w-36 min-h-36 rounded-6 object-cover" alt="<?=Yii::t('database', $model->name)?>">
        </div>
    <?php endif; ?>
    <div class="flex flex-column w-full gap-y-4">
        <p class="flex justify-space-between items-center flex-wrap">
            <a class="p2 link font-medium" href="<?=$model->getUrl()?>">
                <span><?=Yii::t('database', $model->name)?></span>
            </a>
        </p>
        <div class="stat-block__list__footer">
            <p class="p3 text-text-teritiary" title="<?= Yii::t('common', 'Количество просмотров') ?>">
                <i class="fas fa-eye"></i>
                <span><?= $model->views ?></span>
            </p>
            <p class="p3">
                <a href="<?= $model->getUrl() ?>#comments" class="block_body_article_list_item_data_item_icon_wrapper"
                   title="<?= Yii::t('common', 'Количество комментариев') ?>">
                    <i class="fas fa-comments"></i>
                    <span><?= count($model->comments) ?></span>
                </a>
            </p>
        </div>
    </div>
</li>