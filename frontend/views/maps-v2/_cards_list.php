<?php

use yii\widgets\Pjax;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use frontend\widgets\Alert;

/** @var \common\models\map\MapList[] $maps */
/** @var array $cardsHtml */
/** @var \common\models\servers\Servers $server */

$pjaxId = 'maps-v2-cards-pjax';

?>

<?php Pjax::begin([
    'id' => $pjaxId,
    'enablePushState' => false,
    'timeout' => 5000
]); ?>

<?= Alert::widget() ?>

<?php $form = ActiveForm::begin([
    'id' => 'vote-form',
    'action' => '/maps-v2/vote',
    'method' => 'post',
    'enableClientValidation' => false,
    'enableAjaxValidation' => false,
    'options' => [
        'data-pjax' => 1,
    ],
]); ?>

<?= Html::hiddenInput('server_id', $server->id) ?>

<div class="mapsV2__cards" data-role="map-list">
    <?php foreach ($maps as $map): ?>
        <?= $cardsHtml[$map->id] ?? '' ?>
    <?php endforeach; ?>
</div>

<?php ActiveForm::end(); ?>

<?php Pjax::end(); ?>

