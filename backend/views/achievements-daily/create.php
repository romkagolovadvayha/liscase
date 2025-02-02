<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\achievements\AchievementsDaily $model */

$this->title = 'Create Achievements Daily';
$this->params['breadcrumbs'][] = ['label' => 'Achievements Dailies', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<?= $this->render('_form', [
    'model' => $model,
]) ?>