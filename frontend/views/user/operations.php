<?php
use yii\widgets\Pjax;
use common\models\user\UserPayoutSkins;

/** @var yii\web\View $this */
/** @var \yii\data\ArrayDataProvider $dataProvider */
?>
<div class="skins-operations">
    <?php Pjax::begin([
        'id' => 'operations-list-pjax',
        'enablePushState' => false,
        'timeout' => 10000,
    ]); ?>
    <?= \kartik\grid\GridView::widget([
                                          'dataProvider' => $dataProvider,
                                          'layout'       => "{items}{pager}",
                                          'tableOptions' => ['class' => 'skins-operations__table'],
                                          'columns'      => [
                                              [
                                                  'attribute' => 'image',
                                                  'label'     => '',
                                                  'format'    => 'raw',
                                                  'options'   => ['width' => '60'],
                                                  'value'     => function ($model) {
                                                      // Для операций "Зачислено" показываем иконку
                                                      if (isset($model['status']) && $model['status'] === Yii::t('common', "Зачислено")) {
                                                          return '<div class="skins-operations__icon"><i class="fas fa-gift"></i></div>';
                                                      }
                                                      // Для покупок скинов показываем картинку
                                                      if (!empty($model['image'])) {
                                                          return '<img src="' . $model['image'] . '" alt="' . (isset($model['name']) ? htmlspecialchars($model['name']) : '') . '" class="skins-operations__image">';
                                                      }
                                                      // Для переводов показываем иконку
                                                      if (isset($model['status']) && $model['status'] === Yii::t('common', "Перевод в магазин")) {
                                                          return '<div class="skins-operations__icon"><i class="fas fa-exchange-alt"></i></div>';
                                                      }
                                                      return '';
                                                  },
                                              ],
                                              [
                                                  'attribute' => 'created_at',
                                                  'options'   => ['width' => '200'],
                                                  'label'     => Yii::t('common', "Дата операции"),
                                                  'format'    => 'raw',
                                                  'value'     => function ($model) {
                                                      return \common\components\helpers\DateHelper::passed($model['created_at']);
                                                  },
                                              ],
                                               [
                                                   'attribute' => 'name',
                                                   'label'     => Yii::t('common', "Название"),
                                                   'format'    => 'raw',
                                                   'value'     => function ($model) {
                                                       // Для операций "Зачислено" показываем "Розыгрыш скинов"
                                                       if (isset($model['status']) && $model['status'] === Yii::t('common', "Зачислено")) {
                                                           return Yii::t('common', "Розыгрыш скинов");
                                                       }
                                                       // Для переводов показываем соответствующий текст
                                                       if (isset($model['status']) && $model['status'] === Yii::t('common', "Перевод в магазин")) {
                                                           return Yii::t('common', "Перевод средств");
                                                       }
                                                       // Для покупок скинов показываем название
                                                       return isset($model['name']) ? htmlspecialchars($model['name']) : '';
                                                   },
                                               ],
                                              [
                                                  'attribute' => 'status',
                                                  'options'   => ['width' => '150'],
                                                  'label'     => Yii::t('common', "Статус"),
                                                  'format'    => 'raw',
                                                  'value'     => function ($model) {
                                                      $status = isset($model['status']) ? $model['status'] : '';
                                                      $statusKey = isset($model['statusKey']) ? $model['statusKey'] : null;
                                                      
                                                      $class = 'skins-operations__status';
                                                      if ($statusKey === UserPayoutSkins::STATUS_SUCCESS) {
                                                          $class .= ' skins-operations__status--success';
                                                      } elseif ($statusKey === UserPayoutSkins::STATUS_WAIT || $statusKey === UserPayoutSkins::STATUS_NEW) {
                                                          $class .= ' skins-operations__status--wait';
                                                      } elseif ($statusKey === UserPayoutSkins::STATUS_REJECT) {
                                                          $class .= ' skins-operations__status--reject';
                                                      }
                                                      
                                                      return '<span class="' . $class . '">' . htmlspecialchars($status) . '</span>';
                                                  },
                                              ],
                                              [
                                                  'attribute' => 'amount',
                                                  'options'   => ['width' => '120'],
                                                  'label'     => Yii::t('common', "Сумма"),
                                                  'format'    => 'raw',
                                                  'value'     => function ($model) {
                                                      if (!isset($model['amount']) || $model['amount'] == 0) {
                                                          return '';
                                                      }
                                                      $class = '';
                                                      $amountValue = abs($model['amount']);
                                                      $amount = number_format($amountValue, 0, '.', ' ');
                                                      if ($model['amount'] < 0) {
                                                          $class = 'line_sum_munus';
                                                          $amount = '-' . $amount;
                                                      } else {
                                                          $amount = '+' . $amount;
                                                      }
                                                      return "<div class=\"line_sum {$class}\">{$amount} <span class=\"icons icons_16px icons_16px_coin_skins\"></span></div>";
                                                  },
                                              ],
                                          ],
                                      ]);
    ?>
    <?php Pjax::end(); ?>
</div>

