<?php

namespace common\components\yearreview;

use common\models\bans\Bans;
use common\models\skindrops\Skindrops;
use common\models\statistics\Kills;
use common\models\statistics\Reports;
use common\models\statistics\Statistics;
use common\models\user\User;
use common\models\user\UserBox;
use common\models\user\UserRaid;
use Yii;
use yii\base\Component;

/**
 * Компонент для генерации изображения итогов года
 */
class YearReviewGenerator extends Component
{
    /**
     * Получение статистики игрока за все время
     * @param User $user
     * @return array
     */
    public function getPlayerYearStats(User $user): array
    {
        $steamId = $user->steam_id;

        // Оптимизация: получаем все статистики одним запросом
        $statisticsKeys = [
            'playtime', 'kills', 'deaths', 'sulfur.ore', 'wood', 'metal.ore', 
            'stones', 'crate_open', 'barrel'
        ];
        
        $statsData = Statistics::find()
            ->select(['key', 'SUM(value) as total'])
            ->where(['steam_id' => $steamId])
            ->andWhere(['IN', 'key', $statisticsKeys])
            ->groupBy('key')
            ->asArray()
            ->all();
        
        // Инициализируем значения по умолчанию
        $stats = array_fill_keys($statisticsKeys, 0);
        $wipes = 0;
        
        // Обрабатываем результаты
        foreach ($statsData as $row) {
            $key = $row['key'];
            $stats[$key] = (int)$row['total'];
        }
        
        // Количество отыгранных вайпов (отдельный запрос для точности)
        $wipes = Statistics::find()
            ->select('COUNT(DISTINCT `wipe`)')
            ->where(['steam_id' => $steamId])
            ->scalar() ?? 0;

        // Часы на серверах (конвертируем секунды в часы)
        $hours = round($stats['playtime'] / 60);
        
        // Извлекаем значения
        $kills = $stats['kills'];
        $deaths = $stats['deaths'];
        $sulfur = $stats['sulfur.ore'];
        $wood = $stats['wood'];
        $metal = $stats['metal.ore'];
        $stone = $stats['stones'];
        $boxesOpened = $stats['crate_open'];
        $barrelsBroken = $stats['barrel'];

        // Репорты (количество репортов, созданных пользователем)
        $reportsCreated = Reports::find()
            ->where(['steam_id' => $steamId])
            ->count();

        // Забанено благодаря репортам
        // Считаем количество уникальных пользователей, на которых был создан репорт и которые были забанены
        $bansFromReports = 0;
        $reportedSteamIds = Reports::find()
            ->select('recepient_steam_id')
            ->distinct()
            ->where(['steam_id' => $steamId])
            ->column();
        
        if (!empty($reportedSteamIds)) {
            $bansFromReports = Bans::find()
                ->where(['IN', 'steam_id', $reportedSteamIds])
                ->count();
        }

        // Выиграно скинами (сумма price из таблицы skindrops)
        $skindropsWinnings = Skindrops::find()
            ->where(['steam_id' => $steamId])
            ->sum('price') ?? 0;
        $skindropsWinnings = round($skindropsWinnings, 2);
        
        // Если выиграно скинами = 0, считаем количество ежедневных наград из user_box
        $dailyRewardsCount = 0;
        $useDailyRewards = false;
        if ($skindropsWinnings == 0) {
            $dailyRewardsCount = UserBox::find()
                ->where(['user_id' => $user->id])
                ->count();
            $useDailyRewards = true;
        }

        // Зарейдил шкафов (количество рейдов типа 'cupboard')
        $cupboardsRaided = UserRaid::find()
            ->where(['user_id' => $user->id, 'type' => 'cupboard'])
            ->count();

        // Максимальная дистанция убийства (из таблицы kills)
        $maxKillDistance = Kills::find()
            ->where(['steam_id' => $steamId])
            ->andWhere(['!=', 'distance', ''])
            ->andWhere(['IS NOT', 'distance', null])
            ->andWhere(['type' => 'kill']) // Только убийства игроков
            ->max('CAST(distance AS DECIMAL(10,2))') ?? 0;
        $maxKillDistance = round($maxKillDistance);

        return [
            'wipes' => $wipes,
            'hours' => $hours,
            'kills' => $kills,
            'deaths' => $deaths,
            'sulfur' => $sulfur,
            'wood' => $wood,
            'metal' => $metal,
            'stone' => $stone,
            'boxes_opened' => $boxesOpened,
            'barrels_broken' => $barrelsBroken,
            'reports_created' => $reportsCreated,
            'bans_from_reports' => $bansFromReports,
            'skindrops_winnings' => $skindropsWinnings,
            'daily_rewards_count' => $dailyRewardsCount,
            'use_daily_rewards' => $useDailyRewards,
            'cupboards_raided' => $cupboardsRaided,
            'max_kill_distance' => $maxKillDistance,
        ];
    }

    /**
     * Генерация изображения с наложением текста
     * @param string $backgroundPath Путь к фоновому изображению
     * @param array $stats Статистика игрока
     * @param User $user Пользователь
     * @return string Бинарные данные изображения
     * @throws \Exception
     */
    public function generateImage(string $backgroundPath, array $stats, User $user): string
    {
        // Загружаем фоновое изображение
        $image = imagecreatefrompng($backgroundPath);
        if (!$image) {
            throw new \Exception('Не удалось загрузить фоновое изображение');
        }

        // Включаем поддержку альфа-канала для прозрачности
        imagealphablending($image, true);
        imagesavealpha($image, true);
        
        // Форматируем числа с пробелами для разделения тысяч
        $formatNumber = function($number) {
            return number_format($number, 0, '', ' ');
        };

        // Получаем размеры изображения
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        // Создаем цвета (можно использовать как строковые ключи, так и hex)
        $colors = [
            'white' => imagecolorallocate($image, 255, 255, 255),
            'red' => imagecolorallocate($image, 255, 0, 0),
        ];
        
        // Функция для получения цвета (поддерживает hex и строковые ключи)
        $getColor = function($colorValue) use ($image, &$colors) {
            // Если это hex цвет (начинается с #)
            if (is_string($colorValue) && strpos($colorValue, '#') === 0) {
                // Проверяем, не создавали ли мы уже этот цвет
                if (!isset($colors[$colorValue])) {
                    $hex = ltrim($colorValue, '#');
                    // Поддерживаем как 6-символьный, так и 3-символьный hex
                    if (strlen($hex) === 3) {
                        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
                    }
                    $r = hexdec(substr($hex, 0, 2));
                    $g = hexdec(substr($hex, 2, 2));
                    $b = hexdec(substr($hex, 4, 2));
                    $colors[$colorValue] = imagecolorallocate($image, $r, $g, $b);
                }
                return $colors[$colorValue];
            }
            // Если это строковый ключ (white, red и т.д.)
            return $colors[$colorValue] ?? $colors['white'];
        };

        // Координаты X для колонок
        $columnLeftX = 60;      // Левая колонка
        $columnMiddleX = 400;   // Средняя колонка
        $columnThirdX = 740;    // Третья колонка
        $columnRightX = 1350;    // Правая колонка ресурсов (первая)
        $columnRightX2 = 1670;   // Правая колонка ресурсов (вторая)

        // Общие параметры для основных статистик (левая и средняя колонки)
        $statsNumberFontSize = 56;        // Размер шрифта для цифр
        $statsTextFontSize = 18;          // Размер шрифта для текста
        $statsNumberColor = Yii::$app->settings->get('colors_text-main');    // Цвет для цифр
        $statsTextColor = Yii::$app->settings->get('colors_year_review_stats');      // Цвет для текста
        $statsNumberFontWeight = 'bold';  // Жирность для цифр
        $statsTextFontWeight = 'medium';  // Жирность для текста
        $statsOffsetYBetween = -15;         // Отступ Y между числом и текстом
        $statsOffsetYNext = 110;           // Отступ Y между строками

        // Общие параметры для ресурсов (правая колонка)
        $resourcesNumberFontSize = 32;        // Размер шрифта для цифр
        $resourcesTextFontSize = 16;          // Размер шрифта для текста
        $resourcesNumberColor = Yii::$app->settings->get('colors_text-main');    // Цвет для цифр
        $resourcesTextColor = Yii::$app->settings->get('colors_year_review_stats_resources');      // Цвет для текста
        $resourcesNumberFontWeight = 'bold';  // Жирность для цифр
        $resourcesTextFontWeight = 'medium';  // Жирность для текста
        $resourcesOffsetYBetween = -5;         // Отступ Y между числом и текстом
        $resourcesOffsetYNext = 70;           // Отступ Y между строками

        // Конфигурация текстовых элементов
        // Каждый элемент: [текст, размер_шрифта, цвет, позиция_X, позиция_Y]
        // Для динамических значений используем ключи из $stats
        $textElements = [
            // Заголовок "ИТОГИ ГОДА"
            [
                'text' => 'ИТОГИ ГОДА',
                'fontSize' => 48,
                'color' => Yii::$app->settings->get('colors_text-main'),
                'fontWeight' => 'bold', // Жирный шрифт
                'x' => 60,
                'y' => 110,
            ],
            // Никнейм пользователя (будет рассчитано динамически после "ИТОГИ ГОДА")
            [
                'text' => $user->username,
                'fontSize' => 48,
                'color' => Yii::$app->settings->get('colors_primary-colors-main'),
                'fontWeight' => 'bold', // Жирный шрифт
                'x' => 'auto', // Будет рассчитано после предыдущего элемента
                'y' => 110,
                'offsetX' => 20, // Отступ от предыдущего элемента
            ],
            
            // Левая колонка - основные статистики
            [
                'text' => $formatNumber($stats['wipes']),
                'fontSize' => $statsNumberFontSize,
                'color' => $statsNumberColor,
                'fontWeight' => $statsNumberFontWeight,
                'x' => $columnLeftX,
                'y' => 250,
            ],
            [
                'text' => 'Отыгранных вайпа',
                'fontSize' => $statsTextFontSize,
                'color' => $statsTextColor,
                'fontWeight' => $statsTextFontWeight,
                'x' => $columnLeftX,
                'y' => 'auto',
                'offsetY' => $statsOffsetYBetween,
            ],
            
            [
                'text' => $formatNumber($stats['kills']),
                'fontSize' => $statsNumberFontSize,
                'color' => $statsNumberColor,
                'fontWeight' => $statsNumberFontWeight,
                'x' => $columnLeftX,
                'y' => 'auto',
                'offsetY' => $statsOffsetYNext,
            ],
            [
                'text' => 'Ты убил игроков',
                'fontSize' => $statsTextFontSize,
                'color' => $statsTextColor,
                'fontWeight' => $statsTextFontWeight,
                'x' => $columnLeftX,
                'y' => 'auto',
                'offsetY' => $statsOffsetYBetween,
            ],
            
            [
                'text' => $formatNumber($stats['bans_from_reports']),
                'fontSize' => $statsNumberFontSize,
                'color' => $statsNumberColor,
                'fontWeight' => $statsNumberFontWeight,
                'x' => $columnLeftX,
                'y' => 'auto',
                'offsetY' => $statsOffsetYNext,
            ],
            [
                'text' => "Человек забаннено \nблагодаря твоему репорту",
                'fontSize' => $statsTextFontSize,
                'color' => $statsTextColor,
                'fontWeight' => $statsTextFontWeight,
                'x' => $columnLeftX,
                'y' => 'auto',
                'offsetY' => $statsOffsetYBetween,
            ],
            
            // Средняя колонка - основные статистики
            [
                'text' => $formatNumber($stats['hours']),
                'fontSize' => $statsNumberFontSize,
                'color' => $statsNumberColor,
                'fontWeight' => $statsNumberFontWeight,
                'x' => $columnMiddleX,
                'y' => 300,
            ],
            [
                'text' => 'Часов на наших серверах',
                'fontSize' => $statsTextFontSize,
                'color' => $statsTextColor,
                'fontWeight' => $statsTextFontWeight,
                'x' => $columnMiddleX,
                'y' => 'auto',
                'offsetY' => $statsOffsetYBetween,
            ],
            
            [
                'text' => $formatNumber($stats['deaths']),
                'fontSize' => $statsNumberFontSize,
                'color' => $statsNumberColor,
                'fontWeight' => $statsNumberFontWeight,
                'x' => $columnMiddleX,
                'y' => 'auto',
                'offsetY' => $statsOffsetYNext,
            ],
            [
                'text' => 'Тебя убили другие игроки',
                'fontSize' => $statsTextFontSize,
                'color' => $statsTextColor,
                'fontWeight' => $statsTextFontWeight,
                'x' => $columnMiddleX,
                'y' => 'auto',
                'offsetY' => $statsOffsetYBetween,
            ],
            
            [
                'text' => $formatNumber($stats['reports_created']),
                'fontSize' => $statsNumberFontSize,
                'color' => $statsNumberColor,
                'fontWeight' => $statsNumberFontWeight,
                'x' => $columnMiddleX,
                'y' => 'auto',
                'offsetY' => $statsOffsetYNext,
            ],
            [
                'text' => "Создал репортов в \nподдержку",
                'fontSize' => $statsTextFontSize,
                'color' => $statsTextColor,
                'fontWeight' => $statsTextFontWeight,
                'x' => $columnMiddleX,
                'y' => 'auto',
                'offsetY' => $statsOffsetYBetween,
            ],
            
            // Третья колонка - дополнительные статистики
            [
                'text' => $stats['use_daily_rewards'] 
                    ? $formatNumber($stats['daily_rewards_count'])
                    : number_format($stats['skindrops_winnings'], 0, '', ' ') . ' ₽',
                'fontSize' => $statsNumberFontSize,
                'color' => $statsNumberColor,
                'fontWeight' => $statsNumberFontWeight,
                'x' => $columnThirdX,
                'y' => 350,
            ],
            [
                'text' => $stats['use_daily_rewards'] 
                    ? 'Получено ежедневных наград'
                    : 'Выгранно скинами',
                'fontSize' => $statsTextFontSize,
                'color' => $statsTextColor,
                'fontWeight' => $statsTextFontWeight,
                'x' => $columnThirdX,
                'y' => 'auto',
                'offsetY' => $statsOffsetYBetween,
            ],
            
            [
                'text' => $formatNumber($stats['cupboards_raided']),
                'fontSize' => $statsNumberFontSize,
                'color' => $statsNumberColor,
                'fontWeight' => $statsNumberFontWeight,
                'x' => $columnThirdX,
                'y' => 'auto',
                'offsetY' => $statsOffsetYNext,
            ],
            [
                'text' => 'Зарейдил шкафов',
                'fontSize' => $statsTextFontSize,
                'color' => $statsTextColor,
                'fontWeight' => $statsTextFontWeight,
                'x' => $columnThirdX,
                'y' => 'auto',
                'offsetY' => $statsOffsetYBetween,
            ],
            
            [
                'text' => $formatNumber($stats['max_kill_distance']) . ' м',
                'fontSize' => $statsNumberFontSize,
                'color' => $statsNumberColor,
                'fontWeight' => $statsNumberFontWeight,
                'x' => $columnThirdX,
                'y' => 'auto',
                'offsetY' => $statsOffsetYNext,
            ],
            [
                'text' => 'Максимальная дистанция убийства',
                'fontSize' => $statsTextFontSize,
                'color' => $statsTextColor,
                'fontWeight' => $statsTextFontWeight,
                'x' => $columnThirdX,
                'y' => 'auto',
                'offsetY' => $statsOffsetYBetween,
            ],
            
            // Правая колонка - ресурсы (правый верхний угол, первая колонка)
            [
                'text' => $formatNumber($stats['sulfur']),
                'fontSize' => $resourcesNumberFontSize,
                'color' => $resourcesNumberColor,
                'fontWeight' => $resourcesNumberFontWeight,
                'x' => $columnRightX,
                'y' => 140,
            ],
            [
                'text' => 'Серная руда',
                'fontSize' => $resourcesTextFontSize,
                'color' => $resourcesTextColor,
                'fontWeight' => $resourcesTextFontWeight,
                'x' => $columnRightX,
                'y' => 'auto',
                'offsetY' => $resourcesOffsetYBetween,
            ],
            
            [
                'text' => $formatNumber($stats['metal']),
                'fontSize' => $resourcesNumberFontSize,
                'color' => $resourcesNumberColor,
                'fontWeight' => $resourcesNumberFontWeight,
                'x' => $columnRightX,
                'y' => 'auto',
                'offsetY' => $resourcesOffsetYNext,
            ],
            [
                'text' => 'Железная руда',
                'fontSize' => $resourcesTextFontSize,
                'color' => $resourcesTextColor,
                'fontWeight' => $resourcesTextFontWeight,
                'x' => $columnRightX,
                'y' => 'auto',
                'offsetY' => $resourcesOffsetYBetween,
            ],
            
            [
                'text' => $formatNumber($stats['stone']),
                'fontSize' => $resourcesNumberFontSize,
                'color' => $resourcesNumberColor,
                'fontWeight' => $resourcesNumberFontWeight,
                'x' => $columnRightX,
                'y' => 'auto',
                'offsetY' => $resourcesOffsetYNext,
            ],
            [
                'text' => 'Камни',
                'fontSize' => $resourcesTextFontSize,
                'color' => $resourcesTextColor,
                'fontWeight' => $resourcesTextFontWeight,
                'x' => $columnRightX,
                'y' => 'auto',
                'offsetY' => $resourcesOffsetYBetween,
            ],
            
            // Правая колонка - ресурсы (правый верхний угол, вторая колонка)
            [
                'text' => $formatNumber($stats['wood']),
                'fontSize' => $resourcesNumberFontSize,
                'color' => $resourcesNumberColor,
                'fontWeight' => $resourcesNumberFontWeight,
                'x' => $columnRightX2,
                'y' => 140,
            ],
            [
                'text' => 'Дерево',
                'fontSize' => $resourcesTextFontSize,
                'color' => $resourcesTextColor,
                'fontWeight' => $resourcesTextFontWeight,
                'x' => $columnRightX2,
                'y' => 'auto',
                'offsetY' => $resourcesOffsetYBetween,
            ],
            
            [
                'text' => $formatNumber($stats['boxes_opened']),
                'fontSize' => $resourcesNumberFontSize,
                'color' => $resourcesNumberColor,
                'fontWeight' => $resourcesNumberFontWeight,
                'x' => $columnRightX2,
                'y' => 'auto',
                'offsetY' => $resourcesOffsetYNext,
            ],
            [
                'text' => 'Открыто ящиков',
                'fontSize' => $resourcesTextFontSize,
                'color' => $resourcesTextColor,
                'fontWeight' => $resourcesTextFontWeight,
                'x' => $columnRightX2,
                'y' => 'auto',
                'offsetY' => $resourcesOffsetYBetween,
            ],
            
            [
                'text' => $formatNumber($stats['barrels_broken']),
                'fontSize' => $resourcesNumberFontSize,
                'color' => $resourcesNumberColor,
                'fontWeight' => $resourcesNumberFontWeight,
                'x' => $columnRightX2,
                'y' => 'auto',
                'offsetY' => $resourcesOffsetYNext,
            ],
            [
                'text' => 'Разбито бочек',
                'fontSize' => $resourcesTextFontSize,
                'color' => $resourcesTextColor,
                'fontWeight' => $resourcesTextFontWeight,
                'x' => $columnRightX2,
                'y' => 'auto',
                'offsetY' => $resourcesOffsetYBetween,
            ],
            
            // Нижний текст (описание)
            [
                'text' => 'Эти метрики показывают суммарную статистику за всё время, которое вы провели на наших серверах.',
                'fontSize' => 14,
                'color' => Yii::$app->settings->get('colors_year_review_stats_description'),
                'fontWeight' => 'medium',
                'x' => $columnLeftX,
                'y' => 860,
            ],
            [
                'text' => "Мы собрали ключевые показатели вашей активности: от сыгранных часов и пережитых вайпов до добытых ресурсов, совершённых \nрейдов и максимальной дистанции убийства. Это ваш личный путь в мире Rust — цифры, которые отражают ваш опыт, силу и вклад \nв жизнь проекта.",
                'fontSize' => 14,
                'color' => Yii::$app->settings->get('colors_year_review_stats_description'),
                'fontWeight' => 'medium',
                'x' => $columnLeftX,
                'y' => 'auto',
                'offsetY' => 30,
            ],
            [
                'text' => "© 2025 " . Yii::$app->settings->get('site_domain'),
                'fontSize' => 14,
                'color' => Yii::$app->settings->get('colors_year_review_stats_description'),
                'fontWeight' => 'medium',
                'x' => $columnLeftX,
                'y' => 'auto',
                'offsetY' => 30,
            ],
        ];

        // Отрисовываем элементы
        // Отслеживаем последние позиции для каждой колонки (X координата)
        $lastPositions = [];
        
        foreach ($textElements as $index => $element) {
            $text = $element['text'];
            $fontSize = $element['fontSize'];
            $colorValue = $element['color'];
            $color = $getColor($colorValue);
            
            // Получаем путь к шрифту в зависимости от fontWeight
            $fontWeight = $element['fontWeight'] ?? 'regular';
            $elementFontPath = $this->getFontPath($fontWeight);
            
            // Вычисляем X координату
            if ($element['x'] === 'auto' && isset($element['offsetX'])) {
                // Для никнейма - позиционируем после "ИТОГИ ГОДА"
                $prevElement = $textElements[$index - 1] ?? null;
                if ($prevElement) {
                    $prevFontWeight = $prevElement['fontWeight'] ?? 'regular';
                    $prevFontPath = $this->getFontPath($prevFontWeight);
                    $prevBbox = imagettfbbox($prevElement['fontSize'], 0, $prevFontPath, $prevElement['text']);
                    $prevWidth = abs($prevBbox[4] - $prevBbox[0]);
                    $x = $prevElement['x'] + $prevWidth + ($element['offsetX'] ?? 0);
                } else {
                    $x = 0;
                }
            } else {
                $x = $element['x'] === 'auto' ? 0 : $element['x'];
            }
            
            // Вычисляем Y координату
            if ($element['y'] === 'auto') {
                // Ищем последнюю позицию для этой X координаты
                if (isset($lastPositions[$x])) {
                    $y = $lastPositions[$x]['y'] + ($element['offsetY'] ?? 0);
                } else {
                    // Если это первый элемент в колонке, используем значение по умолчанию
                    $y = 200;
                }
            } else {
                $y = $element['y'];
            }
            
            // Добавляем текст
            $this->addText($image, $text, $x, $y, $fontSize, $color, $elementFontPath, true);
            
            // Сохраняем данные для следующего элемента в этой колонке
            $bbox = imagettfbbox($fontSize, 0, $elementFontPath, $text);
            $textHeight = abs($bbox[7] - $bbox[1]);
            
            // Обновляем последнюю позицию для этой колонки
            $lastPositions[$x] = [
                'y' => $y + $textHeight,
                'height' => $textHeight,
            ];
        }

        // Выводим изображение в буфер
        ob_start();
        imagepng($image);
        $imageData = ob_get_contents();
        ob_end_clean();

        // Освобождаем память
        imagedestroy($image);

        return $imageData;
    }

    /**
     * Получение пути к шрифту с поддержкой кириллицы
     * @param string $weight Вес шрифта: 'regular', 'bold', 'medium', 'semiBold', 'light', 'thin'
     * @return string
     * @throws \Exception
     */
    private function getFontPath($weight = 'regular'): string
    {
        // Маппинг весов шрифта на имена файлов Roboto_Condensed
        $weightMap = [
            'regular' => 'RobotoCondensed-Regular',
            'bold' => 'RobotoCondensed-Bold',
            'medium' => 'RobotoCondensed-Medium',
            'semiBold' => 'RobotoCondensed-SemiBold',
            'light' => 'RobotoCondensed-Light',
            'thin' => 'RobotoCondensed-Thin',
            'black' => 'RobotoCondensed-Black',
            'extraBold' => 'RobotoCondensed-ExtraBold',
        ];
        
        $fontName = $weightMap[$weight] ?? $weightMap['regular'];
        $fontFile = $fontName . '.ttf';
        
        // Получаем базовый путь к frontend директории
        $frontendPath = Yii::getAlias('@frontend');
        
        // Основной путь к шрифтам из frontend/assets/fonts/Roboto_Condensed
        $fontPath = $frontendPath . '/assets/fonts/Roboto_Condensed/static/' . $fontFile;
        
        // Альтернативные пути к шрифтам
        $possibleFonts = [
            $fontPath,
            // Если нужного веса нет, пробуем Regular
            $frontendPath . '/assets/fonts/Roboto_Condensed/static/RobotoCondensed-Regular.ttf',
            // Fallback на обычный Roboto
            $frontendPath . '/assets/fonts/Roboto/static/Roboto-Regular.ttf',
            // Пути через алиасы (на случай если прямой путь не работает)
            Yii::getAlias('@frontend/assets/fonts/Roboto_Condensed/static/' . $fontFile),
            Yii::getAlias('@frontend/assets/fonts/Roboto_Condensed/static/RobotoCondensed-Regular.ttf'),
            // Пути через web (если шрифты опубликованы)
            Yii::getAlias('@frontend/web/assets/fonts/Roboto_Condensed/static/' . $fontFile),
            Yii::getAlias('@frontend/web/assets/fonts/Roboto_Condensed/static/RobotoCondensed-Regular.ttf'),
            // Старые пути для совместимости
            Yii::getAlias('@frontend/web/assets/sources/css/fonts/Roboto-Regular.ttf'),
            Yii::getAlias('@frontend/web/fonts/DejaVuSans.ttf'),
            Yii::getAlias('@frontend/web/fonts/arial.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            'C:/Windows/Fonts/arial.ttf',
            'C:/Windows/Fonts/times.ttf',
        ];
        
        foreach ($possibleFonts as $font) {
            if (file_exists($font)) {
                return $font;
            }
        }
        
        // Отладочная информация: выводим первый путь для проверки
        $firstPath = $possibleFonts[0] ?? 'unknown';
        throw new \Exception('Не найден шрифт с поддержкой кириллицы. Проверяемый путь: ' . $firstPath . '. Установите Roboto Condensed, Roboto, DejaVu Sans или Arial.');
    }

    /**
     * Добавление текста на изображение
     * @param \GdImage|resource $image Ресурс изображения
     * @param string $text Текст
     * @param int $x Координата X
     * @param int $y Координата Y
     * @param int $fontSize Размер шрифта
     * @param int $color Цвет
     * @param string $fontPath Путь к шрифту
     * @param bool $useTTF Использовать TTF шрифт
     */
    private function addText($image, $text, $x, $y, $fontSize, $color, $fontPath, $useTTF): void
    {
        if ($useTTF && function_exists('imagettftext') && !empty($fontPath)) {
            // Убеждаемся, что текст в UTF-8
            if (!mb_check_encoding($text, 'UTF-8')) {
                $text = mb_convert_encoding($text, 'UTF-8', mb_detect_encoding($text, ['UTF-8', 'Windows-1251', 'ISO-8859-1'], true));
            }
            
            // imagettftext использует координату Y как baseline (нижняя линия текста)
            // Для правильного позиционирования нужно учесть высоту шрифта
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
            $textHeight = abs($bbox[7] - $bbox[1]);
            
            // Корректируем Y для правильного позиционирования
            $adjustedY = $y;
            
            // Выводим текст
            imagettftext($image, $fontSize, 0, $x, $adjustedY, $color, $fontPath, $text);
        } else {
            // Fallback на встроенный шрифт (не поддерживает кириллицу)
            imagestring($image, 5, $x, $y, $text, $color);
        }
    }
}

