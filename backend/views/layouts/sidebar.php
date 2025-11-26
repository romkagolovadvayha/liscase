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
$skinsBadge = \common\models\serverskin\ServerSkin::find()
    ->andWhere(['status' => \common\models\serverskin\ServerSkin::STATUS_WAIT])
    ->count();
$radioBadge = \common\models\radio\RadioTrack::find()
    ->andWhere(['status' => \common\models\radio\RadioTrack::STATUS_WAIT])
    ->count();

$usersBadge = \common\models\user\User::find()
                                                 ->cache(60)
                                                 ->andWhere(['>=', 'created_at', date('Y-m-d 00:00:01')])
                                                 ->count();

$moder = Yii::$app->user->can(Role::ROLE_ADMIN) || Yii::$app->user->can(Role::ROLE_MODERATOR);
$admin = Yii::$app->user->can(Role::ROLE_ADMIN);
?>
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="<?=Yii::$app->params['baseUrl']?>" class="brand-link" style="display: block; text-align: center">
        <img src="<?=Yii::$app->params['baseUrl']?><?=Yii::$app->settings->get('design_logo')?>" alt="<?=Yii::$app->settings->get('site_title')?>" style="display: inline-block; width: 120px; max-width: 100%">
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
                         'visibility' => $moder,
                         'active' => _checkActive('/user') && !_checkActive('/user-'),
                    ],
                   [
                       'label' => 'Сервера',
                       'icon' => 'fas fa-gamepad',
                       'url' => [''],
                       'visibility' => $admin,
                       'active' => _checkActive('/servers') || _checkActive('/map-list'),
                       'items' => [
                           [
                               'label' => 'Список серверов',
                               'icon' => 'fas fa-list',
                               'url' => ['/servers/index'],
                               'active' => _checkActive('/servers/index') || _checkActive('/servers/create') || _checkActive('/servers/update'),
                           ],
                           [
                               'label' => 'Теги серверов',
                               'icon' => 'fas fa-tags',
                               'url' => ['/servers-tags/index'],
                               'active' => _checkActive('/servers-tags'),
                           ],
                           [
                               'label' => 'Сортировка',
                               'icon' => 'fas fa-sort',
                               'url' => ['/servers/sort'],
                               'active' => _checkActive('/servers/sort'),
                           ],
                           [
                               'label' => 'Карты',
                               'icon' => 'fas fa-map',
                               'url' => ['/map-list/index'],
                               'active' => _checkActive('/map-list'),
                           ],
                       ],
                   ],
                   [
                         'label' => 'Репорты',
                         'icon' => 'fa-solid fa-flag',
                         'url' => ['/reports'],
                         'visibility' => $moder,
                         'active' => _checkActive('/reports'),
                    ],
                   [
                       'label' => 'Рассылка сообщений',
                       'icon' => 'fa-solid fa-envelope',
                       'url' => ['/telegram-constructor'],
                       'visibility' => $admin,
                       'active' => _checkActive('/telegram-constructor'),
                   ],
                   [
                       'label' =>  Yii::t('common', 'Постройки'),
                       'icon' => 'fa-solid fa-house',
                       'badgeDanger' => $buildingBadge,
                       'url' => ['/building'],
                       'visibility' => Yii::$app->settings->get('section_buildings') && $moder,
                       'active' => _checkActive('/building'),
                   ],
                   [
                       'label' =>  Yii::t('common', 'Свои скины'),
                       'icon' => 'fa-solid fa-house',
                       'badgeDanger' => $skinsBadge,
                       'url' => ['/server-skin'],
                       'visibility' => Yii::$app->settings->get('section_skins') && $moder,
                       'active' => _checkActive('/server-skin'),
                   ],
                   [
                       'label' =>  Yii::t('common', 'Радиостанции'),
                       'icon' => 'fa-solid fa-music',
                       'badgeDanger' => $radioBadge,
                       'url' => [''],
                       'visibility' => Yii::$app->settings->get('site_section_radio') && $moder,
                       'active' => _checkActive('/radio'),
                       'items' => [
                           [
                               'label' => 'Главная',
                               'icon' => 'fas fa-home',
                               'url' => ['/radio/index'],
                               'active' => _checkActive('/radio/index') || (_checkActive('/radio') && !_checkActive('/radio/tracks') && !_checkActive('/radio/stations')),
                           ],
                           [
                               'label' => 'Станции',
                               'icon' => 'fas fa-broadcast-tower',
                               'url' => ['/radio/stations'],
                               'active' => _checkActive('/radio/stations') || _checkActive('/radio/station'),
                           ],
                           [
                               'label' => 'Треки',
                               'icon' => 'fas fa-music',
                               'badgeDanger' => $radioBadge,
                               'url' => ['/radio/tracks'],
                               'active' => _checkActive('/radio/tracks') || _checkActive('/radio/view'),
                           ],
                       ],
                   ],
                   [
                       'label' => 'Отчеты',
                       'icon' => 'fa-solid fa-table-list',
                       'url' => [''],
                       'visibility' => $admin,
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
                        'visibility' => Yii::$app->settings->get('section_skindrops') && $admin,
                        'active' => _checkActive('/skindrops/'),
                        'items' => [
                            [
                                'label' => 'Настройки',
                                'icon' => 'fas fa-cog',
                                'url' => ['/settings/index?category=skindrops'],
                                'active' => _checkActive('/settings/index?category=skindrops'),
                            ],
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
                       'label'  => Yii::t('common', 'Предметы'),
                       'url'    => '/drop/index',
                       'icon'   => 'fa-solid fa-table-cells',
                       'active' => _checkActive('/drop/'),
                   ],
                   [
                       'label'  => Yii::t('common', 'Категории'),
                       'url'    => '/category/index',
                       'icon'   => 'fa-solid fa-table-cells',
                       'active' => _checkActive('/category/'),
                   ],
                   [
                       'label'  => Yii::t('common', 'Блог'),
                       'icon'   => 'fa-regular fa-newspaper',
                       'url'    => '/blog',
                       'visibility' => Yii::$app->settings->get('section_blog') && $admin,
                       'active' => _checkActive('/blog/'),
                   ],
                   [
                       'label'  => Yii::t('common', 'Переводы'),
                       'icon'   => 'fa-solid fa-language',
                       'url'    => '/translateManager',
                       'visibility' => $admin,
                       'active' => _checkActive('/translateManager'),
                   ],
                    [
                        'label' => 'WIPE меню',
                        'icon' => 'fa-solid fa-cloud-sun',
                        'url' => ['/wipe'],
                        'visibility' => $admin,
                        'active' => _checkActive('/wipe'),
                    ],
                   [
                       'label' => 'Бонусы',
                       'icon' => 'fas fa-cog',
                       'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN),
                       'active' => _checkActive('/payment-bonuses') || _checkActive('/promocode') || _checkActive('/tasks-v2') || _checkActive('/settings/index?category=referral'),
                       'items' => [
                           [
                               'label' => 'Промокоды',
                               'icon' => 'fa-solid fa-percent',
                               'url' => ['/promocode'],
                               'visibility' => $admin,
                               'active' => _checkActive('/promocode'),
                           ],
                           [
                               'label'  => Yii::t('common', 'Задания'),
                               'icon'   => 'fa-solid fa-list-check',
                               'url'    => '/tasks-v2',
                               'visibility' => Yii::$app->settings->get('section_tasks') && $admin,
                               'active' => _checkActive('/tasks-v2'),
                           ],
                           [
                               'label'  => Yii::t('common', 'Реферальная система'),
                               'icon'   => 'fa-solid fa-users',
                               'url'    => '/settings/index?category=referral',
                               'visibility' => Yii::$app->settings->get('referral_bonus') && $admin,
                               'active' => _checkActive('/settings/index?category=referral'),
                           ],
                           [
                               'label'  => Yii::t('common', 'При пополнении'),
                               'icon'   => 'fa-solid fa-ruble-sign',
                               'url'    => '/payment-bonuses',
                               'visibility' => $admin,
                               'active' => _checkActive('/payment-bonuses'),
                           ],
                       ]
                   ],
                   [
                       'label' => 'Настройки',
                       'icon' => 'fas fa-cog',
                       'visibility' => Yii::$app->user->can(Role::ROLE_ADMIN),
                       'active' => _checkActive('/settings'),
                       'items' => [
                           [
                               'label' => 'Настройки сайта',
                               'icon' => 'fas fa-sitemap',
                               'url' => ['/settings/index?category=site'],
                               'active' => _checkActive('/settings/index?category=site'),
                           ],
                           [
                               'label' => 'Настройки дизайна',
                               'icon' => 'fas fa-spray-can',
                               'url' => ['/settings/index?category=design'],
                               'active' => _checkActive('/settings/index?category=design'),
                           ],
                           [
                               'label' => 'Способы оплаты',
                               'icon' => 'fas fa-shopping-basket',
                               'url' => ['/settings/index?category=payments'],
                               'active' => _checkActive('/settings/index?category=payments'),
                           ],
                           [
                               'label' => 'Настройка ботов',
                               'icon' => 'fas fa-robot',
                               'url' => ['/settings/index?category=bots'],
                               'active' => _checkActive('/settings/index?category=bots'),
                           ],
                           [
                               'label' => 'Добавить настройку',
                               'icon' => 'fas fa-plus',
                               'url' => ['/settings/create'],
                               'active' => _checkActive('/settings/create'),
                           ],
                       ]
                   ],
                ],
            ]);
            ?>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>