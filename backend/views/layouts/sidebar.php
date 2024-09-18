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
?>
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="<?=Yii::$app->params['baseUrl']?>" class="brand-link" style="display: block; text-align: center">
        <img src="<?=Yii::$app->params['baseUrl']?>/images/logo.png" alt="Prostoj Logo" style="display: inline-block; width: 120px; max-width: 100%">
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
                         'icon' => 'users',
                         'url' => ['/user'],
                         'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN) || Yii::$app->user->can(Role::ROLE_MODERATOR),
                         'active' => _checkActive('/user') && !_checkActive('/user-'),
                    ],
                   [
                         'label' => 'Репорты',
                         'icon' => 'user-shield',
                         'url' => ['/reports'],
                         'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN) || Yii::$app->user->can(Role::ROLE_MODERATOR),
                         'active' => _checkActive('/reports'),
                    ],
                   [
                       'label' => 'Рассылка сообщений',
                       'icon' => 'user-shield',
                       'url' => ['/telegram-constructor'],
                       'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN),
                       'active' => _checkActive('/telegram-constructor'),
                   ],
                   [
                       'label' => 'Отчеты',
                       'icon' => 'user-tag',
                       'url' => [''],
                       'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN),
                       'active' => _checkActive('/report/'),
                       'items' => [
                           [
                               'label' => 'Товары',
                               'icon' => 'user-nurse',
                               'url' => ['/report/products'],
                               'active' => _checkActive('/report/products'),
                           ],
                           [
                               'label' => 'Сеты',
                               'icon' => 'user-nurse',
                               'url' => ['/report/sets'],
                               'active' => _checkActive('/report/sets'),
                           ],
                           [
                               'label' => 'Пополнения',
                               'icon' => 'user-tag',
                               'url' => ['/report/deposits'],
                               'active' => _checkActive('/report/deposits'),
                           ],
                       ]
                   ],
                    [
                        'label' => 'Скины',
                        'icon' => 'user-lock',
                        'url' => ['/rbac/permission'],
                        'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN),
                        'active' => _checkActive('/skindrops/'),
                        'items' => [
                            [
                                'label' => 'Отправленные скины',
                                'icon' => 'user-nurse',
                                'url' => ['/skindrops/index'],
                                'active' => _checkActive('/skindrops/index'),
                            ],
                            [
                                'label' => 'Отчет',
                                'icon' => 'user-tag',
                                'url' => ['/skindrops/report'],
                                'active' => _checkActive('/skindrops/report'),
                            ],
                        ]
                    ],
                    [
                        'label' => 'Товары',
                        'icon' => 'user-lock',
                        'url' => ['/rbac/permission'],
                        'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN),
                        'active' => _checkActive('/box/') || _checkActive('/sets/') || _checkActive('/drop/') || _checkActive('/select/'),
                        'items' => [
                            [
                                'label'  => Yii::t('common', 'Рулетки'),
                                'url'    => '/box/index',
                                'icon'   => 'bi bi-gift-fill',
                                'active' => _checkActive('/box/'),
                            ],
                            [
                                'label'  => Yii::t('common', 'Наборы'),
                                'url'    => '/sets/index',
                                'icon'   => 'bi bi-dropbox',
                                'active' => _checkActive('/sets/'),
                            ],
                            [
                                'label'  => Yii::t('common', 'Наборы с выбором'),
                                'url'    => '/select/index',
                                'icon'   => 'bi bi-dropbox',
                                'active' => _checkActive('/select/'),
                            ],
                            [
                                'label'  => Yii::t('common', 'Предметы'),
                                'url'    => '/drop/index',
                                'icon'   => 'bi bi-dropbox',
                                'active' => _checkActive('/drop/'),
                            ],
                        ]
                    ],
                   [
                       'label'  => Yii::t('common', 'Блог'),
                       'icon'   => 'bi bi-newspaper',
                       'url'    => '/blog',
                       'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN),
                       'active' => _checkActive('/blog/'),
                   ],
                   [
                       'label'  => Yii::t('common', 'Переводы'),
                       'icon'   => 'bi bi-newspaper',
                       'url'    => '/translateManager',
                       'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN),
                       'active' => _checkActive('/translateManager'),
                   ],
                    [
                        'label' => 'Промокоды',
                        'icon' => 'calendar-alt',
                        'url' => ['/promocode'],
                        'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN),
                        'active' => _checkActive('/promocode'),
                    ],
                    [
                        'label' => 'WIPE меню',
                        'icon' => 'calendar-alt',
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