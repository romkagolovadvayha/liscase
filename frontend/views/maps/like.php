<?php

use yii\bootstrap5\ActiveForm;
use frontend\widgets\Alert;
use yii\widgets\Pjax;
use yii\helpers\Url;

/** @var \common\models\map\Map $model */
/** @var bool $liked */

?>

<?php Pjax::begin(
    [
        'id'              => 'like-pjax' . $model->id,
        'enablePushState' => false
    ]
); ?>
<?php $form = ActiveForm::begin(
    [
        'enableClientValidation' => false,
        'enableAjaxValidation'   => false,
        'id'                     => 'like_' . $model->id,
        'action' => Url::to(['/maps/vote', 'id' => $model->id]),
        'options'                => [
            'data-pjax' => 1,
        ],
    ]
); ?>
<?= Alert::widget() ?>
<button type="submit" class="maps_content_list_item_footer_like<?=$liked ? ' active' : ''?>" data-map-id="<?= $model->id ?>">
    <?php if ($model->votes > 0): ?>
        <span class="maps_content_list_item_footer_like_count"><?=$model->votes?></span>
    <?php endif; ?>
    <div class="maps_content_list_item_footer_like_icon">
        <i class="icon_active fa-solid fa-heart"></i>
        <i class="icon_noactive fa-regular fa-heart"></i>
    </div>
</button>
<?php ActiveForm::end(); ?>
<?php Pjax::end(); ?>