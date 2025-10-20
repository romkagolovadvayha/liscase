<?php
namespace console\controllers;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Консольная команда для управления настройками
 * 
 * Использование:
 * php yii settings/update           - обновление настроек и генерация цветов
 * php yii settings/gen-colors       - только генерация цветов
 * php yii settings/refresh          - только обновление настроек
 */
class SettingsController extends Controller
{
    /**
     * Обновляет настройки и генерирует цвета
     * Основная команда для выполнения после деплоя
     * 
     * @return int Exit code
     */
    public function actionUpdate()
    {
        $this->stdout("🔧 Обновление настроек системы...\n\n", \yii\helpers\Console::FG_CYAN);

        // 1. Генерация цветов
        $this->stdout("🎨 Генерация цветов...\n", \yii\helpers\Console::FG_YELLOW);
        try {
            Yii::$app->settings->genColors();
            $this->stdout("   ✅ Цвета сгенерированы\n", \yii\helpers\Console::FG_GREEN);
        } catch (\Throwable $e) {
            $this->stderr("   ❌ Ошибка генерации цветов: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // 2. Обновление настроек
        $this->stdout("\n⚙️  Обновление настроек (cache refresh)...\n", \yii\helpers\Console::FG_YELLOW);
        try {
            $settings = Yii::$app->settings->getSettings(true);
            $count = is_array($settings) ? count($settings) : 0;
            $this->stdout("   ✅ Настройки обновлены (загружено: {$count})\n", \yii\helpers\Console::FG_GREEN);
        } catch (\Throwable $e) {
            $this->stderr("   ❌ Ошибка обновления настроек: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("\n✅ Обновление настроек завершено!\n", \yii\helpers\Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Генерирует цвета для настроек
     * 
     * @return int Exit code
     */
    public function actionGenColors()
    {
        $this->stdout("🎨 Генерация цветов...\n", \yii\helpers\Console::FG_CYAN);

        try {
            Yii::$app->settings->genColors();
            $this->stdout("✅ Цвета успешно сгенерированы\n", \yii\helpers\Console::FG_GREEN);
            return ExitCode::OK;
        } catch (\Throwable $e) {
            $this->stderr("❌ Ошибка: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            $this->stderr("   Trace: " . $e->getTraceAsString() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Обновляет настройки (сбрасывает кеш)
     * 
     * @return int Exit code
     */
    public function actionRefresh()
    {
        $this->stdout("⚙️  Обновление настроек...\n", \yii\helpers\Console::FG_CYAN);

        try {
            $settings = Yii::$app->settings->getSettings(true);
            $count = is_array($settings) ? count($settings) : 0;
            
            $this->stdout("✅ Настройки обновлены\n", \yii\helpers\Console::FG_GREEN);
            $this->stdout("   Загружено параметров: {$count}\n", \yii\helpers\Console::FG_GREY);
            
            // Показываем несколько примеров настроек
            if (is_array($settings) && !empty($settings)) {
                $this->stdout("\n📋 Примеры настроек:\n", \yii\helpers\Console::FG_CYAN);
                $counter = 0;
                foreach ($settings as $key => $value) {
                    if ($counter >= 5) break;
                    $displayValue = is_string($value) && strlen($value) > 50 
                        ? substr($value, 0, 50) . '...' 
                        : $value;
                    $this->stdout("   {$key}: " . var_export($displayValue, true) . "\n", \yii\helpers\Console::FG_GREY);
                    $counter++;
                }
            }
            
            return ExitCode::OK;
        } catch (\Throwable $e) {
            $this->stderr("❌ Ошибка: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Показывает информацию о компоненте settings
     * 
     * @return int Exit code
     */
    public function actionInfo()
    {
        $this->stdout("ℹ️  Информация о компоненте settings\n\n", \yii\helpers\Console::FG_CYAN);

        if (!Yii::$app->has('settings')) {
            $this->stderr("❌ Компонент 'settings' не настроен\n", \yii\helpers\Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $settings = Yii::$app->settings;
        $this->stdout("Класс: " . get_class($settings) . "\n", \yii\helpers\Console::FG_GREY);
        
        $methods = get_class_methods($settings);
        $this->stdout("\n📋 Доступные методы:\n", \yii\helpers\Console::FG_CYAN);
        foreach ($methods as $method) {
            if (!str_starts_with($method, '__')) {
                $this->stdout("   - {$method}()\n", \yii\helpers\Console::FG_GREY);
            }
        }

        return ExitCode::OK;
    }
}

