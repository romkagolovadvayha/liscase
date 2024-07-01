<?=\yii\bootstrap5\Nav::widget([
                                   'items' => [
                                       [
                                           'label'   => '<i class="far fa-calendar-check"></i> ' . Yii::t('common', "Задания на вайп"),
                                           'encode' => false,
                                           'url'     => '/user/tasks',
                                           'active' => (bool)strstr(Yii::$app->request->url, 'user/tasks'),
                                       ],
                                       [
                                           'label'   => '<i class="fas fa-shopping-basket"></i> ' . Yii::t('common', 'Корзина'),
                                           'encode' => false,
                                           'url'     => '/user/inventory',
                                           'active' => (bool)strstr(Yii::$app->request->url, 'user/inventory'),
                                       ],
                                       [
                                           'label'   => '<i class="fas fa-history"></i> ' . Yii::t('common', 'История операций'),
                                           'encode' => false,
                                           'url'     => '/user/history',
                                           'active' => (bool)strstr(Yii::$app->request->url, 'user/history'),
                                       ],
                                       [
                                           'label'   => '<i class="fas fa-link"></i> ' . Yii::t('common', "Реферальная система"),
                                           'encode' => false,
                                           'url'     => '/user/partner',
                                           'active' => (bool)strstr(Yii::$app->request->url, 'user/partner'),
                                       ],
                                       [
                                           'label'   => '<i class="fas fa-wallet"></i> ' . Yii::t('common', 'Пополнить баланс'),
                                           'encode' => false,
                                           'linkOptions' => [
                                               'class' => 'show-modal-link',
                                               'data-title' => Yii::t('common', 'Пополнить баланс'),
                                               'data-size' => 'modal-sm',
                                               'data-toggl' => 'modal',
                                               'data-href' => '/user/payment',
                                               'data-target' => 'modal-dialog',
                                           ],
                                           'url'     => '#',
                                       ],
                                   ],
                                   'options' => ['class' =>'profile_widget_menu'],
                               ]);;
?>