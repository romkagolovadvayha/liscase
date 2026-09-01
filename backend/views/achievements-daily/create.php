<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\achievements\AchievementsDaily $model */

$this->title = 'Добавить ежедневную награду';
$this->params['breadcrumbs'][] = ['label' => 'Ежедневные награды', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<?= $this->render('_form', [
    'model' => $model,
]) ?>
