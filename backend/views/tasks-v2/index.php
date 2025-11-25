<?php

use common\models\tasks_v2\TaskV2;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('common', 'Задания v2');
$this->params['breadcrumbs'][] = $this->title;

?>
<div class="tasks-v2-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1><?= Html::encode($this->title) ?></h1>
        <?= Html::a(
            '<i class="fas fa-plus"></i> ' . Yii::t('common', 'Создать задание'),
            ['create'],
            ['class' => 'btn btn-success']
        ) ?>
    </div>

    <?= \frontend\widgets\Alert::widget() ?>

    <div class="card">
        <div class="card-body">
            <div class="mb-3">
                <?= Html::beginForm(['tasks-v2/index'], 'get', ['class' => 'form-inline']) ?>
                
                <div class="form-group me-2">
                    <?= Html::label(Yii::t('common', 'Тип'), 'type') ?>
                    <?= Html::dropDownList(
                        'type',
                        Yii::$app->request->get('type'),
                        ['' => Yii::t('common', 'Все')] + TaskV2::getTypeList(),
                        ['class' => 'form-control', 'id' => 'type']
                    ) ?>
                </div>
                
                <div class="form-group me-2">
                    <?= Html::label(Yii::t('common', 'Статус'), 'is_active') ?>
                    <?= Html::dropDownList(
                        'is_active',
                        Yii::$app->request->get('is_active'),
                        ['' => Yii::t('common', 'Все'), '1' => Yii::t('common', 'Активные'), '0' => Yii::t('common', 'Неактивные')],
                        ['class' => 'form-control', 'id' => 'is_active']
                    ) ?>
                </div>
                
                <div class="form-group me-2">
                    <?= Html::label(Yii::t('common', 'Поиск'), 'search') ?>
                    <?= Html::textInput(
                        'search',
                        Yii::$app->request->get('search'),
                        ['class' => 'form-control', 'id' => 'search', 'placeholder' => Yii::t('common', 'Название...')]
                    ) ?>
                </div>
                
                <?= Html::submitButton(Yii::t('common', 'Применить'), ['class' => 'btn btn-primary me-2']) ?>
                <?= Html::a(Yii::t('common', 'Сбросить'), ['tasks-v2/index'], ['class' => 'btn btn-secondary']) ?>
                
                <?= Html::endForm() ?>
            </div>

            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'columns' => [
                    ['class' => 'kartik\grid\SerialColumn'],
                    
                    [
                        'attribute' => 'id',
                        'label' => 'ID',
                        'width' => '60px',
                    ],
                    
                    [
                        'attribute' => 'title',
                        'label' => Yii::t('common', 'Название'),
                        'format' => 'raw',
                        'value' => function ($model) {
                            $image = $model->image_path 
                                ? Html::img('/' . ltrim($model->image_path, '/'), ['style' => 'width: 40px; height: 40px; object-fit: cover; border-radius: 4px; margin-right: 8px;'])
                                : '';
                            return $image . Html::encode($model->title);
                        },
                    ],
                    
                    [
                        'attribute' => 'type',
                        'label' => Yii::t('common', 'Тип'),
                        'value' => function ($model) {
                            return TaskV2::getTypeList()[$model->type] ?? $model->type;
                        },
                    ],
                    
                    [
                        'attribute' => 'check_type',
                        'label' => Yii::t('common', 'Тип проверки'),
                        'value' => function ($model) {
                            return TaskV2::getCheckTypeList()[$model->check_type] ?? $model->check_type;
                        },
                    ],
                    
                    [
                        'attribute' => 'reward_type',
                        'label' => Yii::t('common', 'Награда'),
                        'format' => 'raw',
                        'value' => function ($model) {
                            if ($model->reward_type === TaskV2::REWARD_TYPE_CURRENCY) {
                                return '<i class="fas fa-coins"></i> ' . number_format($model->reward_amount, 0, '.', ' ') . ' ' . ($model->reward_currency ?? 'RUB');
                            } elseif ($model->reward_type === TaskV2::REWARD_TYPE_ITEM && $model->rewardItem) {
                                return '<img src="' . $model->rewardItem->imageOrig->getImagePubUrl() . '" style="width: 24px; height: 24px; object-fit: cover; border-radius: 4px; margin-right: 4px;"> ' . Yii::t('database', $model->rewardItem->name);
                            }
                            return '-';
                        },
                    ],
                    
                    [
                        'attribute' => 'global_completed',
                        'label' => Yii::t('common', 'Выполнено'),
                        'width' => '100px',
                    ],
                    
                    [
                        'attribute' => 'is_active',
                        'label' => Yii::t('common', 'Активно'),
                        'format' => 'raw',
                        'value' => function ($model) {
                            return $model->is_active
                                ? '<span class="badge bg-success">' . Yii::t('common', 'Да') . '</span>'
                                : '<span class="badge bg-secondary">' . Yii::t('common', 'Нет') . '</span>';
                        },
                        'width' => '100px',
                    ],
                    
                    [
                        'attribute' => 'sort',
                        'label' => Yii::t('common', 'Сортировка'),
                        'width' => '80px',
                    ],
                    
                    [
                        'class' => 'kartik\grid\ActionColumn',
                        'header' => Yii::t('common', 'Действия'),
                        'template' => '{update} {toggle-active} {delete}',
                        'buttons' => [
                            'toggle-active' => function ($url, $model) {
                                return Html::a(
                                    '<i class="fas fa-toggle-' . ($model->is_active ? 'on' : 'off') . '"></i>',
                                    $url,
                                    [
                                        'title' => Yii::t('common', $model->is_active ? 'Деактивировать' : 'Активировать'),
                                        'class' => 'btn btn-sm btn-outline-secondary',
                                    ]
                                );
                            },
                        ],
                        'urlCreator' => function ($action, $model) {
                            if ($action === 'toggle-active') {
                                return Url::to(['tasks-v2/toggle-active', 'id' => $model->id]);
                            }
                            return Url::to(['tasks-v2/' . $action, 'id' => $model->id]);
                        },
                    ],
                ],
                'responsive' => true,
                'hover' => true,
                'export' => false,
                'panel' => [
                    'type' => GridView::TYPE_PRIMARY,
                    'heading' => '<i class="fas fa-tasks"></i> ' . Yii::t('common', 'Задания v2'),
                ],
            ]) ?>
        </div>
    </div>
</div>

