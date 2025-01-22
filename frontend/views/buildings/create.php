<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var \frontend\forms\buildings\BuildingForm $model */

$this->title = Yii::t('common', 'Новая постройка');
?>
<div class="server_info_page">
    <div class="main_child">
        <?= $this->render('_form', [
            'model' => $model,
        ]) ?>
    </div>
</div>