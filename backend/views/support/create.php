<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\support\Support $model */

$this->title = 'Создать обращение';
$this->params['breadcrumbs'][] = ['label' => 'Поддержка', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="support-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
