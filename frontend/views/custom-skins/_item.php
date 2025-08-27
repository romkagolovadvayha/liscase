<?php
/** @var \common\models\serverskin\ServerSkin $model */
/** @var array $userLikes */

$isActive = in_array($model->id, $userLikes);
?>

<div class="custom-skins_content_list_item">
    <div class="custom-skins_content_list_item_images">
        <a href="https://steamcommunity.com/sharedfiles/filedetails/?id=<?=$model->skin_id?>" target="_blank">
            <img src="<?=$model->getImage150PubUrl(true)?>">
        </a>
        <div class="custom-skins_content_list_item_images_name"><?=Yii::t('database', $model->name)?></div>
        <div class="custom-skins_content_list_item_images_like<?=($isActive) ? ' active' : ''?>" data-id="<?=$model->id?>" data-guest="<?=Yii::$app->user->isGuest ? 1 : 0?>">
            <span class="custom-skins_content_list_item_images_like_count"><?=$model->likes?></span>
            <span class="custom-skins_content_list_item_images_like_icon">
                    <i class="icon_active fa-solid fa-heart"></i>
                    <i class="icon_noactive fa-regular fa-heart"></i>
            </span>
        </div>
    </div>
    <div class="custom-skins_content_list_item_footer">
        <a
                title="<?=Yii::t('common', 'Открыть профиль Steam')?>"
                target="_blank"
                class="custom-skins_content_list_item_footer_profile"
                href="<?=$model->user->getLink('stats')?>">
            <img src="<?=$model->user->getAvatar()?>" title="<?=$model->user->username?>"/>
            <span class="custom-skins_content_list_item_footer_profile_name"><?=$model->user->username?></span>
        </a>
        <div class="custom-skins_content_list_item_footer_date">
            <?=$model->passed()?>
        </div>
    </div>
</div>
