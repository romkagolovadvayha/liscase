<?php

/** @var array $model */
/** @var yii\web\View $this */
/** @var integer $index */
/** @var integer $balance */
/** @var string $type */

use yii\helpers\Url;

?>
<?php if ($balance > $model['price']): ?>
    <div class="skins_item available show-modal-link" 
         data-id="<?=$model['id']?>" 
         data-href="<?=Url::to(['/user/skin-confirm', 'id' => $model['id'], 'type' => $type])?>"
         data-size="modal-sm"
         data-toggl="modal"
         data-target="modal-dialog"
         data-title="<?=Yii::t('common', 'Подтверждение покупки')?>"
         <?php if ($model['statTrak']): ?>style="border: 1px solid #CF6A32"<?php endif; ?>>
<?php else: ?>
    <div class="skins_item disabled" data-id="<?=$model['id']?>" <?php if ($model['statTrak']): ?>style="border: 1px solid #CF6A32"<?php endif; ?>>
<?php endif; ?>
    <img src="<?=$model['image']?>" alt="<?=$model['name']?>" class="skins_item__img">
    <p class="skins_item__title" <?php if ($model['statTrak']): ?>style="color: #CF6A32"<?php endif; ?>><?=$model['ru_name']?></p>
    <div class="skins_item__footer">
        <?php 
        // Для Rust показываем переведенный ru_quality (тип предмета), для CS2 - ru_quality (качество)
        $qualityDisplay = '';
        if (!empty($model['ru_quality'])) {
            if ($type == 'rust') {
                // Для Rust используем справочник переводов
                $qualityDisplay = \common\components\rusttm\RustTm::translateItemType($model['ru_quality']);
            } else {
                // Для CS2 показываем как есть
                $qualityDisplay = $model['ru_quality'];
            }
        }
        if ($qualityDisplay): ?>
            <p class="skins_item__quality"><?=htmlspecialchars($qualityDisplay)?></p>
        <?php else: ?>
            <div></div>
        <?php endif; ?>
        <div class="skins_item__price">
            <?=number_format($model['price'], 0, '.', ' ')?>
            <span class="icons icons_16px icons_16px_coin_skins"></span>
        </div>
    </div>
</div>
