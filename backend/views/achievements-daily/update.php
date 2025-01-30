<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\achievements\AchievementsDaily $model */

$this->title = 'Update Achievements Daily: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Achievements Dailies', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="wrap800">
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a href="/achievements-daily" class="nav-link">Ежедневные награды</a>
        </li>
        <li class="nav-item">
            <a href="" class="nav-link active"><?=$model->daily?> день</a>
        </li>
    </ul>

    <div class="tab-content">
        <?= $this->render('_form', [
            'model' => $model,
        ]) ?>
    </div>
</div>