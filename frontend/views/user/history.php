<?php

use yii\base\BaseObject;
use yii\web\View;
use common\models\user\UserDrop;
use yii\widgets\ActiveForm;
use frontend\widgets\Alert;

/** @var View $this */

$user = Yii::$app->user->identity;
$this->title = Yii::t('common', "История операций") . " - {$user->userProfile->name}";

$list = [];
$totalCount = 0;
foreach ($user->userBalances as $balance) {
    foreach ($balance->profits as $profit) {
        $list[] = [
                'comment' => $profit->comment,
                'sum' => "+" . number_format($profit->amount, 0, '.', ' '),
                'created_at' => $profit->created_at,
        ];
    }
}
foreach ($user->invoices as $invoice) {
    $list[] = [
            'comment' => $invoice->comment,
            'sum' => "-" . number_format($invoice->amount, 0, '.', ' '),
            'created_at' => $invoice->created_at,
    ];
}
foreach ($user->deposits as $deposit) {
    if ($deposit->status !== \common\models\invoice\Deposit::STATUS_SUCCESS) {
        continue;
    }
    $list[] = [
            'comment' => Yii::t('common', "Пополнение баланса"),
            'sum' => "+" . number_format($deposit->amount, 0, '.', ' '),
            'created_at' => $deposit->created_at,
    ];
}
usort($list, function ($a, $b) {
    return ($b['created_at'] < $a['created_at']) ? -1 : 1;
});

$dataProvider = new \yii\data\ArrayDataProvider([
                          'allModels' => $list,
                          'totalCount' => count($list),
                          'pagination' => [
                              'pageSize' => 20,
                          ],
]);

?>

<?= Alert::widget() ?>
<section class="tasks">
    <h2 class="tasks__title">
        <?=Yii::t('common', 'История операций')?>
        <span
                class="icons icons_24px icons_24px_info icons_hover"
                data-bs-toggle="tooltip"
                data-bs-placement="right"
                data-bs-title="<?=Yii::t('common', 'В этом разделе отображаются все ваши операции в магазине.')?>"
        ></span>
    </h2>

    <section class="page-stats__block-without-hover">
    <?= \kartik\grid\GridView::widget([
                                          'dataProvider' => $dataProvider,
                                          'layout'       => "{items} {pager}",
                                          'columns'      => [
                                              [
                                                  'attribute' => 'comment',
                                                  'label'     => Yii::t('common', "Детали"),
                                                  'format'    => 'raw',
                                                  'value'          => function ($model) {
                                                      return $model['comment'];
                                                  },
                                              ],
                                              [
                                                  'attribute' => 'created_at',
                                                  'options'   => ['width' => '200'],
                                                  'label'     => Yii::t('common', "Дата"),
                                                  'format'    => 'raw',
                                                  'value'          => function ($model) {
                                                      return \common\components\helpers\DateHelper::passed($model['created_at']);
                                                  },
                                              ],
                                              [
                                                  'attribute' => 'sum',
                                                  'options'   => ['width' => '150'],
                                                  'label'     => Yii::t('common', "Сумма"),
                                                  'format'    => 'raw',
                                                  'value'          => function ($model) {
                                                      if ($model['sum'] == 0) {
                                                          return '';
                                                      }
                                                      $class = '';
                                                      if ($model['sum'] < 0) {
                                                          $class = 'line_sum_munus';
                                                      }
                                                      return "<div class=\"line_sum {$class}\">{$model['sum']} <span class=\"icons icons_16px icons_16px_coin\"></span></div>";
                                                  },
                                              ],
                                          ],
                                      ]);
    ?>
    </section>
</section>