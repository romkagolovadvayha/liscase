<?php
/** @var \common\models\building\Building $model */
/** @var array $userLikes */

$isActive = in_array($model->id, $userLikes);
?>

<div class="buildings_content_list_item">
    <div class="buildings_content_list_item_images">
        <img src="<?=$model->buildingImage[0]->getPublicUrlPreview()?>">
        <div class="buildings_content_list_item_images_name"><?=Yii::t('database', $model->name)?></div>
        <div class="buildings_content_list_item_images_server"><?=Yii::t('database', $model->server->name)?></div>
        <div class="buildings_content_list_item_images_like<?=($isActive) ? ' active' : ''?>" data-id="<?=$model->id?>" data-guest="<?=Yii::$app->user->isGuest ? 1 : 0?>">
            <span class="buildings_content_list_item_images_like_count"><?=$model->likes?></span>
            <span class="buildings_content_list_item_images_like_icon">
                    <i class="icon_active fa-solid fa-heart"></i>
                    <i class="icon_noactive fa-regular fa-heart"></i>
            </span>
        </div>
    </div>
    <div class="buildings_content_list_item_footer">
        <a
                title="<?=Yii::t('common', 'Открыть профиль Steam')?>"
                target="_blank"
                class="buildings_content_list_item_footer_profile"
                href="/stats/player?steamId=<?=$model->user->steam_id?>&server=<?=$model->server_tag?>">
            <img src="<?=$model->user->getAvatar()?>" title="<?=$model->user->username?>"/>
            <span class="buildings_content_list_item_footer_profile_name"><?=$model->user->username?></span>
        </a>
        <div class="buildings_content_list_item_footer_date">
            <?=$model->passed()?>
        </div>
    </div>
    <a href="/buildings/view?id=<?=$model->id?>">Подробнее</a>
</div>
