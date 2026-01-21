<?php

use common\models\box\Drop;
use common\models\box\DropFavorite;
use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use Yii;

/** @var Drop $model */
/** @var string $balance */

if (empty($model->imageOrig)) {
    return null;
}
$image = Html::img($model->imageOrig->getImagePubUrl(), ['width' => '40px']);

// Проверяем, находится ли товар в избранном
$isFavorite = false;
if (!Yii::$app->user->isGuest) {
    $isFavorite = DropFavorite::isFavorite(Yii::$app->user->id, $model->id);
}
?>

<div class="market_drop_item" data-drop-id="<?= $model->id ?>">
    <div class="market_drop_item_header">
    <div class="market_drop_item_image"><?=$image?></div>
        <?php if (!Yii::$app->user->isGuest): ?>
            <button class="market_drop_item_favorite <?= $isFavorite ? 'active' : '' ?>" 
                    type="button" 
                    data-drop-id="<?= $model->id ?>"
                    title="<?= $isFavorite ? Yii::t('common', 'Удалить из избранного') : Yii::t('common', 'Добавить в избранное') ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" 
                          fill="<?= $isFavorite ? '#FFD700' : 'none' ?>" 
                          stroke="<?= $isFavorite ? '#FFD700' : '#666' ?>" 
                          stroke-width="2" 
                          stroke-linejoin="round"/>
                </svg>
            </button>
        <?php endif; ?>
    </div>
    <div class="market_drop_item_name"><?=$model->getShortName()?></div>
    <div class="market_drop_item_quality"><?= Yii::t('database', $model->quality) ?></div>
    <?php $form = ActiveForm::begin(); ?>
    <a class="market_drop_item_btn" href="/market/view?id=<?=$model->id?>" <?=$balance < $model->priceMarket ? 'disabled' : ''?>>
        <span class="market_drop_item_text"><?=Yii::t('common', 'Купить')?></span>
        <span class="market_drop_item_price">
            <span class="currency"><?=$model->currency?></span>
            <span class="price"><?=$model->getPriceMarket()?></span>
        </span>
    </a>
    <?php ActiveForm::end(); ?>
</div>

<?php
// JavaScript для обработки клика по звездочке
$this->registerJs("
    $(document).on('click', '.market_drop_item_favorite', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var \$button = $(this);
        var dropId = \$button.data('drop-id');
        var \$item = \$button.closest('.market_drop_item');
        
        $.ajax({
            url: '/market/toggle-favorite',
            type: 'POST',
            data: {id: dropId},
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (response.isFavorite) {
                        \$button.addClass('active');
                        \$button.find('path').attr('fill', '#FFD700').attr('stroke', '#FFD700');
                        \$button.attr('title', 'Удалить из избранного');
                    } else {
                        \$button.removeClass('active');
                        \$button.find('path').attr('fill', 'none').attr('stroke', '#666');
                        \$button.attr('title', 'Добавить в избранное');
                    }
                    
                    // Показываем уведомление
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message);
                    }
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message);
                    }
                }
            },
            error: function() {
                if (typeof toastr !== 'undefined') {
                    toastr.error('Произошла ошибка при обновлении избранного');
                }
            }
        });
    });
", \yii\web\View::POS_READY);
?>

<style>
.market_drop_item {
    position: relative;
}
.market_drop_item_header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}
.market_drop_item_favorite {
    background: none;
    border: none;
    cursor: pointer;
    padding: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s;
    flex-shrink: 0;
}
.market_drop_item_favorite:hover {
    transform: scale(1.1);
}
.market_drop_item_favorite.active svg path {
    fill: #FFD700;
    stroke: #FFD700;
}
</style>
