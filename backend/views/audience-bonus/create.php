<?php

use yii\helpers\Html;

/** @var yii\web\View $this */

$this->title = 'Создать начисление бонуса аудитории';
$this->params['breadcrumbs'][] = ['label' => 'Бонусы аудитории', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="audience-bonus-create-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <div class="ds-card">
            <?= $this->render('_form') ?>
        </div>
    </div>
</div>

