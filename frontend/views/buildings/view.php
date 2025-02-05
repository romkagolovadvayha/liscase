<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use frontend\assets\BuildingsAsset;
use lo\widgets\magnific\MagnificPopup;
use common\models\building\Building;
use common\models\building\BuildingLike;

/** @var yii\web\View $this */
/** @var Building $model */

$this->title = Yii::t('database', $model->name) . " - " . Yii::t('common', 'постройка игрока') . " " . $model->user->username;
BuildingsAsset::register($this);

$isActive = BuildingLike::find()
                    ->andWhere(['building_id' => $model->id])
                    ->andWhere(['user_id' => Yii::$app->user->id])
                    ->exists();
?>
<h1 class="main_title"><span><?=Yii::t('database', $model->name)?></span></h1>
    <div class="server_info_page">
        <div class="buildings_profile">
            <div class="buildings_profile_content">
                <h2><?=Yii::t('common', 'Описание')?></h2>
                <p><?=$model->description?></p>
                <h2><?=Yii::t('common', 'Оценили')?></h2>
                <div class="buildings_profile_users">
                    <div class="buildings_content_list_item_images_like<?=($isActive) ? ' active' : ''?>" data-id="<?=$model->id?>" data-guest="<?=Yii::$app->user->isGuest ? 1 : 0?>">
                        <span class="buildings_content_list_item_images_like_count"><?=$model->likes?></span>
                        <span class="buildings_content_list_item_images_like_icon">
                                            <i class="icon_active fa-solid fa-heart"></i>
                                            <i class="icon_noactive fa-regular fa-heart"></i>
                                    </span>
                    </div>
                    <?php foreach ($model->buildingLikes as $like): ?>
                        <a  title="<?=Yii::t('common', 'Открыть профиль игрока')?> <?=$like->user->username?>"
                            target="_blank"
                            href="<?=$like->user->getLink('stats')?>"
                            class="buildings_profile_users_item">
                            <img src="<?=$like->user->getAvatar()?>" title="<?=$like->user->username?>"/>
                            <span class="buildings_profile_users_item_name"><?=$like->user->username?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <h2><?=Yii::t('common', 'Местоположение базы')?></h2>
                <p><?=Yii::t('common', 'Сервер')?>: <?=Yii::t('database', $model->server->name)?></p>
                <p><?=Yii::t('common', 'Квадрат')?>: <?=$model->location?></p>
                <p><?=Yii::t('common', 'Номер вайпа')?>: <?=$model->wipe?></p>
                <div class="buildings_profile_side">
                    <div class="buildings_profile_side_images" id="mpup">
                        <?php foreach ($model->buildingImage as $i => $image): ?>
                            <a href="<?=$image->getPublicUrl()?>" title="<?=Yii::t('database', $model->name)?>"><img  src="<?=$image->getPublicUrlPreview()?>" alt="<?=$model->name?>"></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <h2><?=Yii::t('common', 'Жильцы')?></h2>
                <div class="buildings_profile_users">
                    <?php foreach ($model->buildingResident as $resident): ?>
                        <a  title="<?=Yii::t('common', 'Открыть профиль игрока')?> <?=$resident->user->username?>"
                            target="_blank"
                            href="/stats/player?steamId=<?=$resident->user->steam_id?>&server=<?=$model->server_tag?>"
                            class="buildings_profile_users_item">
                            <img src="<?=$resident->user->getAvatar()?>" title="<?=$resident->user->username?>"/>
                            <span class="buildings_profile_users_item_name"><?=$resident->user->username?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
<?=MagnificPopup::widget(
    [
        'target' => '#mpup',
        'options' => [
            'delegate'=> 'a',
            'gallery' => [
                'enabled' => true
            ],
        ],
        'effect' => 'with-zoom' //for zoom effect
    ]
);?>