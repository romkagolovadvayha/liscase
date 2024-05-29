<?php

use common\models\box\Sets;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

/** @var $dataProvider */
/** @var $searchModel */
/** @var $model Sets */

$this->title = Yii::t('common', 'Сеты');
?>
<style>
    .drops {
        margin-top: 10px;
    }
    .drops_item {
        display: flex;
        gap: 5px;
        margin-top: 5px;
        background: #565656;
        padding: 4px;
        align-items: center;
        color: #fff;
        justify-content: space-between;
    }
    .drops_item_name {
        flex: 1 0;
        line-height: 12px;
        color: #fff;
    }
    .drops_item_count {
        line-height: 12px;
        color: #fff;
    }
    .drops_item_delete {
        font-size: 10px;
        line-height: 12px;
        color: #fff;
    }
    .drops_add {
        font-size: 12px;
        line-height: 12px;
        color: #000;
    }
</style>
<?= Html::a(Yii::t('common', 'Добавить сет'),
    '/sets/create',
    ['class' => 'btn btn-success']); ?>
<div>&nbsp;</div>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel'  => $searchModel,
    'columns'      => [
        [
            'format'    => 'raw',
            'options'   => ['width' => '50'],
            'value'     => function (Sets $model) {
                if (empty($model->imageOrig)) {
                    return null;
                }
                return Html::img($model->imageOrig->getImagePubUrl(), ['width' => '40px']);
            },
        ],
        [
            'attribute' => 'name',
            'format'    => 'raw',
            'options'   => ['width' => '50'],
            'value'     => function (Sets $model) {
              $result = "<div><b>{$model->name}</b></div>";
              $result .= "<div class=\"drops\">";
              $result .= "<a class=\"drops_add\" href=\"/sets-drop/create\">Добавить предмет</a>";
              foreach ($model->setsDrop as $setsDrop) {
                  $result .= "<div class=\"drops_item\">
                        <a class=\"drops_item_name\" href=\"/sets-drop/update?id={$setsDrop->id}\">{$setsDrop->drop->name}</a>
                        <div class=\"drops_item_count\">x{$setsDrop->count}</div>
                        <a class=\"drops_item_delete\" href=\"/sets-drop/delete?id={$setsDrop->id}\">Удалить</a>
                  </div>";
              }
              $result .= "</div>";

              return $result;
            },
        ],
        [
            'attribute' => 'price',
            'options'   => ['width' => '130'],
        ],
        [
            'attribute' => 'discount',
            'options'   => ['width' => '130'],
        ],
        [
            'attribute' => 'status',
            'options'   => ['width' => '130'],
            'filterType'  => GridView::FILTER_SELECT2,
            'filter'    => Sets::getStatusList(),
            'value'     => function (Sets $model) {
                return ArrayHelper::getValue(Sets::getStatusList(), $model->status);
            },
        ],
        [
            'options'   => ['width' => '200'],
            'class' => \common\components\grid\DateColumn::class,
        ],
        [
            'class'    => 'yii\grid\ActionColumn',
            'template' => '{update} {delete}',
            'options'  => ['width' => '45'],
        ],
    ],
]);
?>
