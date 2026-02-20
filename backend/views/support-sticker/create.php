<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\support\SupportSticker $model */

$this->title = Yii::t('common', 'Создать стикер');
$this->params['breadcrumbs'][] = ['label' => Yii::t('common', 'Стикеры поддержки'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="support-sticker-create">

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>


































