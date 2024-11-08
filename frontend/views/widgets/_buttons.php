<?php

use yii\bootstrap5\Nav;
?>
<?=Nav::widget([
                   'items' => [
                       [
                           'label'   => '<i class="fab fa-steam"></i> ' . Yii::t('common', 'Пополнение скинами Steam'),
                           'url'     => Yii::$app->params['skinpayment'],
                           'encode' => false,
                           'v' => !empty(Yii::$app->params['skinpayment']),
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