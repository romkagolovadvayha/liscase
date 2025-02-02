<?php

use common\models\site\SiteSetting;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\widgets\Pjax;

/** @var string $category */
/** @var string $title */
/** @var string $setting_items_class */

/** @var \common\models\site\SiteSetting[] $settings */
$settings = SiteSetting::find()
                       ->andWhere(['category' => $category])
                       ->indexBy('id')
                       ->all();

    $itemsClass = 'setting_items';
    if (!empty($setting_items_class)) {
        $itemsClass .= " " . $setting_items_class;
    } else {
        $setting_items_class = null;
    }
?>

<div class="setting_items_wrap_wrap">
    <div class="setting_header">
        <h2><?=!empty($title) ? $title : ''?></h2>
    </div>
    <?php Pjax::begin([
                          'id'              => 'settings-form-pjax-' . $category,
                          'enablePushState' => false
                      ]); ?>

    <?php $form = ActiveForm::begin([
                                        'enableClientValidation' => false,
                                        'enableAjaxValidation'   => false,
                                        'id' => 'settings-form-' . $category,
                                        'options' => ['data-pjax' => true, 'enctype' => 'multipart/form-data'],
                                        'method' => 'post',
                                        'action' => '/settings/form?category=' . $category . '&itemsFlexClass=' . $setting_items_class,
                                    ]); ?>
    <?=\frontend\widgets\Alert::widget()?>
    <div class="setting_items_wrap">
        <div class="<?=$itemsClass?>">
            <?php foreach ($settings as $setting): ?>
                <div class="setting_items_item">
                    <?=$this->render('../fields/' . $setting->type, ['item' => $setting])?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="setting_items_actions">
            <?= Html::submitButton('Сохранить', ['class' => 'btn btn-primary']) ?>
        </div>
        <?php ActiveForm::end(); ?>
        <?php Pjax::end(); ?>
    </div>
</div>
