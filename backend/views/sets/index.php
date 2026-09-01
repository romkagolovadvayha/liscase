<?php

use common\models\box\Sets;
use backend\components\AccessibleKartikGridView as GridView;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\grid\ActionColumn;

/** @var $dataProvider */
/** @var $searchModel \common\models\box\SetsSearch */
/** @var $model Sets */

$this->title = Yii::t('common', 'Сеты');
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';
?>
<style>
.drops { margin-top: 10px; }
.drops_item { display: flex; gap: 5px; margin-top: 5px; background: hsl(0 0% 20% / 1); padding: 4px; align-items: center; color: #fff; justify-content: space-between; border-radius: 4px; }
.drops_item_name { flex: 1 0; line-height: 12px; color: #fff; }
.drops_item_count { line-height: 12px; color: #fff; }
.drops_item_delete { font-size: 10px; line-height: 12px; color: #fff; }
.drops_add { font-size: 12px; line-height: 12px; color: hsl(200 70% 60%); }
</style>
<div class="sets-index-page w-full">
    <div class="w-full">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'filterModel' => $searchModel,
            'tableOptions' => ['class' => 'table-auto w-full text-sm'],
            'options' => ['class' => 'admin-grid-view-dark'],
            'layout' => "{items}\n{pager}",
            'filterRowOptions' => ['style' => 'display: none;'],
            'bordered' => false,
            'striped' => false,
            'hover' => true,
            'columns' => [
                [
                    'format' => 'raw',
                    'options' => ['width' => '50'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (Sets $model) {
                        if (empty($model->imageOrig)) return null;
                        return Html::img($model->imageOrig->getImagePubUrl(false), ['width' => '40px']);
                    },
                ],
                [
                    'attribute' => 'name',
                    'format' => 'raw',
                    'options' => ['width' => '50'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (Sets $model) {
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
                ['attribute' => 'price', 'options' => ['width' => '130'], 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                ['attribute' => 'discount', 'options' => ['width' => '130'], 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                [
                    'attribute' => 'status',
                    'options' => ['width' => '130'],
                    'filterType' => GridView::FILTER_SELECT2,
                    'filter' => Sets::getStatusList(),
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (Sets $model) {
                        return ArrayHelper::getValue(Sets::getStatusList(), $model->status);
                    },
                ],
                [
                    'attribute' => 'created_at',
                    'options' => ['width' => '200'],
                    'class' => \common\components\grid\DateColumn::class,
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'class' => ActionColumn::class,
                    'template' => '{update} {delete}',
                    'options' => ['width' => '45'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'urlCreator' => function ($action, $model, $key, $index, $column) {
                        return Url::toRoute([$action, 'id' => $model->id]);
                    },
                ],
            ],
        ]); ?>
    </div>
</div>
