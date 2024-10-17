<?php

use common\models\box\Drop;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

/** @var $items */

$this->title = Yii::t('common', 'Задания');

$data = [];
foreach ($items as $id => $item) {
    $data[] = [
            'id' => $id,
            'title' => $item,
    ];
}
$provider = new \yii\data\ArrayDataProvider([
                                                'allModels' => $data,
                                                'totalCount' => count($data),
                                                'sort' => [
                                                    'attributes' => ['id'],
                                                    'defaultOrder' => ['id' => SORT_ASC],
                                                ],
                                                'pagination' => [
                                                    'pageSize' => 30,
                                                ],
                                            ]);
?>

<?= \kartik\grid\GridView::widget([
                                      'dataProvider' => $provider,
                                      'layout'       => "{items} {pager}",
                                      'columns'      => [
                                          [
                                              'attribute' => 'name',
                                              'label'     => Yii::t('common', "Название"),
                                              'format'    => 'raw',
                                              'value'          => function ($model) {
                                                  return "<a href=\"/task/type?id={$model['id']}\">{$model['title']}</a>";
                                              },
                                          ],
                                      ],
                                  ]);
?>