<?php

use yii\bootstrap5\Nav;
if (empty(Yii::$app->params['skinpayment'])) {
    return;
}
?>
<?=Nav::widget([
                   'items' => [
                       [
                           'label'   => '<i class="fab fa-steam"></i> ' . Yii::t('common', 'Пополнение скинами Steam'),
                           'url'     => Yii::$app->params['skinpayment'],
                           'encode' => false,
                           'visible' => !empty(Yii::$app->params['skinpayment']),
                           'options'     => [
                               'class' => 'menu-bildings'
                           ],
                           'linkOptions' => [
                               'target' => '_blank',
                           ],
                       ],
                   ],
                   'options' => ['class' =>'side-menu'],
               ]);
?>