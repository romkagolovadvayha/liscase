<?php

use backend\forms\clan\MemberPermissionsForm;
use common\models\clan\Clan;
use common\models\clan\ClanMember;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var Clan $clan */
/** @var ClanMember $member */
/** @var MemberPermissionsForm $form */
/** @var ClanPermission[] $allPermissions */

$this->title = Yii::t('common', 'Права участника') . ': ' . Html::encode($member->user->username ?? ('#' . $member->user_id));
$this->params['contentClass'] = 'content-no-padding';

$list = ArrayHelper::map($allPermissions, 'key', 'name');
?>

<div class="max-w-3xl p-4 lg:p-6 text-white">
    <p class="text-sm text-gray-400 mb-4"><?= Yii::t('common', 'Клан') ?>: <?= Html::encode($clan->name) ?> · <?= Yii::t('common', 'Пользователь') ?>:
        <?= Html::encode($member->user->username ?? ('#' . $member->user_id)) ?></p>

    <?php $f = ActiveForm::begin(['options' => ['class' => 'space-y-4']]); ?>

    <?= $f->field($form, 'permissionKeys')->checkboxList($list, [
        'class' => 'space-y-2 text-gray-200',
        'itemOptions' => ['class' => 'me-2'],
    ])->label(Yii::t('common', 'Разрешения')) ?>

    <div class="flex gap-2 pt-2">
        <?= Html::submitButton(Yii::t('common', 'Сохранить'), ['class' => 'ds-btn ds-btn--primary']) ?>
        <?= Html::a(Yii::t('common', 'Отмена'), ['view', 'id' => $clan->id], ['class' => 'ds-btn ds-btn--secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
