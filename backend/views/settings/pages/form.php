<?php

use common\models\site\SiteSetting;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;
use yii\widgets\Pjax;

/** @var string $category */
/** @var string $setting_items_class */

$settings = SiteSetting::find()
    ->andWhere(['category' => $category])
    ->indexBy('id')
    ->all();

$itemsClass = 'setting_items';
if (!empty($setting_items_class)) {
    $itemsClass .= ' ' . $setting_items_class;
} else {
    $setting_items_class = null;
}

$sectionClass = 'bg-[hsl(0_0%_11.8%_/_1)] overflow-hidden';
$sectionHeaderClass = 'px-4 py-3 text-xs font-semibold text-zinc-400 uppercase tracking-wider border-b border-[hsl(0_0%_15.3%_/_1)]';
?>

<div class="settings-form-wrap w-full">
    <?php Pjax::begin([
        'id' => 'settings-form-pjax-' . $category,
        'enablePushState' => false,
    ]); ?>

    <?php $form = ActiveForm::begin([
        'enableClientValidation' => false,
        'enableAjaxValidation' => false,
        'id' => 'settings-form-' . $category,
        'options' => ['data-pjax' => true, 'enctype' => 'multipart/form-data', 'class' => 'settings-form'],
        'method' => 'post',
        'action' => Url::to(['/settings/form', 'category' => $category, 'itemsFlexClass' => $setting_items_class]),
    ]); ?>
    <?= \frontend\widgets\Alert::widget() ?>

    <div class="<?= $sectionClass ?>">
        <h3 class="<?= $sectionHeaderClass ?>"><?= Yii::t('common', 'Параметры') ?></h3>
        <div class="p-4">
            <div class="<?= $itemsClass ?> grid grid-cols-1 lg:grid-cols-2 gap-4">
                <?php foreach ($settings as $setting): ?>
                    <div class="setting_items_item">
                        <?= $this->render('../fields/' . $setting->type, ['item' => $setting]) ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-6 pt-4 border-t border-[hsl(0_0%_15.3%_/_1)]">
                <?= Html::submitButton(Yii::t('common', 'Сохранить'), ['class' => 'ds-btn ds-btn--primary']) ?>
            </div>
        </div>
    </div>
    <?php ActiveForm::end(); ?>
    <?php Pjax::end(); ?>
</div>
