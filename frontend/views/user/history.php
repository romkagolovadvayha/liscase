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

<div class="container-fluid mb-5">
    <div class="main_wrap">
        <aside>
            <?= $this->render('@frontend/views/widgets/_profile'); ?>
            <?php echo $this->render('@frontend/views/layouts/_promocode_line'); ?>
        </aside>
        <main id="main" role="main">
            <div class="main_child">
                <div class="profile_content">
                    <div class="profile_content_header">
                        <?=Yii::t('common', "История операций")?>
                    </div>
                    <div class="profile_content_body">
                        <?= Alert::widget() ?>
                        <div class="profile_table">
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
                                                                          'options'   => ['width' => '150'],
                                                                          'label'     => Yii::t('common', "Дата"),
                                                                          'format'    => 'raw',
                                                                          'value'          => function ($model) {
                                                                              return $model['created_at'];
                                                                          },
                                                                      ],
                                                                      [
                                                                          'attribute' => 'sum',
                                                                          'options'   => ['width' => '150'],
                                                                          'label'     => Yii::t('common', "Сумма"),
                                                                          'format'    => 'raw',
                                                                          'value'          => function ($model) {
                                                                              return $model['sum'] . " RUB";
                                                                          },
                                                                      ],
                                                                  ],
                                                              ]);
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>