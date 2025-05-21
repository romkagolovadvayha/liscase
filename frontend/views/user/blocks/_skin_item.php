<?php

/** @var array $model */
/** @var yii\web\View $this */
/** @var integer $index */
/** @var integer $balance */
/** @var SkinsForm $skinsForm */

use yii\base\BaseObject;
use frontend\forms\user\SkinsForm;
use yii\widgets\Pjax;
use yii\widgets\ActiveForm;
use frontend\widgets\Alert;

?>
<?php $form = ActiveForm::begin(
    [
        'id'                     => 'skin-' . $model['id'],
        'options'                => [
            'data-pjax' => 1,
        ],
    ]
); ?>
<div class="skins_item" <?php if ($model['statTrak']): ?>style="border: 1px solid #CF6A32"<?php endif; ?>>
    <h5 class="skins_item__count-and-img">
        <span class="skins_item__price"><?=number_format($model['price'], 0, '.', ' ')?> <span class="icons icons_16px icons_16px_coin_skins" style="vertical-align: middle;margin-top: -3px;"></span></span>
        <img src="<?=$model['image']?>" alt="<?=$model['name']?>" class="skins_item__img object-contain">
    </h5>
    <p class="skins_item__title" <?php if ($model['statTrak']): ?>style="color: #CF6A32"<?php endif; ?>><?=$model['ru_name']?></p>
    <?php if (!empty($model['ru_quality'])): ?><p class="skins_item__quality"><span class="p3"><?=$model['ru_quality']?></span></p><?php endif; ?>
    <?php if ($balance > $model['price']): ?>
        <div style="display: none"><?=$form->field($skinsForm, 'id')->label(false)->hiddenInput(['value' => $model['id']])?></div>
        <div style="display: none"><?=$form->field($skinsForm, 'amount')->label(false)->hiddenInput(['value' => $model['price']])?></div>
        <button type="submit" class="button-secondary button-size__s h-36 w-full" style="padding-top: 6px; padding-bottom: 6px">
            <span class="button__text"><?=Yii::t('common', 'Получить')?></span>
        </button>
    <?php else: ?>
        <button type="button" class="button-secondary button-size__s h-36 w-full" style="padding-top: 6px; padding-bottom: 6px" disabled>
            <span class="button__text"><?=Yii::t('common', 'Не хватает')?></span>
        </button>
    <?php endif; ?>
</div>
<?php ActiveForm::end(); ?>
