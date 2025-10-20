<?php
namespace console\controllers;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use common\models\template\Template;

/**
 * Консольная команда для компиляции SCSS
 * 
 * Использование:
 * php yii scss/compile           - компиляция без указания шаблона
 * php yii scss/compile 1          - компиляция для шаблона с ID=1
 * php yii scss/compile --help     - справка
 */
class ScssController extends Controller
{
    /**
     * @var int|null ID шаблона для компиляции
     */
    public $templateId;

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['templateId']);
    }

    /**
     * @inheritdoc
     */
    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            't' => 'templateId',
        ]);
    }

    /**
     * Компилирует SCSS в CSS
     * 
     * @param int|null $templateId ID шаблона (опционально)
     * @return int Exit code
     */
    public function actionCompile($templateId = null)
    {
        // Приоритет: аргумент > опция > null
        $tplId = $templateId ?? $this->templateId;

        $this->stdout("🎨 Компиляция SCSS для шаблона" . ($tplId ? " ID={$tplId}" : " (default)") . "...\n", \yii\helpers\Console::FG_CYAN);

        $result = Template::compileDesignScss($tplId);

        if ($result['success']) {
            $compiler = $result['compiler'] ?? 'unknown';
            $this->stdout("✅ " . $result['message'] . "\n", \yii\helpers\Console::FG_GREEN);
            $this->stdout("   Компилятор: {$compiler}\n", \yii\helpers\Console::FG_GREY);
            return ExitCode::OK;
        } else {
            $this->stderr("❌ " . $result['message'] . "\n", \yii\helpers\Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Показывает информацию о доступных шаблонах
     * 
     * @return int Exit code
     */
    public function actionList()
    {
        $this->stdout("📋 Список доступных шаблонов:\n\n", \yii\helpers\Console::FG_CYAN);

        $templates = Template::find()->orderBy(['id' => SORT_ASC])->all();

        if (empty($templates)) {
            $this->stdout("   Нет доступных шаблонов\n", \yii\helpers\Console::FG_YELLOW);
            return ExitCode::OK;
        }

        foreach ($templates as $tpl) {
            $this->stdout("   [ID: {$tpl->id}] {$tpl->name}\n", \yii\helpers\Console::FG_GREEN);
        }

        $this->stdout("\nИспользование:\n", \yii\helpers\Console::FG_CYAN);
        $this->stdout("   php yii scss/compile [template_id]\n\n");

        return ExitCode::OK;
    }

    /**
     * Компилирует SCSS для всех шаблонов
     * 
     * @return int Exit code
     */
    public function actionCompileAll()
    {
        $this->stdout("🎨 Массовая компиляция SCSS для всех шаблонов...\n\n", \yii\helpers\Console::FG_CYAN);

        $templates = Template::find()->orderBy(['id' => SORT_ASC])->all();

        if (empty($templates)) {
            $this->stdout("⚠️  Нет доступных шаблонов\n", \yii\helpers\Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $success = 0;
        $failed = 0;

        foreach ($templates as $tpl) {
            $this->stdout("Шаблон [{$tpl->id}] {$tpl->name}: ", \yii\helpers\Console::FG_GREY);

            $result = Template::compileDesignScss($tpl->id);

            if ($result['success']) {
                $this->stdout("✅ OK\n", \yii\helpers\Console::FG_GREEN);
                $success++;
            } else {
                $this->stdout("❌ FAIL\n", \yii\helpers\Console::FG_RED);
                $this->stderr("   Ошибка: " . $result['message'] . "\n");
                $failed++;
            }
        }

        $this->stdout("\n📊 Результат:\n", \yii\helpers\Console::FG_CYAN);
        $this->stdout("   Успешно: {$success}\n", \yii\helpers\Console::FG_GREEN);
        if ($failed > 0) {
            $this->stdout("   Ошибок: {$failed}\n", \yii\helpers\Console::FG_RED);
        }

        return $failed > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }
}

