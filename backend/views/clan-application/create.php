<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\clan\ClanApplication $model */

$this->title = 'Create Clan Application';
$this->params['breadcrumbs'][] = ['label' => 'Clan Applications', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="clan-application-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
