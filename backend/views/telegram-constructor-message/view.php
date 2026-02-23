<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var $this yii\web\View */
/** @var $model backend\models\TelegramConstructorMessage */

$this->title = $model->title ?: 'Сообщение #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Конструктор рассылок', 'url' => ['/telegram-constructor']];
$this->params['breadcrumbs'][] = ['label' => 'Сообщения для рассылок', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
$this->params['contentClass'] = 'content-no-padding';
\yii\web\YiiAsset::register($this);
?>
<div class="tcm-view-page p-4 lg:p-6">
    <div class="flex flex-wrap gap-2 mb-4">
        <?= Html::a('<i class="fas fa-pencil-alt"></i> Изменить', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--primary']) ?>
        <?= Html::a('<i class="fas fa-trash"></i> Удалить', ['delete', 'id' => $model->id], [
            'class' => 'ds-btn ds-btn--danger',
            'data' => [
                'confirm' => 'Удалить это сообщение?',
                'method' => 'post',
            ],
        ]) ?>
        <?= Html::a('<i class="fas fa-arrow-left"></i> К списку', ['index'], ['class' => 'ds-btn ds-btn--secondary']) ?>
    </div>

    <?= DetailView::widget([
        'model' => $model,
        'options' => ['class' => 'table table-borderless tcm-detail-view'],
        'attributes' => [
            'id',
            'title',
            [
                'attribute' => 'image_link',
                'format' => 'raw',
                'value' => $model->getPubUrl() ? Html::img($model->getPubUrl(), ['style' => 'max-width: 200px; height: auto; border-radius: 4px;']) : '—',
            ],
            'created_at:datetime',
        ],
    ]) ?>
</div>

<style>
.tcm-detail-view { color: #e5e7eb; }
.tcm-detail-view th { width: 180px; padding: 0.5rem 0.75rem 0.5rem 0; font-weight: 600; color: hsl(0 0% 70%); vertical-align: top; }
.tcm-detail-view td { padding: 0.5rem 0; border-bottom: 1px solid hsl(0 0% 18% / 1); }
</style>
