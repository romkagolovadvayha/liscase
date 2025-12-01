<?php

namespace console\controllers;

use api\controllers\VkWidgetController as ApiVkWidgetController;
use common\models\vk\VkWidget;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use ReflectionMethod;

/**
 * Консольный контроллер для управления виджетами ВК
 * Использует методы из API контроллера
 */
class VkWidgetController extends Controller
{
    /**
     * Обновить все активные виджеты ВК
     * 
     * Пример: php yii vk-widget/update-all
     * 
     * @return int
     */
    public function actionUpdateAll()
    {
        $widgets = VkWidget::getActiveWidgets();
        
        if (empty($widgets)) {
            $this->stdout("Нет активных виджетов для обновления.\n");
            return ExitCode::OK;
        }

        $this->stdout("Найдено " . count($widgets) . " активных виджетов.\n");

        $apiController = new ApiVkWidgetController('vk-widget', Yii::$app);
        
        $successCount = 0;
        $errorCount = 0;

        foreach ($widgets as $widget) {
            $this->stdout("Обновление виджета для группы {$widget->group_id}...\n");
            
            try {
                // Используем рефлексию для вызова защищенного метода
                $method = new ReflectionMethod($apiController, 'updateWidgetByModel');
                $method->setAccessible(true);
                
                if ($method->invoke($apiController, $widget)) {
                    $this->stdout("✓ Виджет для группы {$widget->group_id} успешно обновлен.\n");
                    $successCount++;
                } else {
                    $this->stderr("✗ Ошибка при обновлении виджета для группы {$widget->group_id}.\n");
                    $errorCount++;
                }
            } catch (\Exception $e) {
                $this->stderr("✗ Исключение при обновлении виджета для группы {$widget->group_id}: " . $e->getMessage() . "\n");
                $errorCount++;
            }
        }

        $this->stdout("\nИтого: успешно обновлено {$successCount}, ошибок: {$errorCount}.\n");

        return $errorCount > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * Обновить конкретный виджет по group_id
     * 
     * Пример: php yii vk-widget/update 12345678
     * 
     * @param int $groupId ID сообщества ВК
     * @return int
     */
    public function actionUpdate($groupId)
    {
        $widget = VkWidget::findOne(['group_id' => $groupId, 'status' => VkWidget::STATUS_ACTIVE]);
        
        if (!$widget) {
            $this->stderr("Виджет для группы {$groupId} не найден или неактивен.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Обновление виджета для группы {$groupId}...\n");

        try {
            $apiController = new ApiVkWidgetController('vk-widget', Yii::$app);
            
            // Используем рефлексию для вызова защищенного метода
            $method = new ReflectionMethod($apiController, 'updateWidgetByModel');
            $method->setAccessible(true);
            
            if ($method->invoke($apiController, $widget)) {
                $this->stdout("✓ Виджет успешно обновлен.\n");
                return ExitCode::OK;
            } else {
                $this->stderr("✗ Ошибка при обновлении виджета.\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }
        } catch (\Exception $e) {
            $this->stderr("✗ Исключение: " . $e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}

