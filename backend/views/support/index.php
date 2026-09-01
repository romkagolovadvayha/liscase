<?php

use common\models\support\Support;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use backend\components\AccessibleGridView as GridView;
use yii\widgets\Pjax;
/** @var yii\web\View $this */
/** @var backend\models\support\SupportSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Поддержка';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="support-index-page">
    <div class="content-header">
        <div class="ds-flex ds-flex--between">
            <h1><?= Html::encode($this->title) ?></h1>
            <?= Html::a('<i class="fas fa-plus"></i> Создать тикет', ['create'], ['class' => 'ds-btn ds-btn--success']) ?>
        </div>
    </div>

    <div class="content">
        <div class="ds-card">
            <?php Pjax::begin(); ?>
            <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            [
                'attribute' => 'user_id',
                'format' => 'raw',
                'value' => function (Support $model) {
                    if (!$model->user) {
                        return Html::encode($model->user_id);
                    }
                    $avatar = $model->user->getAvatar();
                    $avatarHtml = '';
                    if ($avatar) {
                        $avatarHtml = Html::img($avatar, [
                            'alt' => '',
                            'style' => 'width: 32px; height: 32px; border-radius: 50%; object-fit: cover; vertical-align: middle; margin-right: 8px;',
                        ]);
                    }
                    $url = Url::to('/profile/' . $model->user->id);
                    return $avatarHtml . Html::a(Html::encode($model->user->username), $url, [
                        'class' => 'ds-text--primary',
                        'style' => 'text-decoration: none; vertical-align: middle;'
                    ]);
                },
            ],
            [
                'attribute' => 'name',
                'value' => static function (Support $model): string {
                    return trim((string)$model->name) !== '' ? (string)$model->name : 'Без темы';
                },
            ],
            [
                'attribute' => 'status',
                'format' => 'raw',
                'filter' => Support::getStatusList(),
                'value' => static function (Support $model): string {
                    $label = Support::getStatusList()[$model->status] ?? 'Неизвестно';
                    $modifier = (int)$model->status === Support::STATUS_OPEN ? 'success' : 'secondary';
                    return Html::tag('span', Html::encode($label), ['class' => 'ds-badge ds-badge--' . $modifier]);
                },
            ],
            [
                'attribute' => 'created_at',
                'format' => ['datetime', 'php:d.m.Y H:i'],
            ],
            [
                'class' => ActionColumn::className(),
                'urlCreator' => function ($action, Support $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                 }
            ],
        ],
    ]); ?>

            <?php Pjax::end(); ?>
        </div>
    </div>
</div>
