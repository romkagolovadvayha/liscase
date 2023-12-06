<?php

use yii\widgets\ListView;

/** @var yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::$app->name . ' - ' . Yii::t('common', 'Лучшие CSGO кейсы');

?>
<main id="main" role="main">
    <div class="content_wrapper container">
        <div class="content">
            <?= ListView::widget([
                'id'           => 'package-list-view',
                'dataProvider' => $dataProvider,
                'layout'       => "{items}{pager}",
                'itemView'     => '../blog/_item',
            ]) ?>
        </div>
        <div class="side"></div>
    </div>
</main>