<?php

use common\models\avatar\AvatarFrame;
use yii\grid\GridView;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var backend\models\avatar\AvatarFrameSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Рамки аватаров';
?>

<div class="p-4">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'tableOptions' => ['class' => 'table table-dark table-striped table-hover'],
        'columns' => [
            'id',
            'name',
            [
                'attribute' => 'image_key',
                'format' => 'raw',
                'value' => static function (AvatarFrame $model): string {
                    $url = $model->getImageUrl();
                    if (!$url) {
                        return Html::tag('span', '—', ['class' => 'text-muted']);
                    }
                    return Html::img($url, ['style' => 'width:52px;height:52px;object-fit:contain;background:#101214;border-radius:8px;padding:2px;'])
                        . '<div class="small text-muted mt-1">' . Html::encode($model->image_key) . '</div>';
                },
            ],
            'sort',
            [
                'attribute' => 'is_active',
                'format' => 'raw',
                'value' => static fn(AvatarFrame $model): string => $model->is_active
                    ? '<span class="badge bg-success">Активна</span>'
                    : '<span class="badge bg-secondary">Отключена</span>',
                'filter' => [1 => 'Да', 0 => 'Нет'],
            ],
            [
                'class' => yii\grid\ActionColumn::class,
                'template' => '{update} {delete}',
            ],
        ],
    ]) ?>
</div>

