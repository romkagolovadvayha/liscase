<?php
/** @var \common\models\building\Building $model */
/** @var array $userLikes */

use yii\helpers\Html;

$isActive = in_array($model->id, $userLikes);
$buildingName = Yii::t('database', $model->name);
$serverName = Yii::t('database', $model->server->monitoring_name);
?>

<article class="buildings_content_list_item" itemscope itemtype="https://schema.org/CreativeWork">
    <div class="buildings_content_list_item_images">
        <div class="buildings_content_list_item_images_wrapper">
            <img src="<?= $model->buildingImage[0]->getPublicUrlPreview() ?>" 
                 alt="<?= $buildingName ?> - Постройка на сервере <?= $serverName ?>"
                 itemprop="image"
                 loading="lazy">
        </div>
        
        <div class="buildings_content_list_item_images_overlay">
            <div class="buildings_content_list_item_images_name" itemprop="name">
                <?= $buildingName ?>
            </div>
            <div class="buildings_content_list_item_images_server">
                <i class="fa-solid fa-server"></i> <?= $serverName ?>
            </div>
        </div>
        
        <button type="button" 
                class="buildings_content_list_item_images_like<?= ($isActive) ? ' active' : '' ?>" 
                data-id="<?= $model->id ?>" 
                data-guest="<?= Yii::$app->user->isGuest ? 1 : 0 ?>"
                title="<?= Yii::t('common', 'Оценить постройку') ?>"
                aria-label="<?= Yii::t('common', 'Оценить постройку') ?>">
            <span class="buildings_content_list_item_images_like_count" itemprop="interactionCount">
                <?= $model->likes ?>
            </span>
            <span class="buildings_content_list_item_images_like_icon">
                <i class="icon_active fa-solid fa-heart"></i>
                <i class="icon_noactive fa-regular fa-heart"></i>
            </span>
        </button>
    </div>
    
    <footer class="buildings_content_list_item_footer">
        <a title="<?= Yii::t('common', 'Открыть профиль игрока') ?> <?= $model->user->username ?>"
           target="_blank"
           class="buildings_content_list_item_footer_profile"
           rel="nofollow"
           href="<?= $model->user->getLink('stats') ?>"
           itemprop="author"
           itemscope 
           itemtype="https://schema.org/Person">
            <img src="<?= $model->user->getAvatar() ?>" 
                 alt="<?= $model->user->username ?>"
                 loading="lazy"/>
            <span class="buildings_content_list_item_footer_profile_name" itemprop="name">
                <?= $model->user->username ?>
            </span>
        </a>
        
        <time class="buildings_content_list_item_footer_date" 
              datetime="<?= date('c', is_numeric($model->created_at) ? $model->created_at : strtotime($model->created_at)) ?>"
              itemprop="datePublished">
            <?= $model->passed() ?>
        </time>
    </footer>
    
    <?= Html::a(
        Yii::t('common', 'Подробнее'),
        ['/buildings/view', 'id' => $model->id],
        [
            'class' => 'buildings_content_list_item_link',
            'title' => Yii::t('common', 'Смотреть постройку') . ' ' . $buildingName,
            'itemprop' => 'url'
        ]
    ) ?>
</article>
