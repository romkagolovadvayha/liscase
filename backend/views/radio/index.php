<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */

$this->title = Yii::t('common', 'Управление радио');
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="radio-admin-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row">
        <div class="col-md-6">
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-body">
                    <h3 class="card-title"><?= Yii::t('common', 'Радиостанции') ?></h3>
                    <p class="card-text"><?= Yii::t('common', 'Управление радиостанциями: создание, редактирование, удаление') ?></p>
                    <?= Html::a(
                        Yii::t('common', 'Управление радиостанциями') . ' &raquo;',
                        ['radio/stations'],
                        ['class' => 'btn btn-primary']
                    ) ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-body">
                    <h3 class="card-title"><?= Yii::t('common', 'Треки') ?></h3>
                    <p class="card-text"><?= Yii::t('common', 'Модерация треков: одобрение, отклонение, удаление') ?></p>
                    <?= Html::a(
                        Yii::t('common', 'Модерация треков') . ' &raquo;',
                        ['radio/tracks'],
                        ['class' => 'btn btn-success']
                    ) ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="alert alert-info">
        <i class="fa fa-info-circle"></i> 
        <?= Yii::t('common', 'Здесь вы можете управлять радиостанциями и модерировать загруженные треки.') ?>
    </div>
</div>
