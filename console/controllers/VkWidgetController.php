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
                // Проверяем наличие токена
                $accessToken = $widget->decryptToken();
                if (!$accessToken) {
                    $accessToken = Yii::$app->settings->get('vk_app_sever_key');
                }
                
                if (!$accessToken) {
                    $this->stderr("✗ Ошибка: отсутствует токен доступа для группы {$widget->group_id}.\n");
                    $this->stderr("   Убедитесь, что токен сохранен в БД или установлен vk_app_sever_key в настройках.\n");
                    $errorCount++;
                    continue;
                }
                
                // Проверяем наличие API URL
                $apiUrl = $widget->api_url ?: (Yii::$app->params['api_url'] ?? 'https://api.' . Yii::$app->settings->get('site_domain') . '/servers');
                $this->stdout("   API URL: {$apiUrl}\n");
                
                // Используем рефлексию для вызова защищенного метода с verbose режимом
                $method = new ReflectionMethod($apiController, 'updateWidgetByModel');
                $method->setAccessible(true);
                
                if ($method->invoke($apiController, $widget, true)) {
                    $this->stdout("✓ Виджет для группы {$widget->group_id} успешно обновлен.\n");
                    $successCount++;
                } else {
                    $this->stderr("✗ Ошибка при обновлении виджета для группы {$widget->group_id}.\n");
                    $this->stderr("   Проверьте логи приложения для деталей.\n");
                    $errorCount++;
                }
            } catch (\Exception $e) {
                $this->stderr("✗ Исключение при обновлении виджета для группы {$widget->group_id}:\n");
                $this->stderr("   " . $e->getMessage() . "\n");
                if ($e->getTraceAsString()) {
                    $this->stderr("   " . $e->getFile() . ":" . $e->getLine() . "\n");
                }
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
            
            // Используем рефлексию для вызова защищенного метода с verbose режимом
            $method = new ReflectionMethod($apiController, 'updateWidgetByModel');
            $method->setAccessible(true);
            
            if ($method->invoke($apiController, $widget, true)) {
                $this->stdout("✓ Виджет успешно обновлен.\n");
                return ExitCode::OK;
            } else {
                $this->stderr("✗ Ошибка при обновлении виджета.\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }
        } catch (\Exception $e) {
            $this->stderr("✗ Исключение при обновлении виджета:\n");
            $this->stderr("   " . $e->getMessage() . "\n");
            $this->stderr("   Файл: " . $e->getFile() . ":" . $e->getLine() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Сохранить токен доступа сообщества для виджета
     * 
     * Пример: php yii vk-widget/save-token 12345678 "vk1.a.token_here"
     * 
     * @param int $groupId ID сообщества ВК
     * @param string $token Ключ доступа сообщества
     * @return int
     */
    public function actionSaveToken($groupId, $token)
    {
        if (empty($token)) {
            $this->stderr("Ошибка: токен не указан.\n");
            $this->stdout("Использование: php yii vk-widget/save-token <group_id> <token>\n");
            $this->stdout("Пример: php yii vk-widget/save-token 12345678 \"vk1.a.token_here\"\n");
            return ExitCode::DATAERR;
        }

        $widget = VkWidget::findOne(['group_id' => $groupId]);

        if (!$widget) {
            $this->stderr("Виджет для группы {$groupId} не найден в БД.\n");
            $this->stdout("Создайте виджет сначала через админ-панель приложения.\n");
            return ExitCode::DATAERR;
        }

        try {
            // Шифруем и сохраняем токен
            $widget->access_token = $widget->encryptToken($token);
            
            if ($widget->save()) {
                $this->stdout("✓ Токен успешно сохранен для группы {$groupId}.\n");
                $this->stdout("Теперь автоматическое обновление через cron будет работать.\n");
                return ExitCode::OK;
            } else {
                $this->stderr("✗ Ошибка при сохранении токена:\n");
                foreach ($widget->errors as $field => $errors) {
                    $this->stderr("   {$field}: " . implode(', ', $errors) . "\n");
                }
                return ExitCode::DATAERR;
            }
        } catch (\Exception $e) {
            $this->stderr("✗ Исключение: " . $e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}

