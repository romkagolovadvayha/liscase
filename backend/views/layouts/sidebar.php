<?php
use common\components\helpers\Role;
use common\models\user\UserJob;
use common\models\wallet\WalletTransaction;
use queuemanager\models\QueueManager;
use common\models\wallet\Wallet;
use common\models\wallet\Project;

function _checkActive($urlStr)
{
    return (bool)strstr(Yii::$app->request->url, $urlStr);
}

$jobBadges = 0;
$buildingBadge = \common\models\building\Building::find()
    ->andWhere(['status' => \common\models\building\Building::STATUS_WAIT])
    ->count();

$usersBadge = \common\models\user\User::find()
                                                 ->andWhere(['>=', 'created_at', date('Y-m-d 00:00:01')])
                                                 ->andWhere(['<=', 'created_at', date('Y-m-d 23:59:59')])
                                                 ->count();
?>
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="<?=Yii::$app->params['baseUrl']?>" class="brand-link" style="display: block; text-align: center">
        <img src="<?=Yii::$app->params['cdnUrl']?>/images/logo.png" alt="Prostoj Logo" style="display: inline-block; width: 120px; max-width: 100%">
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- <div class="form-inline">
            <div class="input-group" data-widget="sidebar-search">
                <input class="form-control form-control-sidebar" type="search" placeholder="Search" aria-label="Search">
                <div class="input-group-append">
                    <button class="btn btn-sidebar">
                        <i class="fas fa-search fa-fw"></i>
                    </button>
                </div>
            </div>
        </div> -->
        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <?php
            echo \backend\widgets\Menu::widget([
                'options' => [
                        'class' => 'nav nav-pills nav-sidebar flex-column nav-flat nav-child-indent',
                        'data-widget' => 'treeview',
                        'role' => 'menu',
                        'data-accordion' => 'false',
                ],
                'items' => [
                   [
                         'label' => 'Пользователи',
                         'icon' => 'fa-solid fa-users',
                         'badgeSuccess' => $usersBadge,
                         'url' => ['/user'],
                         'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN) || Yii::$app->user->can(Role::ROLE_MODERATOR),
                         'active' => _checkActive('/user') && !_checkActive('/user-'),
                    ],
                   [
                       'label' => 'Сервера',
                       'icon' => 'fas fa-gamepad',
                       'url' => ['/servers'],
                       'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN),
                       'active' => _checkActive('/servers'),
                   ],
                   [
                         'label' => 'Репорты',
                         'icon' => 'fa-solid fa-flag',
                         'url' => ['/reports'],
                         'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN) || Yii::$app->user->can(Role::ROLE_MODERATOR),
                         'active' => _checkActive('/reports'),
                    ],
                   [
                       'label' => 'Рассылка сообщений',
                       'icon' => 'fa-solid fa-envelope',
                       'url' => ['/telegram-constructor'],
                       'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN),
                       'active' => _checkActive('/telegram-constructor'),
                   ],
                   [
                       'label' =>  Yii::t('common', 'Постройки'),
                       'icon' => 'fa-solid fa-house',
                       'badgeDanger' => $buildingBadge,
                       'url' => ['/building'],
                       'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN) || Yii::$app->user->can(Role::ROLE_MODERATOR),
                       'active' => _checkActive('/building'),
                   ],
                   [
                       'label' => 'Отчеты',
                       'icon' => 'fa-solid fa-table-list',
                       'url' => [''],
                       'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN),
                       'active' => _checkActive('/report/') || _checkActive('/deposit'),
                       'items' => [
                           [
                               'label' => 'Товары',
                               'icon' => 'fa-brands fa-product-hunt',
                               'url' => ['/report/products'],
                               'active' => _checkActive('/report/products'),
                           ],
                           [
                               'label' => 'Сеты',
                               'icon' => 'fa-solid fa-object-ungroup',
                               'url' => ['/report/sets'],
                               'active' => _checkActive('/report/sets'),
                           ],
                           [
                               'label' => 'Пополнения',
                               'icon' => 'fa-solid fa-ruble-sign',
                               'url' => ['/report/deposits'],
                               'active' => _checkActive('/report/deposits'),
                           ],
                           [
                               'label' => 'Депозиты',
                               'icon' => 'fa-solid fa-coins',
                               'url' => ['/deposit'],
                               'active' => _checkActive('/deposit'),
                           ],
                       ]
                   ],
                    [
                        'label' => 'Скины',
                        'icon' => 'fa-solid fa-gift',
                        'url' => ['/rbac/permission'],
                        'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN),
                        'active' => _checkActive('/skindrops/'),
                        'items' => [
                            [
                                'label' => 'Отправленные скины',
                                'icon' => 'fa-solid fa-hourglass-start',
                                'url' => ['/skindrops/index'],
                                'active' => _checkActive('/skindrops/index'),
                            ],
                            [
                                'label' => 'Отчет',
                                'icon' => 'fa-solid fa-ranking-star',
                                'url' => ['/skindrops/report'],
                                'active' => _checkActive('/skindrops/report'),
                            ],
                        ]
                    ],
                    [
                        'label' => 'Товары',
                        'icon' => 'fa-solid fa-list',
                        'url' => ['/rbac/permission'],
                        'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN),
                        'active' => _checkActive('/box/') || _checkActive('/sets/') || _checkActive('/drop/') || _checkActive('/select/'),
                        'items' => [
                            [
                                'label'  => Yii::t('common', 'Рулетки'),
                                'url'    => '/box/index',
                                'icon'   => 'fa-solid fa-gift',
                                'active' => _checkActive('/box/'),
                            ],
                            [
                                'label'  => Yii::t('common', 'Наборы'),
                                'url'    => '/sets/index',
                                'icon'   => 'fa-solid fa-suitcase',
                                'active' => _checkActive('/sets/'),
                            ],
                            [
                                'label'  => Yii::t('common', 'Наборы с выбором'),
                                'url'    => '/select/index',
                                'icon'   => 'fa-solid fa-object-ungroup',
                                'active' => _checkActive('/select/'),
                            ],
                            [
                                'label'  => Yii::t('common', 'Предметы'),
                                'url'    => '/drop/index',
                                'icon'   => 'fa-solid fa-table-cells',
                                'active' => _checkActive('/drop/'),
                            ],
                        ]
                    ],
                   [
                       'label'  => Yii::t('common', 'Блог'),
                       'icon'   => 'fa-regular fa-newspaper',
                       'url'    => '/blog',
                       'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN),
                       'active' => _checkActive('/blog/'),
                   ],
                   [
                       'label'  => Yii::t('common', 'Задания'),
                       'icon'   => 'fa-solid fa-list-check',
                       'url'    => '/task',
                       'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN),
                       'active' => _checkActive('/task'),
                   ],
                   [
                       'label'  => Yii::t('common', 'Переводы'),
                       'icon'   => 'fa-solid fa-language',
                       'url'    => '/translateManager',
                       'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN),
                       'active' => _checkActive('/translateManager'),
                   ],
                    [
                        'label' => 'Промокоды',
                        'icon' => 'fa-solid fa-percent',
                        'url' => ['/promocode'],
                        'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN),
                        'active' => _checkActive('/promocode'),
                    ],
                    [
                        'label' => 'WIPE меню',
                        'icon' => 'fa-solid fa-cloud-sun',
                        'url' => ['/wipe'],
                        'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN),
                        'active' => _checkActive('/wipe'),
                    ],
//                    ['label' => 'Simple Link', 'icon' => 'th', 'badge' => '<span class="right badge badge-danger">New</span>'],
//                    ['label' => 'Simple Link', 'icon' => 'th', 'badge' => '<span class="right badge badge-danger">New</span>'],
//                    ['label' => 'Yii2 PROVIDED', 'header' => true],
//                    ['label' => 'Login', 'url' => ['site/login'], 'icon' => 'sign-in-alt', 'visible' => Yii::$app->user->isGuest],
//                    ['label' => 'Gii',  'icon' => 'file-code', 'url' => ['/gii'], 'target' => '_blank'],
//                    ['label' => 'Debug', 'icon' => 'bug', 'url' => ['/debug'], 'target' => '_blank'],
                ],
            ]);
            ?>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>