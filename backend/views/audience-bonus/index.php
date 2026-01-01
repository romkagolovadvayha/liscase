<?php

use common\models\bonus\AudienceBonus;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use kartik\grid\GridView;
use yii\helpers\ArrayHelper;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Бонусы аудитории';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="audience-bonus-index-page">
    <div class="content-header">
        <div class="ds-flex ds-flex--between">
            <h1><?= Html::encode($this->title) ?></h1>
            <?= Html::a('<i class="fas fa-plus"></i> Создать начисление', ['create'], ['class' => 'ds-btn ds-btn--primary']) ?>
        </div>
    </div>

    <div class="content">
        <div class="ds-card">
            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'columns' => [
                    [
                        'attribute' => 'id',
                        'options' => ['width' => '60']
                    ],
                    [
                        'attribute' => 'audience_type',
                        'options' => ['width' => '150'],
                        'value' => function (AudienceBonus $model) {
                            return $model->getAudienceTypeName();
                        },
                    ],
                    [
                        'attribute' => 'total_users',
                        'options' => ['width' => '120'],
                        'label' => 'Пользователей',
                    ],
                    [
                        'attribute' => 'total_amount',
                        'options' => ['width' => '150'],
                        'label' => 'Общая сумма',
                        'value' => function (AudienceBonus $model) {
                            return number_format($model->total_amount, 2, '.', ' ') . ' РУБ';
                        },
                    ],
                    [
                        'attribute' => 'isTest',
                        'label' => 'Тестовое',
                        'options' => ['width' => '100'],
                        'format' => 'raw',
                        'value' => function (AudienceBonus $model) {
                            if ($model->isTest()) {
                                return Html::tag('span', 'Да', ['class' => 'ds-badge ds-badge--warning']);
                            }
                            return Html::tag('span', 'Нет', ['class' => 'ds-badge ds-badge--success']);
                        },
                    ],
                    [
                        'attribute' => 'created_at',
                        'options' => ['width' => '150'],
                        'format' => 'datetime',
                    ],
                    [
                        'attribute' => 'created_by',
                        'format' => 'raw',
                        'value' => function (AudienceBonus $model) {
                            if ($model->createdBy) {
                                return Html::encode($model->createdBy->username);
                            }
                            return '-';
                        },
                    ],
                    [
                        'class' => ActionColumn::className(),
                        'urlCreator' => function ($action, AudienceBonus $model, $key, $index, $column) {
                            return Url::toRoute([$action, 'id' => $model->id]);
                        },
                        'template' => '{view}',
                        'options' => ['width' => '60'],
                    ],
                ],
            ]) ?>
        </div>
    </div>
</div>

