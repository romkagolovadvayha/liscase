<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var \frontend\forms\buildings\BuildingForm $model */
/** @var \common\models\servers\Servers $server */

$this->title = Yii::t('common', 'Новая постройка');
?>
<div class="server_info_page">
    <div class="main_child">
        <?= $this->render('_form', [
            'model' => $model,
            'server' => $server,
        ]) ?>
    </div>
</div>