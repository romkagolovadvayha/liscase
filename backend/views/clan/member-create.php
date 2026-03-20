<?php

use backend\models\ClanMemberAddForm;
use common\models\clan\Clan;
use common\models\clan\ClanMember;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var Clan $clan */
/** @var ClanMemberAddForm $form */

$this->title = Yii::t('common', 'Добавить участника') . ': ' . Html::encode($clan->name);
$this->params['contentClass'] = 'content-no-padding';
?>

<div class="max-w-lg p-4 lg:p-6 text-white">
    <p class="text-sm text-gray-400 mb-4"><?= Yii::t('common', 'Клан') ?>: <strong><?= Html::encode($clan->name) ?></strong> (ID <?= (int)$clan->id ?>)</p>

    <?php $f = ActiveForm::begin(['options' => ['class' => 'space-y-4']]); ?>

    <?= $f->field($form, 'user_id')->input('number', ['class' => 'ds-input form-control', 'min' => 1])
        ->hint(Yii::t('common', 'Числовой ID из профиля пользователя в админке.')) ?>

    <div class="ds-select-wrapper">
        <?= $f->field($form, 'role')->dropDownList([
            ClanMember::ROLE_MEMBER => Yii::t('common', 'Участник'),
            ClanMember::ROLE_OFFICER => Yii::t('common', 'Офицер'),
        ], ['class' => 'ds-select form-control']) ?>
        <i class="fas fa-chevron-down ds-select-arrow"></i>
    </div>

    <div class="flex gap-2 pt-2">
        <?= Html::submitButton(Yii::t('common', 'Добавить'), ['class' => 'ds-btn ds-btn--primary']) ?>
        <?= Html::a(Yii::t('common', 'Отмена'), ['view', 'id' => $clan->id], ['class' => 'ds-btn ds-btn--secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
