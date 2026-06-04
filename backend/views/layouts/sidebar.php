<?php
use common\components\helpers\Role;
use yii\helpers\Html;
use yii\helpers\Url;

function _checkActive($urlStr)
{
    return (bool)strstr(Yii::$app->request->url, $urlStr);
}

$buildingBadge = \common\models\building\Building::find()
    ->andWhere(['status' => \common\models\building\Building::STATUS_WAIT])
    ->count();
$skinsBadge = \common\models\serverskin\ServerSkin::find()
    ->andWhere(['status' => \common\models\serverskin\ServerSkin::STATUS_WAIT])
    ->count();
$videosBadge = \common\models\video\UserVideo::find()
    ->andWhere(['status' => \common\models\video\UserVideo::STATUS_WAIT])
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
$support = Yii::$app->user->can(Role::ROLE_SUPPORT);
$moderOrSupport = $moder || $support;

// Группировка меню по секциям
$moderationItems = [];
$managementItems = [];
$projectItems = [];

// Модерация
if ($moder) {
    $moderationItems[] = [
        'label' => 'Игроки',
        'icon' => 'fa-solid fa-users',
        'badgeSuccess' => $usersBadge,
        'url' => ['/user'],
        'active' => (_checkActive('/user') && !_checkActive('/user-')) || _checkActive('/reports'),
    ];
}
if ($moderOrSupport) {
    $moderationItems[] = [
        'label' => 'Сервера',
        'icon' => 'fas fa-gamepad',
        'url' => [''],
        'active' => _checkActive('/servers') || _checkActive('/map-list') || _checkActive('/rust-plugin-config') || _checkActive('/servers-rules') || _checkActive('/servers-tags') || _checkActive('/servers-radio-station') || _checkActive('/ftp-manager') || _checkActive('/wipe-calendar'),
        'items' => [
            [
                'label' => 'Список серверов',
                'icon' => 'fas fa-list',
                'url' => ['/servers/index'],
                'active' => _checkActive('/servers/index') || _checkActive('/servers/create') || _checkActive('/servers/update'),
            ],
            [
                'label' => 'Календарь вайпов',
                'icon' => 'fas fa-calendar-days',
                'url' => ['/wipe-calendar/index'],
                'active' => _checkActive('/wipe-calendar'),
            ],
            [
                'label' => 'Даты вайпов',
                'icon' => 'fas fa-calendar-check',
                'url' => ['/servers/wipe-dates'],
                'active' => _checkActive('/servers/wipe-dates'),
            ],
            [
                'label' => 'Правила серверов',
                'icon' => 'fas fa-gavel',
                'url' => ['/servers-rules/index'],
                'visibility' => $moderOrSupport,
                'active' => _checkActive('/servers-rules'),
            ],
            [
                'label' => 'Радиостанции',
                'icon' => 'fas fa-radio',
                'url' => ['/servers-radio-station/index'],
                'visibility' => $moder,
                'active' => _checkActive('/servers-radio-station'),
            ],
            [
                'label' => 'RCON команды',
                'icon' => 'fas fa-terminal',
                'url' => ['/rcon/index'],
                'visibility' => $admin,
                'active' => _checkActive('/rcon'),
            ],
            [
                'label' => 'FTP менеджер',
                'icon' => 'fas fa-folder-tree',
                'url' => ['/ftp-manager/index'],
                'visibility' => $admin,
                'active' => _checkActive('/ftp-manager/index') || ( _checkActive('/ftp-manager') && !_checkActive('/ftp-manager/broadcast') ),
            ],
            [
                'label' => 'FTP: все сервера',
                'icon' => 'fas fa-cloud-arrow-up',
                'url' => ['/ftp-manager/broadcast'],
                'visibility' => $admin,
                'active' => _checkActive('/ftp-manager/broadcast'),
            ],
            [
                'label' => 'Карты',
                'icon' => 'fas fa-map',
                'url' => ['/map-list/index'],
                'active' => _checkActive('/map-list'),
            ],
            [
                'label' => 'Конфиги',
                'icon' => 'fas fa-cog',
                'url' => ['/rust-plugin-config/index'],
                'visibility' => $admin,
                'active' => _checkActive('/rust-plugin-config'),
            ],
        ],
    ];
}
if ($moder) {
    $moderationItems[] = [
        'label' => Yii::t('common', 'Кланы'),
        'icon' => 'fas fa-users-gear',
        'url' => ['/clan/index'],
        'active' => _checkActive('/clan'),
    ];
    $moderationItems[] = [
        'label' => Yii::t('common', 'Турниры'),
        'icon' => 'fas fa-trophy',
        'url' => ['/tournament/index'],
        'active' => _checkActive('/tournament'),
    ];
}
if ($moder) {
    $moderationItems[] = [
        'label' => Yii::t('common', 'Постройки'),
        'icon' => 'fa-solid fa-house',
        'badgeDanger' => $buildingBadge,
        'url' => ['/building/index'],
        'visibility' => Yii::$app->settings->get('section_buildings'),
        'active' => _checkActive('/building'),
    ];
    $moderationItems[] = [
        'label' => Yii::t('common', 'Свои скины'),
        'icon' => 'fa-solid fa-palette',
        'badgeDanger' => $skinsBadge,
        'url' => ['/server-skin/index'],
        'visibility' => Yii::$app->settings->get('section_skins'),
        'active' => _checkActive('/server-skin'),
    ];
    $moderationItems[] = [
        'label' => Yii::t('common', 'Видео'),
        'icon' => 'fa-solid fa-video',
        'badgeDanger' => $videosBadge,
        'url' => ['/video/index'],
        'active' => _checkActive('/video'),
    ];
    $moderationItems[] = [
        'label' => Yii::t('common', 'Предметы'),
        'icon' => 'fa-solid fa-table-cells',
        'url' => ['/drop/index'],
        'active' => _checkActive('/drop/') && !_checkActive('/user-drop'),
    ];
    $moderationItems[] = [
        'label' => Yii::t('common', 'Предметы пользователей'),
        'icon' => 'fa-solid fa-box-open',
        'url' => ['/user-drop/index'],
        'active' => _checkActive('/user-drop'),
    ];
    $moderationItems[] = [
        'label' => Yii::t('common', 'Блог'),
        'icon' => 'fa-regular fa-newspaper',
        'url' => ['/blog/index'],
        'visibility' => Yii::$app->settings->get('section_blog') && ($moder || $support),
        'active' => _checkActive('/blog/'),
    ];
    $moderationItems[] = [
        'label' => Yii::t('common', 'Стикеры поддержки'),
        'icon' => 'fa-regular fa-face-smile',
        'url' => ['/support-sticker/index'],
        'active' => _checkActive('/support-sticker'),
    ];
}
if ($moderOrSupport) {
    $moderationItems[] = [
        'label' => Yii::t('common', 'Рамки аватаров'),
        'icon' => 'fa-regular fa-square',
        'url' => ['/avatar-frame/index'],
        'active' => _checkActive('/avatar-frame'),
    ];
}

// Управление
if ($admin) {
    $managementItems[] = [
        'label' => 'Рассылка сообщений',
        'icon' => 'fa-solid fa-envelope',
        'url' => ['/telegram-constructor'],
        'active' => _checkActive('/telegram-constructor'),
    ];
    $managementItems[] = [
        'label' => 'Отчеты',
        'icon' => 'fa-solid fa-table-list',
        'url' => [''],
        'active' => _checkActive('/report/') || _checkActive('/deposit'),
        'items' => [
            [
                'label' => 'Товары',
                'icon' => 'fa-brands fa-product-hunt',
                'url' => ['/report/products'],
                'active' => _checkActive('/report/products'),
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
                'url' => ['/deposit/index'],
                'active' => _checkActive('/deposit'),
            ],
        ]
    ];
    if (Yii::$app->settings->get('site_section_radio')) {
        $managementItems[] = [
            'label' => Yii::t('common', 'Радиостанции'),
            'icon' => 'fa-solid fa-music',
            'badgeDanger' => $radioBadge,
            'url' => [''],
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
        ];
    }
    if (Yii::$app->settings->get('section_skindrops')) {
        $managementItems[] = [
            'label' => 'Скины',
            'icon' => 'fa-solid fa-gift',
            'url' => ['/skindrops/index'],
            'active' => _checkActive('/skindrops/'),
            'items' => [
                [
                    'label' => 'Настройки',
                    'icon' => 'fas fa-cog',
                    'url' => ['/settings/index', 'category' => 'skindrops'],
                    'active' => _checkActive('category=skindrops'),
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
        ];
    }
    $managementItems[] = [
        'label' => 'WIPE меню',
        'icon' => 'fa-solid fa-cloud-sun',
        'url' => ['/wipe/index'],
        'active' => _checkActive('/wipe'),
    ];
    $managementItems[] = [
        'label' => 'Плагины',
        'icon' => 'fa-solid fa-puzzle-piece',
        'url' => ['/plugins/index'],
        'active' => _checkActive('/plugins'),
    ];
}
if ($moder) {
    $managementItems[] = [
        'label' => 'Бонусы',
        'icon' => 'fas fa-tags',
        'active' => _checkActive('/audience-bonus') || _checkActive('/payment-bonuses') || _checkActive('/promocode') || _checkActive('/tasks-v2') || _checkActive('category=referral'),
        'items' => [
            [
                'label' => 'Бонусы аудитории',
                'icon' => 'fa-solid fa-users',
                'url' => ['/audience-bonus/index'],
                'active' => _checkActive('/audience-bonus'),
            ],
            [
                'label' => 'Промокоды',
                'icon' => 'fa-solid fa-percent',
                'url' => ['/promocode/index'],
                'active' => _checkActive('/promocode'),
            ],
            [
                'label' => Yii::t('common', 'Задания'),
                'icon' => 'fa-solid fa-list-check',
                'url' => ['/tasks-v2/index'],
                'visibility' => Yii::$app->settings->get('section_tasks'),
                'active' => _checkActive('/tasks-v2'),
            ],
            [
                'label' => Yii::t('common', 'Реферальная система'),
                'icon' => 'fa-solid fa-users',
                'url' => ['/settings/index', 'category' => 'referral'],
                'visibility' => Yii::$app->settings->get('referral_bonus'),
                'active' => _checkActive('category=referral'),
            ],
            [
                'label' => Yii::t('common', 'При пополнении'),
                'icon' => 'fa-solid fa-ruble-sign',
                'url' => ['/payment-bonuses/index'],
                'active' => _checkActive('/payment-bonuses'),
            ],
        ]
    ];
}

// Проект
if ($admin) {
    $projectItems[] = [
        'label' => 'Настройки',
        'icon' => 'fas fa-cog',
        'active' => _checkActive('/settings'),
        'items' => [
            [
                'label' => 'Настройки сайта',
                'icon' => 'fas fa-sitemap',
                'url' => ['/settings/index', 'category' => 'site'],
                'active' => _checkActive('category=site'),
            ],
            [
                'label' => 'Настройки дизайна',
                'icon' => 'fas fa-spray-can',
                'url' => ['/settings/index', 'category' => 'design'],
                'active' => _checkActive('category=design'),
            ],
            [
                'label' => 'Способы оплаты',
                'icon' => 'fas fa-shopping-basket',
                'url' => ['/settings/index', 'category' => 'payments'],
                'active' => _checkActive('category=payments'),
            ],
            [
                'label' => 'Настройка ботов',
                'icon' => 'fas fa-robot',
                'url' => ['/settings/index', 'category' => 'bots'],
                'active' => _checkActive('category=bots'),
            ],
            [
                'label' => 'Добавить настройку',
                'icon' => 'fas fa-plus',
                'url' => ['/settings/create'],
                'active' => _checkActive('/settings/create'),
            ],
            [
                'label' => 'Файлы S3',
                'icon' => 'fas fa-cloud',
                'url' => ['/s3-storage/index'],
                'active' => _checkActive('/s3-storage'),
            ],
        ]
    ];
    $projectItems[] = [
        'label' => 'Дизайн-система',
        'icon' => 'fa-solid fa-palette',
        'url' => ['/design-system'],
        'active' => _checkActive('/design-system'),
    ];
}

function renderMenuItem($item, $level = 0) {
    if (isset($item['visibility']) && $item['visibility'] === false) {
        return '';
    }
    
    $hasChildren = !empty($item['items']);
    $isActive = isset($item['active']) && $item['active'];
    $url = isset($item['url']) ? Url::to($item['url']) : '#';
    $icon = isset($item['icon']) ? $item['icon'] : 'fa-circle';
    $label = $item['label'] ?? '';
    
    $badgeHtml = '';
    if (!empty($item['badgeSuccess'])) {
        $badgeHtml = '<span class="ml-auto bg-green-500 text-white text-xs px-2 py-0.5 rounded-full">' . $item['badgeSuccess'] . '</span>';
    } elseif (!empty($item['badgeDanger'])) {
        $badgeHtml = '<span class="ml-auto bg-red-500 text-white text-xs px-2 py-0.5 rounded-full">' . $item['badgeDanger'] . '</span>';
    } elseif (!empty($item['badgeWarning'])) {
        $badgeHtml = '<span class="ml-auto bg-yellow-500 text-white text-xs px-2 py-0.5 rounded-full"><i class="fas fa-info-circle"></i></span>';
    }
    
    $itemClass = 'sidebar-menu-item';
    $linkClass = 'sidebar-menu-link flex items-center gap-3 px-3 py-2 rounded-md text-gray-400 no-underline transition-colors group';
    
    if ($isActive) {
        $linkClass .= ' bg-blue-600 text-white';
    } else {
        $linkClass .= ' hover:bg-[hsl(0_0%_25%_/_1)] hover:text-white';
    }
    
    if ($level > 0) {
        $linkClass .= ' pl-8';
    }
    
    $html = '<li class="' . $itemClass . ($isActive ? ' active' : '') . '">';
    
    if ($hasChildren) {
        $html .= '<div class="sidebar-menu-group">';
        $html .= '<a href="' . $url . '" class="' . $linkClass . '" data-toggle="submenu">';
        $html .= '<i class="' . $icon . ' w-5 text-center flex-shrink-0"></i>';
        $html .= '<span class="flex-1 sidebar-menu-text">' . Html::encode($label) . '</span>';
        $html .= $badgeHtml;
        $html .= '<i class="fas fa-chevron-down ml-auto sidebar-menu-arrow transition-transform text-xs flex-shrink-0"></i>';
        $html .= '</a>';
        $html .= '<ul class="sidebar-submenu hidden pl-0 m-0 list-none mt-1">';
        foreach ($item['items'] as $subItem) {
            if (!isset($subItem['visibility']) || $subItem['visibility'] !== false) {
                $html .= renderMenuItem($subItem, $level + 1);
            }
        }
        $html .= '</ul>';
        $html .= '</div>';
    } else {
        $html .= '<a href="' . $url . '" class="' . $linkClass . '">';
        $html .= '<i class="' . $icon . ' w-5 text-center flex-shrink-0"></i>';
        $html .= '<span class="flex-1 sidebar-menu-text">' . Html::encode($label) . '</span>';
        $html .= $badgeHtml;
        $html .= '</a>';
    }
    
    $html .= '</li>';
    return $html;
}
?>

<!-- Modern Sidebar -->
<aside class="admin-sidebar-content bg-[hsl(0_0%_20.4%_/_1)] border-r border-[hsl(0_0%_15.3%_/_1)] h-full overflow-y-auto scrollbar-thin flex flex-col w-[250px] transition-all duration-300" id="main-sidebar">
    <!-- Logo and Collapse Button -->
    <div class="flex items-center justify-between p-4 border-b border-[hsl(0_0%_15.3%_/_1)] flex-shrink-0">
        <a href="<?= Url::to(['/']) ?>" class="flex items-center gap-2 text-white no-underline sidebar-brand">
            <?php if (Yii::$app->settings->get('design_logo')): ?>
                <img src="<?= Yii::$app->settings->get('design_logo') ?>" alt="<?= Yii::$app->settings->get('site_title') ?>" class="h-8 w-auto sidebar-logo">
            <?php else: ?>
                <span class="text-lg font-semibold sidebar-text"><?= Html::encode(Yii::$app->settings->get('site_title') ?: 'Prostoj') ?></span>
            <?php endif; ?>
        </a>
        <button 
            class="ds-btn ds-btn--icon ds-btn--ghost sidebar-toggle-btn flex-shrink-0" 
            id="sidebar-collapse-btn"
            aria-label="Свернуть меню"
            title="Свернуть меню"
        >
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <!-- Menu Sections -->
    <nav class="p-2 pt-2 flex-1 overflow-y-auto scrollbar-thin">
        <ul class="list-none p-0 m-0">
            <?php if (!empty($moderationItems)): ?>
                <!-- Модерация Section -->
                <li class="mb-4">
                    <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide sidebar-section-title">
                        Модерация
                    </div>
                    <ul class="list-none p-0 m-0 mt-1">
                        <?php foreach ($moderationItems as $item): ?>
                            <?= renderMenuItem($item) ?>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endif; ?>

            <?php if (!empty($managementItems)): ?>
                <!-- Управление Section -->
                <li class="mb-4">
                    <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide sidebar-section-title">
                        Управление
                    </div>
                    <ul class="list-none p-0 m-0 mt-1">
                        <?php foreach ($managementItems as $item): ?>
                            <?= renderMenuItem($item) ?>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endif; ?>

            <?php if (!empty($projectItems)): ?>
                <!-- Проект Section -->
                <li class="mb-4">
                    <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide sidebar-section-title">
                        Проект
                    </div>
                    <ul class="list-none p-0 m-0 mt-1">
                        <?php foreach ($projectItems as $item): ?>
                            <?= renderMenuItem($item) ?>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Bottom Actions -->
    <div class="p-3 border-t border-[hsl(0_0%_15.3%_/_1)] flex-shrink-0 sidebar-bottom-actions">
        <div class="flex items-center justify-around gap-2">
            <a href="#" class="ds-btn ds-btn--icon ds-btn--ghost" title="Документация">
                <i class="fas fa-file-alt"></i>
            </a>
            <a href="#" class="ds-btn ds-btn--icon ds-btn--ghost" title="Чат">
                <i class="fas fa-comments"></i>
            </a>
            <a href="#" class="ds-btn ds-btn--icon ds-btn--ghost" title="Поддержка">
                <i class="fas fa-headset"></i>
            </a>
            <?= Html::a(
                '<i class="fas fa-sign-out-alt"></i>',
                ['/site/logout'],
                [
                    'data-method' => 'post',
                    'class' => 'ds-btn ds-btn--icon ds-btn--ghost',
                    'title' => 'Выход',
                    'aria-label' => 'Выход'
                ]
            ) ?>
        </div>
    </div>
</aside>

<style>
/* Sidebar collapse styles */
.admin-sidebar-content {
  transition: width 0.3s ease;
  width: 250px;
  height: 100%;
}

.admin-sidebar-content.sidebar-collapsed {
  width: 70px;
}

.admin-sidebar-content.sidebar-collapsed .sidebar-logo,
.admin-sidebar-content.sidebar-collapsed .sidebar-text,
.admin-sidebar-content.sidebar-collapsed .sidebar-bottom-actions,
.admin-sidebar-content.sidebar-collapsed .sidebar-section-title,
.admin-sidebar-content.sidebar-collapsed .sidebar-menu-text,
.admin-sidebar-content.sidebar-collapsed .sidebar-menu-arrow,
.admin-sidebar-content.sidebar-collapsed .ml-auto:not(.sidebar-toggle-btn) {
  display: none !important;
}

.admin-sidebar-content.sidebar-collapsed .sidebar-toggle-btn i {
  transform: rotate(180deg);
}

.admin-sidebar-content.sidebar-collapsed nav .sidebar-menu-link {
  justify-content: center;
  padding: 0.75rem;
  width: 100%;
}

.admin-sidebar-content.sidebar-collapsed nav .sidebar-menu-link i {
  margin: 0;
}

.admin-sidebar-content.sidebar-collapsed .sidebar-brand {
  justify-content: center;
}

/* Active menu item styles */
.sidebar-menu-link.active {
  background: hsl(200 70% 50% / 1) !important;
  color: white !important;
}

.sidebar-menu-link:hover:not(.active) {
  background: hsl(0 0% 25% / 1);
  color: white;
}

/* Submenu styles */
.sidebar-submenu {
  padding-left: 0.5rem;
}

.sidebar-submenu .sidebar-menu-link {
  padding-left: 2.5rem;
  font-size: 0.875rem;
}

.sidebar-menu-group.menu-open .sidebar-menu-arrow {
  transform: rotate(180deg);
}
</style>

<script>
// Sidebar collapse functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('main-sidebar');
    const sidebarWrapper = sidebar?.closest('.sidebar-wrapper');
    const collapseBtn = document.getElementById('sidebar-collapse-btn');
    const layoutGrid = document.getElementById('admin-layout-grid');
    const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
    
    if (isCollapsed) {
        sidebar?.classList.add('sidebar-collapsed');
        if (layoutGrid) {
            layoutGrid.classList.add('sidebar-collapsed');
        }
    }
    
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function() {
            sidebar?.classList.toggle('sidebar-collapsed');
            if (layoutGrid) {
                layoutGrid.classList.toggle('sidebar-collapsed');
            }
            localStorage.setItem('sidebar-collapsed', sidebar?.classList.contains('sidebar-collapsed') || false);
        });
    }

    // Sidebar submenu toggle
    document.querySelectorAll('.sidebar-menu-link[data-toggle="submenu"]').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const group = this.closest('.sidebar-menu-group');
            const submenu = group.querySelector('.sidebar-submenu');
            const arrow = this.querySelector('.sidebar-menu-arrow');
            
            if (submenu) {
                submenu.classList.toggle('hidden');
                if (arrow) arrow.classList.toggle('rotate-180');
                group.classList.toggle('menu-open');
            }
        });
    });
    
    // Auto-expand active menu groups
    document.querySelectorAll('.sidebar-menu-item.active').forEach(function(item) {
        const group = item.closest('.sidebar-menu-group');
        if (group) {
            const submenu = group.querySelector('.sidebar-submenu');
            const arrow = group.querySelector('.sidebar-menu-arrow');
            if (submenu) {
                submenu.classList.remove('hidden');
                if (arrow) arrow.classList.add('rotate-180');
                group.classList.add('menu-open');
            }
        }
    });
});
</script>
