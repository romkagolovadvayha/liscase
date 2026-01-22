<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\support\SupportSticker $model */

$this->title = Yii::t('common', 'Изменить стикер: {name}', ['name' => $model->name]);
$this->params['breadcrumbs'][] = ['label' => Yii::t('common', 'Стикеры поддержки'), 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->name, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = Yii::t('common', 'Изменить');
?>
<div class="support-sticker-update">

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>









