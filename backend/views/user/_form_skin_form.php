<?php

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use backend\forms\userProfile\SkinForm;
use common\models\user\UserBalance;

/* @var $this yii\web\View */
/* @var $childId string */
/* @var $user \common\models\user\User */
/* @var $dataProviderSkins \yii\data\ArrayDataProvider */

?>

<div class="modal-header">
    <h5 class="modal-title">Выберите скин</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть окно"></button>
</div>
<div class="modal-body">
    <?= \kartik\grid\GridView::widget([
                                          'dataProvider' => $dataProviderSkins,
                                          'layout'       => "{items} {pager}",
                                          'columns'      => [
                                              [
                                                  'attribute' => 'image',
                                                  'label'     => Yii::t('common', "Изображение"),
                                                  'format'    => 'raw',
                                                  'value'          => function ($model) {
                                                      return Html::img($model['image'], [
                                                          'width' => 50,
                                                          'height' => 50,
                                                          'alt' => $model['name'] ?? 'Скин',
                                                      ]);
                                                  },
                                              ],
                                              [
                                                  'attribute' => 'name',
                                                  'label'     => Yii::t('common', "Название"),
                                                  'format'    => 'raw',
                                                  'value'          => function ($model) {
                                                      return $model['name'];
                                                  },
                                              ],
                                              [
                                                  'attribute' => 'price',
                                                  'options'   => ['width' => '200'],
                                                  'label'     => Yii::t('common', "Цена"),
                                                  'format'    => 'raw',
                                                  'value'          => function ($model) {
                                                      return $model['price'] . " RUB";
                                                  },
                                              ],
                                          ],
                                      ]);
    ?>
</div>
