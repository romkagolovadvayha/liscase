<?php

use yii\bootstrap5\Nav;
if (Yii::$app->params['buildings']) {
    $mobileMenu[] = [
        'label'   => '<i class="fa-solid fa-house"></i> ' . Yii::t('common', 'Постройки'),
        'encode' => false,
        'url'     => '/buildings',
    ];
}
if (empty(Yii::$app->params['skinpayment']) && !Yii::$app->params['buildings'] && !Yii::$app->params['blog']) {
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
                       [
                           'label'   => '<i class="fa-solid fa-house"></i> ' . Yii::t('common', 'Постройки'),
                           'url'     => '/buildings',
                           'encode' => false,
                           'visible' => Yii::$app->params['buildings'],
                           'options'     => [
                               'class' => 'menu-bildings'
                           ],
                       ],
                       [
                           'label'   => '<i class="far fa-newspaper"></i> ' . Yii::t('common', 'Блог'),
                           'url'     => '/posts',
                           'encode' => false,
                           'visible' => Yii::$app->params['blog'],
                           'options'     => [
                               'class' => 'menu-bildings'
                           ],
                       ],
                   ],
                   'options' => ['class' =>'side-menu'],
               ]);
?>