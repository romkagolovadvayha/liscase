<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use yii\helpers\Json;
use common\models\bonus\AudienceBonus;

/** @var yii\web\View $this */
/** @var common\models\bonus\AudienceBonus $model */

$this->title = 'Начисление бонуса #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Бонусы аудитории', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="audience-bonus-view-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <div class="ds-card">
            <?= DetailView::widget([
                'model' => $model,
                'attributes' => [
                    'id',
                    [
                        'attribute' => 'audience_type',
                        'value' => $model->getAudienceTypeName(),
                    ],
                    [
                        'attribute' => 'parameters_json',
                        'label' => 'Параметры',
                        'format' => 'raw',
                        'value' => function ($model) {
                            $params = $model->getParameters();
                            if (empty($params)) {
                                return '-';
                            }
                            return '<pre>' . Html::encode(Json::encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                        },
                    ],
                    [
                        'attribute' => 'message_template',
                        'format' => 'ntext',
                    ],
                    [
                        'attribute' => 'test_user_ids',
                        'label' => 'Тестовые пользователи',
                        'format' => 'raw',
                        'value' => function ($model) {
                            $testUserIds = $model->getTestUserIds();
                            if (empty($testUserIds)) {
                                return '-';
                            }
                            return Html::tag('span', 'Да (' . count($testUserIds) . ' пользователей)', ['class' => 'ds-badge ds-badge--warning'])
                                . '<br><small>' . Html::encode(implode(', ', $testUserIds)) . '</small>';
                        },
                    ],
                    [
                        'attribute' => 'total_users',
                        'label' => 'Количество пользователей',
                    ],
                    [
                        'attribute' => 'total_amount',
                        'label' => 'Общая сумма',
                        'value' => function ($model) {
                            return number_format($model->total_amount, 2, '.', ' ') . ' РУБ';
                        },
                    ],
                    [
                        'attribute' => 'created_at',
                        'format' => 'datetime',
                    ],
                    [
                        'attribute' => 'created_by',
                        'label' => 'Создал',
                        'format' => 'raw',
                        'value' => function ($model) {
                            if ($model->createdBy) {
                                return Html::encode($model->createdBy->username);
                            }
                            return '-';
                        },
                    ],
                ],
            ]) ?>
        </div>
    </div>
</div>

