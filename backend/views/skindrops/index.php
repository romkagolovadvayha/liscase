<?php

use yii\base\BaseObject;
use yii\web\View;
use common\models\user\UserDrop;
use yii\widgets\ActiveForm;
use frontend\widgets\Alert;

/** @var View $this */

$user = Yii::$app->user->identity;
$this->title = Yii::t('common', "Скиндропс");

$list = Yii::$app->rustTm->history()['data'];
$dataProvider = new \yii\data\ArrayDataProvider([
                          'allModels' => $list,
                          'totalCount' => count($list),
                          'pagination' => [
                              'pageSize' => 20,
                          ],
]);

?>

<?= Alert::widget(); ?>
<?= \kartik\grid\GridView::widget([
                                      'dataProvider' => $dataProvider,
                                      'layout'       => "{items} {pager}",
                                      'columns'      => [
                                          [
                                              'attribute' => 'market_hash_name',
                                              'label'     => Yii::t('common', "Название"),
                                              'format'    => 'raw',
                                              'value'          => function ($model) {
                                                  return $model['market_hash_name'];
                                              },
                                          ],
                                          [
                                              'attribute' => 'stage',
                                              'options'   => ['width' => '150'],
                                              'label'     => Yii::t('common', "Статус"),
                                              'format'    => 'raw',
                                              'value'          => function ($model) {
                                                  $status = "Отправлен";
                                                  if ($model['stage'] == 5) {
                                                      $status = "Таймаут";
                                                  }
                                                  if ($model['stage'] == 2) {
                                                      $status = "Получен";
                                                  }
                                                  return $status;
                                              },
                                          ],
                                          [
                                              'attribute' => 'paid',
                                              'options'   => ['width' => '150'],
                                              'label'     => Yii::t('common', "Сумма"),
                                              'format'    => 'raw',
                                              'value'          => function ($model) {
                                                  return ($model['paid'] / 100) . " RUB";
                                              },
                                          ],
                                          [
                                              'attribute' => 'time',
                                              'options'   => ['width' => '150'],
                                              'label'     => Yii::t('common', "Дата"),
                                              'format'    => 'raw',
                                              'value'          => function ($model) {
                                                  return gmdate("Y-m-d H:i:s", $model['time']);
                                              },
                                          ],
                                          [
                                              'attribute' => 'replay',
                                              'options'   => ['width' => '150'],
                                              'label'     => Yii::t('common', "Отправить повторно"),
                                              'format'    => 'raw',
                                              'value'          => function ($model) {
                                                  if ($model['stage'] == 5) {
                                                      return \yii\helpers\Html::a('Отправить повторно', \yii\helpers\Url::to(['/skindrops/buy', 'name' => $model['market_hash_name'], 'price' => $model['paid'] + 1000, 'partner' => $model['for']]));
                                                  }
                                                  if ($model['stage'] == 2) {
                                                      return '';
                                                  }
                                                  return 'Ждем';
                                              },
                                          ],
                                      ],
                                  ]);
?>