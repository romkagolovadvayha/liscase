<?php

use yii\widgets\ListView;
use common\models\comment\Comment;

/** @var yii\web\View $this */
/** @var Comment $model */


?>

<li class="flex items-center bg-background-teritiary py-14 px-16 rounded-8 gap-x-8 relative transition-all">
    <div class="block w-full">
        <p class="p3 gap-x-4 mb-12">
            <?=Yii::t('common', 'Пользователь')?>
            <a class="p3" href="/users/<?=$model->createdByUser->username?>"><?=$model->createdByUser->username?></a>
            <?=Yii::t('common', 'оставил коментарий к записи')?>
            <a class="p3" href="<?=$model->blog->getUrl()?>"><?=Yii::t('database', $model->blog->name)?></a>
        </p>
        <div class="stat-block__list__footer">
            <p class="p3 text-text-teritiary" title="<?=Yii::t('common', 'Дата комментария')?>">
                <?=\common\components\helpers\DateHelper::passed(date('Y-m-d H:i:s', $model->createdAt))?>
            </p>
        </div>
    </div>
</li>