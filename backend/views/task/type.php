<?php

use common\models\box\Drop;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use common\models\tasks\Task;

/** @var $items Task[] */
/** @var $id */
/** @var $tasks */

$this->title = Yii::t('common', 'Задания');

$data = [];
foreach ($tasks as $_id => $item) {
    $data[] = [
        'id' => $_id,
        'title' => $item,
    ];
}

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

<div class="wrap800">
    <ul class="nav nav-tabs">
        <?php foreach ($data as $item): ?>
            <li class="nav-item">
                <a href="/task/type?id=<?=$item['id']?>" class="nav-link<?php if ($item['id'] == $id): ?> active<?php endif; ?>"><?=$item['title']?></a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">
        <?= \kartik\grid\GridView::widget([
                                              'dataProvider' => $provider,
                                              'layout'       => "{items} {pager}",
                                              'columns'      => [
                                                  [
                                                      'attribute' => 'name',
                                                      'label'     => Yii::t('common', "Награда"),
                                                      'format'    => 'raw',
                                                      'value'          => function (Task $model) {
                                                          return "Задание: {$model->description}<br/>Награда: {$model->drop->name}";
                                                      },
                                                  ],
                                                  [
                                                      'attribute' => 'count',
                                                      'label'     => Yii::t('common', "Количество"),
                                                      'options'   => ['width' => '80'],
                                                      'format'    => 'raw',
                                                      'value'          => function (Task $model) {
                                                          return "x{$model->amount}<br/>x{$model->count}";
                                                      },
                                                  ],
                                                  [
                                                      'attribute' => 'buttons',
                                                      'label'     => '',
                                                      'options'   => ['width' => '30'],
                                                      'format'    => 'raw',
                                                      'value'          => function (Task $model) {
                                                          return Html::a('<i class="fas fa-pencil-alt"></i>', '/task/update?id=' . $model->id);
                                                      },
                                                  ],
                                              ],
                                          ]);
        ?>
        <?= Html::a(Yii::t('common', 'Сортировать'),
                    '/task/sort?id=' . $id,
                    ['class' => 'btn btn-primary']); ?>
    </div>
</div>