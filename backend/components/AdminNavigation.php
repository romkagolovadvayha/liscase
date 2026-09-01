<?php

namespace backend\components;

use common\components\helpers\Role;
use Yii;

/** Единая task-based навигация backend. */
final class AdminNavigation
{
    /** @return array<int,array<string,mixed>> */
    public static function sections(): array
    {
        $admin = [Role::ROLE_ADMIN];
        $moderation = [Role::ROLE_ADMIN, Role::ROLE_MODERATOR];
        $content = [Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT];
        $badges = self::badges();

        $sections = [
            [
                'label' => 'Работа',
                'items' => [
                    self::item('Обзор', 'fa-solid fa-house', ['/site/index'], ['site'], $content),
                    self::item('Игроки', 'fa-solid fa-users', ['/user/index'], ['user'], $moderation, $badges['users'] ?? null),
                    self::item('Поддержка', 'fa-solid fa-headset', ['/support/index'], ['support'], $admin, $badges['support'] ?? null),
                    self::group('Сообщество', 'fa-solid fa-people-group', [
                        self::item('Кланы', 'fa-solid fa-users-gear', ['/clan/index'], ['clan'], $moderation),
                        self::item('Турниры', 'fa-solid fa-trophy', ['/tournament/index'], ['tournament'], $moderation),
                        self::item('Денежная гонка', 'fa-solid fa-key', ['/cash-race/index'], ['cash-race'], $moderation),
                    ]),
                    self::group('Модерация контента', 'fa-solid fa-shield-halved', [
                        self::item('Постройки', 'fa-solid fa-house-chimney', ['/building/index'], ['building'], $moderation, $badges['buildings'] ?? null),
                        self::item('Свои скины', 'fa-solid fa-palette', ['/server-skin/index'], ['server-skin'], $moderation, $badges['skins'] ?? null),
                        self::item('Видео игроков', 'fa-solid fa-video', ['/video/index'], ['video'], $moderation, $badges['videos'] ?? null),
                        self::item('Предметы игроков', 'fa-solid fa-box-open', ['/user-drop/index'], ['user-drop'], $moderation),
                        self::item('Стикеры поддержки', 'fa-regular fa-face-smile', ['/support-sticker/index'], ['support-sticker'], $moderation),
                        self::item('Рамки аватаров', 'fa-regular fa-square', ['/avatar-frame/index'], ['avatar-frame'], $content),
                    ]),
                    self::group('Аналитика игроков', 'fa-solid fa-chart-simple', [
                        self::item('Статистика', 'fa-solid fa-chart-line', ['/statistics/index'], ['statistics'], $moderation),
                        self::item('Топ игроков', 'fa-solid fa-ranking-star', ['/user-top/index'], ['user-top'], $moderation),
                        self::item('Ежедневные достижения', 'fa-solid fa-medal', ['/achievements-daily/index'], ['achievements-daily'], $admin),
                    ]),
                ],
            ],
            [
                'label' => 'Контент и магазин',
                'items' => [
                    self::group('Каталог', 'fa-solid fa-boxes-stacked', [
                        self::item('Категории', 'fa-solid fa-folder-tree', ['/category/index'], ['category'], $admin),
                        self::item('Предметы', 'fa-solid fa-cube', ['/drop/index'], ['drop', 'drop-drop', 'drop-stat'], $moderation),
                        self::item('Наборы', 'fa-solid fa-layer-group', ['/sets/index'], ['sets', 'sets-drop'], $admin),
                        self::item('Кейсы', 'fa-solid fa-box', ['/box/index'], ['box'], $admin),
                        self::item('Селекты', 'fa-solid fa-list-check', ['/select/index'], ['select'], $admin),
                    ]),
                    self::group('Публикации', 'fa-regular fa-newspaper', [
                        self::item('Блог', 'fa-regular fa-pen-to-square', ['/blog/index'], ['blog', 'blog-image'], $content),
                        self::item('Категории блога', 'fa-solid fa-tags', ['/blog-category/index'], ['blog-category'], $content),
                        self::item('Новости', 'fa-regular fa-newspaper', ['/news/index'], ['news', 'news-content'], $admin),
                    ]),
                ],
            ],
            [
                'label' => 'Серверы',
                'items' => [
                    self::group('Управление серверами', 'fa-solid fa-server', [
                        self::item('Список серверов', 'fa-solid fa-list', ['/servers/index'], ['servers'], $admin, null, ['index', 'create', 'update', 'view']),
                        self::item('Календарь вайпов', 'fa-regular fa-calendar', ['/wipe-calendar/index'], ['wipe-calendar'], $content),
                        self::item('Даты вайпов', 'fa-solid fa-calendar-check', ['/servers/wipe-dates'], ['servers'], $admin, null, ['wipe-dates']),
                        self::item('Правила', 'fa-solid fa-gavel', ['/servers-rules/index'], ['servers-rules'], $content),
                        self::item('Категории правил', 'fa-solid fa-folder-open', ['/servers-rules-category/index'], ['servers-rules-category'], $content),
                        self::item('Серверные радиостанции', 'fa-solid fa-radio', ['/servers-radio-station/index'], ['servers-radio-station'], $moderation),
                        self::item('Карты', 'fa-solid fa-map', ['/map-list/index'], ['map-list', 'map'], $moderation),
                        self::item('Теги серверов', 'fa-solid fa-tags', ['/servers-tags/index'], ['servers-tags'], $admin),
                    ]),
                    self::group('Инструменты серверов', 'fa-solid fa-screwdriver-wrench', [
                        self::item('RCON', 'fa-solid fa-terminal', ['/rcon/index'], ['rcon'], $admin),
                        self::item('FTP-менеджер', 'fa-solid fa-folder-tree', ['/ftp-manager/index'], ['ftp-manager'], $admin, null, ['index', 'view', 'update']),
                        self::item('FTP: все серверы', 'fa-solid fa-cloud-arrow-up', ['/ftp-manager/broadcast'], ['ftp-manager'], $admin, null, ['broadcast']),
                        self::item('Конфиги плагинов', 'fa-solid fa-file-code', ['/rust-plugin-config/index'], ['rust-plugin-config'], $admin),
                        self::item('WIPE-меню', 'fa-solid fa-cloud-sun', ['/wipe/index'], ['wipe'], $admin),
                        self::item('Плагины', 'fa-solid fa-puzzle-piece', ['/plugins/index'], ['plugins'], $admin),
                    ]),
                ],
            ],
            [
                'label' => 'Рост и коммуникации',
                'items' => [
                    self::group('Бонусы и прогресс', 'fa-solid fa-gift', [
                        self::item('Бонусы аудитории', 'fa-solid fa-users', ['/audience-bonus/index'], ['audience-bonus'], $moderation),
                        self::item('Промокоды', 'fa-solid fa-percent', ['/promocode/index'], ['promocode'], $moderation),
                        self::item('Задания', 'fa-solid fa-list-check', ['/tasks-v2/index'], ['tasks-v2'], $moderation),
                        self::item('Старые задания', 'fa-solid fa-list', ['/task/index'], ['task'], $admin),
                        self::item('Battle Pass', 'fa-solid fa-trophy', ['/battle-pass-seasons/index'], ['battle-pass-seasons'], $moderation),
                        self::item('Медали', 'fa-solid fa-medal', ['/medals/index'], ['medals'], $moderation),
                        self::item('Бонусы пополнения', 'fa-solid fa-ruble-sign', ['/payment-bonuses/index'], ['payment-bonuses'], $moderation),
                        self::item('Депозитные бонусы', 'fa-solid fa-coins', ['/deposit-bonus/index'], ['deposit-bonus'], $admin),
                    ]),
                    self::group('Telegram-рассылки', 'fa-brands fa-telegram', [
                        self::item('Конструктор рассылок', 'fa-solid fa-paper-plane', ['/telegram-constructor/index'], ['telegram-constructor'], $admin),
                        self::item('Шаблоны сообщений', 'fa-solid fa-message', ['/telegram-constructor-message/index'], ['telegram-constructor-message'], $admin),
                        self::item('Получатели', 'fa-solid fa-address-book', ['/telegram-recipients/index'], ['telegram-recipients'], $admin),
                    ]),
                    self::group('Отчёты и финансы', 'fa-solid fa-chart-pie', [
                        self::item('Товары', 'fa-solid fa-box', ['/report/products'], ['report'], $admin, null, ['products']),
                        self::item('Пополнения', 'fa-solid fa-wallet', ['/report/deposits'], ['report'], $admin, null, ['deposits']),
                        self::item('Депозиты', 'fa-solid fa-coins', ['/deposit/index'], ['deposit'], $admin),
                        self::item('Сводные отчёты', 'fa-solid fa-table-list', ['/reports/index'], ['reports'], $admin),
                    ]),
                    self::item('Радиостанции сайта', 'fa-solid fa-music', ['/radio/index'], ['radio'], $admin, $badges['radio'] ?? null),
                    self::group('Раздача скинов', 'fa-solid fa-shirt', [
                        self::item('Выданные скины', 'fa-solid fa-hourglass-start', ['/skindrops/index'], ['skindrops'], $admin, null, ['index']),
                        self::item('Отчёт раздачи', 'fa-solid fa-chart-column', ['/skindrops/report'], ['skindrops'], $admin, null, ['report']),
                    ]),
                ],
            ],
            [
                'label' => 'Система',
                'items' => [
                    self::item('Все настройки', 'fa-solid fa-sliders', ['/settings/index'], ['settings'], $admin),
                    self::item('Шаблоны интерфейса', 'fa-solid fa-code', ['/template/index'], ['template'], $admin),
                    self::item('Файлы S3', 'fa-solid fa-cloud', ['/s3-storage/index'], ['s3-storage'], $admin),
                    self::item('Дизайн-система', 'fa-solid fa-palette', ['/design-system/index'], ['design-system'], $admin),
                ],
            ],
        ];

        return self::prepareSections($sections);
    }

    /** @return array<string,mixed> */
    private static function item(string $label, string $icon, array $url, array $controllers, array $roles, ?int $badge = null, array $actions = []): array
    {
        return compact('label', 'icon', 'url', 'controllers', 'roles', 'badge', 'actions');
    }

    /** @return array<string,mixed> */
    private static function group(string $label, string $icon, array $items): array
    {
        return compact('label', 'icon', 'items');
    }

    /** @return array<int,array<string,mixed>> */
    private static function prepareSections(array $sections): array
    {
        $controller = Yii::$app->controller->id;
        $action = Yii::$app->controller->action->id;
        $result = [];
        foreach ($sections as $section) {
            $items = self::prepareItems($section['items'], $controller, $action);
            if ($items !== []) {
                $section['items'] = $items;
                $result[] = $section;
            }
        }
        return $result;
    }

    /** @return array<int,array<string,mixed>> */
    private static function prepareItems(array $items, string $controller, string $action): array
    {
        $result = [];
        foreach ($items as $item) {
            if (isset($item['roles']) && !self::canAny($item['roles'])) {
                continue;
            }
            if (!empty($item['items'])) {
                $item['items'] = self::prepareItems($item['items'], $controller, $action);
                if ($item['items'] === []) {
                    continue;
                }
                $item['active'] = (bool) array_filter($item['items'], static fn(array $child): bool => !empty($child['active']));
            } else {
                $item['active'] = in_array($controller, $item['controllers'] ?? [], true)
                    && ($item['actions'] === [] || in_array($action, $item['actions'], true));
            }
            $result[] = $item;
        }
        return $result;
    }

    private static function canAny(array $roles): bool
    {
        foreach ($roles as $role) {
            if (Yii::$app->user->can($role)) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string,int> */
    private static function badges(): array
    {
        static $badges;
        if ($badges !== null) {
            return $badges;
        }
        $badges = [];
        try {
            $badges['users'] = (int) \common\models\user\User::find()
                ->andWhere(['>=', 'created_at', date('Y-m-d 00:00:01')])
                ->count();
            $badges['buildings'] = (int) \common\models\building\Building::find()
                ->andWhere(['status' => \common\models\building\Building::STATUS_WAIT])
                ->count();
            $badges['skins'] = (int) \common\models\serverskin\ServerSkin::find()
                ->andWhere(['status' => \common\models\serverskin\ServerSkin::STATUS_WAIT])
                ->count();
            $badges['videos'] = (int) \common\models\video\UserVideo::find()
                ->andWhere(['status' => \common\models\video\UserVideo::STATUS_WAIT])
                ->count();
            $badges['radio'] = (int) \common\models\radio\RadioTrack::find()
                ->andWhere(['status' => \common\models\radio\RadioTrack::STATUS_WAIT])
                ->count();
            $badges['support'] = (int) \common\models\support\Support::find()
                ->andWhere(['status' => \common\models\support\Support::STATUS_OPEN])
                ->count();
        } catch (\Throwable $exception) {
            Yii::warning('Не удалось получить счётчики меню: ' . $exception->getMessage(), __METHOD__);
        }
        return $badges;
    }
}
