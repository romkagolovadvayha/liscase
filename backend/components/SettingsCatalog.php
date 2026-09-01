<?php

namespace backend\components;

use common\models\site\SiteSetting;

/**
 * Единый каталог настроек админки.
 *
 * Категории берутся из БД, поэтому новая категория никогда не теряется из
 * интерфейса. Метаданные ниже отвечают только за понятные названия и
 * группировку.
 */
final class SettingsCatalog
{
    private const GROUPS = [
        'project' => [
            'label' => 'Проект',
            'icon' => 'fa-solid fa-sliders',
            'categories' => ['site', 'section', 'page_main', 'social', 'metrics', 'banSystem'],
        ],
        'appearance' => [
            'label' => 'Внешний вид и контент',
            'icon' => 'fa-solid fa-palette',
            'categories' => ['design', 'colors', 'clans', 'tops'],
        ],
        'commerce' => [
            'label' => 'Платежи и экономика',
            'icon' => 'fa-solid fa-wallet',
            'categories' => ['tinkoffpay', 'trc20', 'ton', 'skinpay', 'telegrampay', 'funpay', 'personal_info_ip', 'referral'],
        ],
        'game' => [
            'label' => 'Игра и сервисы',
            'icon' => 'fa-solid fa-gamepad',
            'categories' => ['skindrops', 'custom-skins', 'rusttm', 'maps', 'battlemetrics', 'rustAdmin', 'steam'],
        ],
        'integrations' => [
            'label' => 'Интеграции и хранилище',
            'icon' => 'fa-solid fa-plug',
            'categories' => ['s3', 'proxy', 'discord', 'vk', 'twitch', 'kick', 'telegramChannel', 'telegram_parser'],
        ],
        'automation' => [
            'label' => 'Боты и уведомления',
            'icon' => 'fa-solid fa-bell',
            'categories' => ['tgbot', 'tgbotAlert', 'tgbotPaymentReport', 'tgbotPayments', 'tgbotRedFlag', 'tgbotReport', 'tgbotSupportAlert', 'maxSupport'],
        ],
        'ai' => [
            'label' => 'Искусственный интеллект',
            'icon' => 'fa-solid fa-wand-magic-sparkles',
            'categories' => ['openAi'],
        ],
    ];

    private const CATEGORIES = [
        'site' => ['label' => 'Основные', 'description' => 'Название, адреса, версия и базовое поведение сайта.', 'icon' => 'fa-solid fa-house'],
        'section' => ['label' => 'Разделы сайта', 'description' => 'Включение и отключение функциональных разделов.', 'icon' => 'fa-solid fa-table-cells-large'],
        'page_main' => ['label' => 'Главная страница', 'description' => 'Содержимое и поведение главной страницы.', 'icon' => 'fa-regular fa-window-maximize'],
        'social' => ['label' => 'Социальные сети', 'description' => 'Публичные ссылки проекта.', 'icon' => 'fa-solid fa-share-nodes'],
        'metrics' => ['label' => 'Метрики', 'description' => 'Счётчики аналитики и технические метрики.', 'icon' => 'fa-solid fa-chart-line'],
        'banSystem' => ['label' => 'Бан-система', 'description' => 'Подключение внешней системы блокировок.', 'icon' => 'fa-solid fa-ban'],
        'design' => ['label' => 'Дизайн', 'description' => 'Логотипы, фон, изображения и визуальные параметры.', 'icon' => 'fa-solid fa-pen-ruler'],
        'colors' => ['label' => 'Цвета темы', 'description' => 'Цветовые токены интерфейса сайта.', 'icon' => 'fa-solid fa-droplet'],
        'clans' => ['label' => 'Кланы', 'description' => 'Правила и лимиты клановой системы.', 'icon' => 'fa-solid fa-people-group'],
        'tops' => ['label' => 'Рейтинги', 'description' => 'Настройки публичных таблиц лидеров.', 'icon' => 'fa-solid fa-ranking-star'],
        'tinkoffpay' => ['label' => 'Тинькофф', 'description' => 'Эквайринг и уведомления Тинькофф.', 'icon' => 'fa-solid fa-credit-card'],
        'trc20' => ['label' => 'TRC20', 'description' => 'Приём платежей в сети TRON.', 'icon' => 'fa-solid fa-coins'],
        'ton' => ['label' => 'TON', 'description' => 'Платежи в сети TON.', 'icon' => 'fa-solid fa-gem'],
        'skinpay' => ['label' => 'Оплата скинами', 'description' => 'Параметры SkinPay.', 'icon' => 'fa-solid fa-shirt'],
        'telegrampay' => ['label' => 'Telegram Pay', 'description' => 'Оплата через Telegram.', 'icon' => 'fa-brands fa-telegram'],
        'funpay' => ['label' => 'FunPay', 'description' => 'Интеграция магазина FunPay.', 'icon' => 'fa-solid fa-store'],
        'personal_info_ip' => ['label' => 'Данные плательщика', 'description' => 'Параметры определения и вывода информации о плательщике.', 'icon' => 'fa-solid fa-user-shield'],
        'referral' => ['label' => 'Реферальная система', 'description' => 'Бонусы, лимиты и условия реферальной программы.', 'icon' => 'fa-solid fa-user-plus'],
        'skindrops' => ['label' => 'Раздача скинов', 'description' => 'Автоматическая выдача и лимиты скинов.', 'icon' => 'fa-solid fa-gift'],
        'custom-skins' => ['label' => 'Кастомные скины', 'description' => 'Параметры пользовательских скинов.', 'icon' => 'fa-solid fa-brush'],
        'rusttm' => ['label' => 'Rust.TM', 'description' => 'Подключение к торговой площадке Rust.TM.', 'icon' => 'fa-solid fa-cart-shopping'],
        'maps' => ['label' => 'Карты', 'description' => 'API и параметры генерации карт.', 'icon' => 'fa-solid fa-map'],
        'battlemetrics' => ['label' => 'BattleMetrics', 'description' => 'Доступ к API BattleMetrics.', 'icon' => 'fa-solid fa-signal'],
        'rustAdmin' => ['label' => 'RustAdmin', 'description' => 'Подключение к RustAdmin API.', 'icon' => 'fa-solid fa-shield-halved'],
        'steam' => ['label' => 'Steam', 'description' => 'Ключи и параметры Steam API.', 'icon' => 'fa-brands fa-steam'],
        's3' => ['label' => 'S3 и CDN', 'description' => 'Хранилище файлов, публичные URL и Swift.', 'icon' => 'fa-solid fa-cloud'],
        'proxy' => ['label' => 'Прокси', 'description' => 'Сетевой прокси для внешних интеграций.', 'icon' => 'fa-solid fa-network-wired'],
        'discord' => ['label' => 'Discord', 'description' => 'OAuth и бот Discord.', 'icon' => 'fa-brands fa-discord'],
        'vk' => ['label' => 'ВКонтакте', 'description' => 'API, callback и публикации ВКонтакте.', 'icon' => 'fa-brands fa-vk'],
        'twitch' => ['label' => 'Twitch', 'description' => 'OAuth и трансляции Twitch.', 'icon' => 'fa-brands fa-twitch'],
        'kick' => ['label' => 'Kick', 'description' => 'OAuth и трансляции Kick.', 'icon' => 'fa-solid fa-video'],
        'telegramChannel' => ['label' => 'Telegram-канал', 'description' => 'Публикации в Telegram-канале.', 'icon' => 'fa-brands fa-telegram'],
        'telegram_parser' => ['label' => 'Telegram-парсер', 'description' => 'Бот, webhook и правила парсинга.', 'icon' => 'fa-solid fa-rss'],
        'tgbot' => ['label' => 'Основной Telegram-бот', 'description' => 'Персональный бот проекта.', 'icon' => 'fa-solid fa-robot'],
        'tgbotAlert' => ['label' => 'Общие оповещения', 'description' => 'Канал для системных уведомлений.', 'icon' => 'fa-solid fa-bell'],
        'tgbotPaymentReport' => ['label' => 'Финансовые отчёты', 'description' => 'Отчёты о платежах в Telegram.', 'icon' => 'fa-solid fa-file-invoice-dollar'],
        'tgbotPayments' => ['label' => 'Оповещения о платежах', 'description' => 'Мгновенные уведомления об оплатах.', 'icon' => 'fa-solid fa-money-check-dollar'],
        'tgbotRedFlag' => ['label' => 'Важные оповещения', 'description' => 'Критические события и красные флаги.', 'icon' => 'fa-solid fa-triangle-exclamation'],
        'tgbotReport' => ['label' => 'Репорты', 'description' => 'Канал для игровых репортов.', 'icon' => 'fa-solid fa-flag'],
        'tgbotSupportAlert' => ['label' => 'Поддержка', 'description' => 'Оповещения о новых обращениях.', 'icon' => 'fa-solid fa-headset'],
        'maxSupport' => ['label' => 'MAX — поддержка', 'description' => 'Бот поддержки и webhook MAX.', 'icon' => 'fa-solid fa-comments'],
        'openAi' => ['label' => 'OpenAI', 'description' => 'Модели, лимиты и доступ к OpenAI API.', 'icon' => 'fa-solid fa-wand-magic-sparkles'],
    ];

    public static function normalizeCategory(?string $category): string
    {
        $category = trim((string) $category);
        $aliases = [
            '' => 'site',
            'general' => 'site',
            'payments' => 'tinkoffpay',
            'bots' => 'tgbot',
        ];

        return $aliases[$category] ?? $category;
    }

    /**
     * @return array<int, array{id:string,label:string,icon:string,categories:array<int,array<string,mixed>>}>
     */
    public static function navigation(): array
    {
        $rows = SiteSetting::find()
            ->select(['category', 'items_count' => 'COUNT(*)'])
            ->groupBy('category')
            ->orderBy(['category' => SORT_ASC])
            ->asArray()
            ->all();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['category']] = (int) $row['items_count'];
        }

        $result = [];
        $assigned = [];
        foreach (self::GROUPS as $id => $group) {
            $categories = [];
            foreach ($group['categories'] as $category) {
                if (!isset($counts[$category])) {
                    continue;
                }
                $categories[] = self::category($category, $counts[$category]);
                $assigned[$category] = true;
            }
            if ($categories !== []) {
                $result[] = [
                    'id' => $id,
                    'label' => $group['label'],
                    'icon' => $group['icon'],
                    'categories' => $categories,
                ];
            }
        }

        $other = [];
        foreach ($counts as $category => $count) {
            if (!isset($assigned[$category])) {
                $other[] = self::category($category, $count);
            }
        }
        if ($other !== []) {
            $result[] = [
                'id' => 'other',
                'label' => 'Другие настройки',
                'icon' => 'fa-solid fa-box-archive',
                'categories' => $other,
            ];
        }

        return $result;
    }

    /** @return array{code:string,label:string,description:string,icon:string,count:int} */
    public static function category(string $category, int $count = 0): array
    {
        $meta = self::CATEGORIES[$category] ?? [];
        return [
            'code' => $category,
            'label' => $meta['label'] ?? self::humanize($category),
            'description' => $meta['description'] ?? 'Системная категория настроек проекта.',
            'icon' => $meta['icon'] ?? 'fa-solid fa-sliders',
            'count' => $count,
        ];
    }

    /** @return array{code:string,label:string,description:string,icon:string,count:int}|null */
    public static function findCategory(array $navigation, string $category): ?array
    {
        foreach ($navigation as $group) {
            foreach ($group['categories'] as $item) {
                if ($item['code'] === $category) {
                    return $item;
                }
            }
        }
        return null;
    }

    public static function firstCategory(array $navigation): string
    {
        return $navigation[0]['categories'][0]['code'] ?? 'site';
    }

    public static function totalCount(array $navigation): int
    {
        $total = 0;
        foreach ($navigation as $group) {
            foreach ($group['categories'] as $category) {
                $total += (int) $category['count'];
            }
        }
        return $total;
    }

    /**
     * Старые настройки часто имеют type=text, хотя фактически содержат секрет.
     * Не выводим такие значения в HTML и сохраняем прежнее значение при пустом поле.
     */
    public static function isSensitive(SiteSetting $setting): bool
    {
        if ($setting->type === 'password') {
            return true;
        }

        $code = strtolower((string) $setting->code);
        if ($code === '') {
            return false;
        }

        foreach (['password', 'passwd', 'secret', 'token', 'apikey', 'api_key', 'accesskey', 'access_key', 'privatekey', 'private_key', 'credential'] as $needle) {
            if (strpos($code, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string,string> */
    public static function typeOptions(): array
    {
        return [
            'text' => 'Короткий текст',
            'longtext' => 'Многострочный текст',
            'number' => 'Число',
            'checkbox' => 'Переключатель',
            'color' => 'Цвет',
            'image' => 'Изображение',
            'video' => 'Видео WebM',
            'file' => 'Файл или путь к файлу',
            'radio' => 'Выбор из вариантов',
            'password' => 'Секретное значение',
        ];
    }

    private static function humanize(string $value): string
    {
        $value = preg_replace('/(?<!^)[A-Z]/', ' $0', str_replace(['_', '-'], ' ', $value));
        return mb_convert_case(trim((string) $value), MB_CASE_TITLE, 'UTF-8');
    }
}
