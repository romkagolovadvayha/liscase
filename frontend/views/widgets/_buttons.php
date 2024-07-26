<?php

use yii\bootstrap5\Nav;
?>
<?=Nav::widget([
                   'items' => [
                       [
                           'label'   => '<i class="fas fa-house-damage"></i> ' . Yii::t('common', 'Ваши постройки'),
                           'url'     => '/buldings',
                           'encode' => false,
                           'options'     => [
                               'class' => 'menu-bildings'
                           ],
                       ],
                   ],
                   'options' => ['class' =>'side-menu'],
               ]);
?>