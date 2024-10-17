<?php

use common\models\box\Drop;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use common\models\tasks\Task;

/** @var $items Task[] */
/** @var $id */

$this->title = Yii::t('common', 'Задания');

$provider = new \yii\data\ArrayDataProvider([
                                                        'allModels' => $items,
                                                        'totalCount' => count($items),
                                                        'sort' => [
                                                            'attributes' => ['sort'],
                                                            'defaultOrder' => ['sort' => SORT_ASC],
                                                        ],
                                                        'pagination' => [
                                                            'pageSize' => 30,
                                                        ],
                                                    ]);
?>

<?= Html::a(Yii::t('common', 'Сортировать'),
            '/task/sort?id=' . $id,
            ['class' => 'btn btn-primary']); ?>

<div class="mt-3">
    <?= \kartik\grid\GridView::widget([
                                          'dataProvider' => $provider,
                                          'layout'       => "{items} {pager}",
                                          'columns'      => [
                                              [
                                                  'attribute' => 'name',
                                                  'label'     => Yii::t('common', "Название"),
                                                  'format'    => 'raw',
                                                  'value'          => function (Task $model) {
                                                      return "{$model->description}({$model->amount})";
                                                  },
                                              ],
                                              [
                                                  'attribute' => 'bonus',
                                                  'label'     => Yii::t('common', "Награда"),
                                                  'format'    => 'raw',
                                                  'value'          => function (Task $model) {
                                                      return "{$model->drop->name}({$model->count})";
                                                  },
                                              ],
                                              [
                                                  'attribute' => 'stat_attribute',
                                                  'label'     => Yii::t('common', "Статистика"),
                                                  'format'    => 'raw',
                                                  'value'          => function (Task $model) {
                                                      return "{$model->stat_attribute}";
                                                  },
                                              ],
                                              [
                                                  'attribute' => 'actions',
                                                  'label'     => '',
                                                  'format'    => 'raw',
                                                  'value'          => function (Task $model) {
                                                      return "<a href=\"/task/update?id={$model->id}\">Изменить</a>";
                                                  },
                                              ],
                                          ],
                                      ]);
    ?>
</div>