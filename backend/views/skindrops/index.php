<?php

use common\models\skindrops\SkindropsLink;
use yii\base\BaseObject;
use yii\web\View;
use common\models\user\UserDrop;
use yii\widgets\ActiveForm;
use frontend\widgets\Alert;
use common\models\user\User;

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
                                              'attribute' => 'username',
                                              'options'   => ['width' => '150'],
                                              'label'     => Yii::t('common', "Ник"),
                                              'format'    => 'raw',
                                              'value'          => function ($model) {
                                                  if (empty($model['for'])) {
                                                      return null;
                                                  }
                                                  /** @var User[] $users */
                                                  $users = User::find()
                                                              ->alias('u')
                                                              ->joinWith(['userProfile up'])
                                                              ->andWhere(['LIKE', 'up.trade_link', '%partner=' . $model['for'] . '%', false])
                                                              ->all();
                                                  if (empty($users)) {
                                                      return null;
                                                  }
                                                  $result = "";
                                                  foreach ($users as $user) {
                                                      $result .= $user->username . "<Br/>";
                                                  }
                                                  return $result;
                                              },
                                          ],
                                          [
                                              'attribute' => 'steamId',
                                              'options'   => ['width' => '150'],
                                              'label'     => Yii::t('common', "Steam ID"),
                                              'format'    => 'raw',
                                              'value'          => function ($model) {
                                                  if (empty($model['for'])) {
                                                      return null;
                                                  }
                                                  /** @var User[] $users */
                                                  $users = User::find()
                                                               ->alias('u')
                                                               ->joinWith(['userProfile up'])
                                                               ->andWhere(['LIKE', 'up.trade_link', '%partner=' . $model['for'] . '%', false])
                                                               ->all();
                                                  if (empty($users)) {
                                                      return null;
                                                  }
                                                  $result = "";
                                                  foreach ($users as $user) {
                                                      $result .= $user->steam_id . "<Br/>";
                                                  }
                                                  return $result;
                                              },
                                          ],
                                          [
                                              'attribute' => 'replay',
                                              'options'   => ['width' => '150'],
                                              'label'     => Yii::t('common', "Отправить повторно"),
                                              'format'    => 'raw',
                                              'value'          => function ($model) {
                                                  if ($model['stage'] == 5) {
                                                      if (empty($model['for'])) {
                                                          return 'for: ' . $model['for'];
                                                      }
                                                      /** @var User[] $users */
                                                      $users = User::find()
                                                                   ->alias('u')
                                                                   ->joinWith(['userProfile up'])
                                                                   ->andWhere(['LIKE', 'up.trade_link', '%partner=' . $model['for'] . '%', false])
                                                                   ->all();
                                                      if (empty($users)) {
                                                          return null;
                                                      }
                                                      $result = "";
                                                      foreach ($users as $user) {
                                                          $result .= \yii\helpers\Html::a('Отправить повторно', \yii\helpers\Url::to(['/skindrops/buy', 'name' => $model['market_hash_name'], 'price' => $model['paid'] + 1000, 'userId' => $user->id])) . "<Br/>";
                                                      }
                                                      return $result;
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