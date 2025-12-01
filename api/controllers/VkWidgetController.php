<?php

namespace api\controllers;

use common\models\vk\VkWidget;
use yii\web\Controller;
use Yii;
use yii\web\Response;

class VkWidgetController extends Controller
{
    public $enableCsrfValidation = false;

    public function beforeAction($action)
    {
        // Устанавливаем CORS заголовки
        Yii::$app->response->headers->set('Access-Control-Allow-Origin', '*');
        Yii::$app->response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        Yii::$app->response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        Yii::$app->response->headers->set('Access-Control-Max-Age', '3600');
        
        // Обработка preflight запросов
        if (Yii::$app->request->method === 'OPTIONS') {
            Yii::$app->response->statusCode = 200;
            Yii::$app->end();
        }
        
        return parent::beforeAction($action);
    }

    /**
     * Прокси для получения URL сервера загрузки изображений виджета ВК
     * Использует appWidgets.getAppImageUploadServer для коллекции приложения
     * Работает с сервисным ключом доступа приложения (vk_app_sever_key)
     */
    public function actionGetUploadServer()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        // Для коллекции приложения group_id не требуется
        // Используем сервисный ключ из настроек
        $serviceToken = Yii::$app->settings->get('vk_app_sever_key');
        
        if (!$serviceToken) {
            Yii::$app->response->statusCode = 400;
            return [
                'error' => 'Service access token is required. Set vk_app_sever_key in settings.'
            ];
        }
        
        try {
            // Используем метод для коллекции приложения (работает с сервисным ключом)
            $url = "https://api.vk.com/method/appWidgets.getAppImageUploadServer?" .
                   "image_type=24x24" .
                   "&access_token=" . urlencode($serviceToken) .
                   "&v=5.199";
            
            $response = file_get_contents($url);
            $data = json_decode($response, true);
            
            return $data;
        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 500;
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Прокси для загрузки файла на сервер ВК (обход CORS)
     * Принимает файл и URL загрузки, загружает на сервер ВК и возвращает результат
     */
    public function actionUploadImage()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        $uploadUrl = Yii::$app->request->post('upload_url');
        
        // Получаем файл из $_FILES
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Yii::$app->response->statusCode = 400;
            return [
                'error' => 'file is required and must be uploaded successfully'
            ];
        }
        
        if (!$uploadUrl) {
            Yii::$app->response->statusCode = 400;
            return [
                'error' => 'upload_url is required'
            ];
        }
        
        try {
            $filePath = $_FILES['file']['tmp_name'];
            $fileName = $_FILES['file']['name'];
            $fileType = $_FILES['file']['type'] ?: 'image/png';
            
            // Проверяем, что файл существует
            if (!file_exists($filePath)) {
                throw new \Exception('Uploaded file not found');
            }
            
            // Проверяем размер изображения (должно быть 24x24px для виджетов)
            $imageInfo = getimagesize($filePath);
            if ($imageInfo === false) {
                throw new \Exception('Invalid image file - cannot read image dimensions');
            }
            
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $mimeType = $imageInfo['mime'];
            
            // Логируем информацию об изображении
            Yii::info("Image upload: {$fileName}, size: {$width}x{$height}, type: {$mimeType}, file size: " . filesize($filePath) . " bytes", 'vk-widget');
            
            // ВК требует загружать изображения в утроенном размере:
            // для виджета 24x24px нужно загружать 72x72px (24 * 3 = 72)
            $requiredSize = 72;
            if ($width !== $requiredSize || $height !== $requiredSize) {
                Yii::$app->response->statusCode = 400;
                return [
                    'error_code' => 4601,
                    'error_msg' => "Wrong image size: image must be exactly {$requiredSize}x{$requiredSize}px (для виджета 24x24px), got {$width}x{$height}px",
                    'image_width' => $width,
                    'image_height' => $height,
                    'required_size' => $requiredSize
                ];
            }
            
            // Подготавливаем файл для загрузки через CURL
            $cfile = new \CURLFile($filePath, $fileType, $fileName);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $uploadUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => $cfile]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                Yii::error("CURL error during image upload: {$error}", 'vk-widget');
                throw new \Exception('CURL error: ' . $error);
            }
            
            curl_close($ch);
            
            Yii::info("VK upload server response: HTTP {$httpCode}, response: " . substr($response, 0, 200), 'vk-widget');
            
            if ($httpCode !== 200) {
                Yii::error("VK upload failed with HTTP code: {$httpCode}, response: " . substr($response, 0, 500), 'vk-widget');
                throw new \Exception('Upload failed with HTTP code: ' . $httpCode . '. Response: ' . substr($response, 0, 500));
            }
            
            $data = json_decode($response, true);
            
            if (!$data) {
                Yii::error("Invalid JSON response from VK upload server: " . substr($response, 0, 500), 'vk-widget');
                throw new \Exception('Invalid response from upload server: ' . substr($response, 0, 500));
            }
            
            // Если ВК вернул ошибку, логируем её
            if (isset($data['error_code']) || isset($data['error'])) {
                Yii::error("VK upload error: " . json_encode($data), 'vk-widget');
            }
            
            return $data;
        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 500;
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Прокси для сохранения изображения виджета ВК
     * Использует appWidgets.saveAppImage для коллекции приложения
     * Работает с сервисным ключом доступа приложения (vk_app_sever_key)
     */
    public function actionSaveImage()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        $image = Yii::$app->request->post('image');
        
        if (!$image) {
            Yii::$app->response->statusCode = 400;
            return [
                'error' => 'image is required'
            ];
        }
        
        // Используем сервисный ключ из настроек
        $serviceToken = Yii::$app->settings->get('vk_app_sever_key');
        
        if (!$serviceToken) {
            Yii::$app->response->statusCode = 400;
            return [
                'error' => 'Service access token is required. Set vk_app_sever_key in settings.'
            ];
        }
        
        try {
            // Используем метод для коллекции приложения (работает с сервисным ключом)
            $url = "https://api.vk.com/method/appWidgets.saveAppImage?" .
                   "image=" . urlencode($image) .
                   "&access_token=" . urlencode($serviceToken) .
                   "&v=5.199";
            
            $response = file_get_contents($url);
            $data = json_decode($response, true);
            
            return $data;
        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 500;
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Прокси для обновления виджета ВК по переданному коду
     * Использует appWidgets.update для обновления виджета сообщества
     * Требует токен пользователя с правами app_widget
     */
    public function actionUpdateByCode()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        $groupId = Yii::$app->request->post('group_id');
        $code = Yii::$app->request->post('code');
        $type = Yii::$app->request->post('type', 'table');
        
        if (!$groupId || !$code) {
            Yii::$app->response->statusCode = 400;
            return [
                'error' => 'group_id and code are required'
            ];
        }
        
        // Для appWidgets.update требуется токен пользователя с правами app_widget
        $accessToken = Yii::$app->request->post('access_token');
        
        if (!$accessToken) {
            Yii::$app->response->statusCode = 400;
            return [
                'error' => 'access_token is required. appWidgets.update requires user access token with app_widget permissions.'
            ];
        }
        
        return $this->updateWidgetByCode($groupId, $code, $type, $accessToken);
    }

    /**
     * Обновить все активные виджеты ВК
     * GET /vk-widget/update-all
     */
    public function actionUpdateAll()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        $widgets = VkWidget::getActiveWidgets();
        
        if (empty($widgets)) {
            return [
                'success' => true,
                'message' => 'No active widgets found',
                'updated' => 0,
                'failed' => 0
            ];
        }
        
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        
        foreach ($widgets as $widget) {
            try {
                if ($this->updateWidgetByModel($widget)) {
                    $successCount++;
                } else {
                    $errorCount++;
                    $errors[] = "Failed to update widget for group_id: {$widget->group_id}";
                }
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = "Exception for group_id {$widget->group_id}: " . $e->getMessage();
            }
        }
        
        return [
            'success' => $errorCount === 0,
            'message' => "Updated: {$successCount}, Failed: {$errorCount}",
            'updated' => $successCount,
            'failed' => $errorCount,
            'errors' => $errors
        ];
    }

    /**
     * Обновить виджет по group_id
     * GET /vk-widget/update-by-group-id?group_id=123456
     */
    public function actionUpdateByGroupId($group_id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        if (!$group_id) {
            Yii::$app->response->statusCode = 400;
            return [
                'error' => 'group_id is required'
            ];
        }
        
        $widget = VkWidget::findOne(['group_id' => $group_id, 'status' => VkWidget::STATUS_ACTIVE]);
        
        if (!$widget) {
            Yii::$app->response->statusCode = 404;
            return [
                'error' => "Widget for group_id {$group_id} not found or inactive"
            ];
        }
        
        try {
            if ($this->updateWidgetByModel($widget)) {
                return [
                    'success' => true,
                    'message' => "Widget for group_id {$group_id} updated successfully"
                ];
            } else {
                Yii::$app->response->statusCode = 500;
                return [
                    'error' => "Failed to update widget for group_id {$group_id}"
                ];
            }
        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 500;
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Обновить виджет по переданному коду (для обратной совместимости)
     * POST /vk-widget/update
     */
    public function actionUpdate()
    {
        return $this->actionUpdateByCode();
    }

    /**
     * Сохранить информацию об установленном виджете в БД
     * для последующего автоматического обновления через cron
     */
    public function actionSave()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        $groupId = Yii::$app->request->post('group_id');
        $appId = Yii::$app->request->post('app_id');
        $logoIconId = Yii::$app->request->post('logo_icon_id');
        $apiUrl = Yii::$app->request->post('api_url');
        $accessToken = Yii::$app->request->post('access_token'); // Опционально, для автоматического обновления
        
        if (!$groupId || !$appId) {
            Yii::$app->response->statusCode = 400;
            return [
                'error' => 'group_id and app_id are required'
            ];
        }
        
        try {
            $widget = \common\models\vk\VkWidget::findOrCreate($groupId, $appId);
            
            if ($logoIconId) {
                $widget->logo_icon_id = $logoIconId;
            }
            
            if ($apiUrl) {
                $widget->api_url = $apiUrl;
            }
            
            // Сохраняем токен для автоматического обновления (если передан)
            if ($accessToken) {
                $widget->access_token = $widget->encryptToken($accessToken);
            }
            
            $widget->status = \common\models\vk\VkWidget::STATUS_ACTIVE;
            
            if ($widget->save()) {
                return [
                    'success' => true,
                    'message' => 'Widget saved successfully',
                    'id' => $widget->id
                ];
            } else {
                Yii::$app->response->statusCode = 400;
                return [
                    'error' => 'Failed to save widget',
                    'errors' => $widget->errors
                ];
            }
        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 500;
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Обновить виджет по переданному коду
     * 
     * @param int $groupId
     * @param string $code
     * @param string $type
     * @param string $accessToken
     * @return array
     */
    protected function updateWidgetByCode($groupId, $code, $type, $accessToken)
    {
        try {
            $url = "https://api.vk.com/method/appWidgets.update?" .
                   "code=" . urlencode($code) .
                   "&type=" . urlencode($type) .
                   "&group_id=" . urlencode($groupId) .
                   "&access_token=" . urlencode($accessToken) .
                   "&v=5.199";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'ignore_errors' => true
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                $error = error_get_last();
                Yii::error("Failed to call VK API for widget group_id {$groupId}. Error: " . ($error['message'] ?? 'Unknown error'), 'vk-widget');
                return [
                    'error' => [
                        'error_code' => -1,
                        'error_msg' => 'Failed to call VK API: ' . ($error['message'] ?? 'Unknown error')
                    ]
                ];
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Yii::error("Failed to decode JSON response from VK API for widget group_id {$groupId}. JSON error: " . json_last_error_msg() . ". Response: " . substr($response, 0, 500), 'vk-widget');
                return [
                    'error' => [
                        'error_code' => -2,
                        'error_msg' => 'Invalid JSON response from VK API: ' . json_last_error_msg()
                    ]
                ];
            }
            
            if ($data === null) {
                Yii::error("Empty response from VK API for widget group_id {$groupId}. Raw response: " . substr($response, 0, 500), 'vk-widget');
                return [
                    'error' => [
                        'error_code' => -3,
                        'error_msg' => 'Empty response from VK API'
                    ]
                ];
            }
            
            return $data;
        } catch (\Exception $e) {
            Yii::error("Exception updating widget by code for group_id {$groupId}: " . $e->getMessage(), 'vk-widget');
            throw $e;
        }
    }

    /**
     * Обновить виджет по модели
     * 
     * @param VkWidget $widget
     * @param bool $verbose Если true, выводит детальную информацию об ошибках (для консоли)
     * @return bool
     */
    protected function updateWidgetByModel(VkWidget $widget, $verbose = false)
    {
        // Получаем данные о серверах
        $apiUrl = $widget->api_url ?: (Yii::$app->params['api_url'] ?? 'https://api.prostoj.store/servers');
        $serversData = $this->getServersData($apiUrl);
        
        if (empty($serversData)) {
            $errorMsg = "No servers data for widget group_id={$widget->group_id}. API URL: {$apiUrl}";
            Yii::warning($errorMsg, 'vk-widget');
            if ($verbose) {
                echo "   ERROR: Не удалось получить данные о серверах из API URL: {$apiUrl}\n";
            }
            return false;
        }

        if ($verbose) {
            echo "   Получено " . count($serversData) . " серверов\n";
        }

        // Создаем код виджета
        $widgetCode = $this->createWidgetCode($serversData, $widget->logo_icon_id);

        if ($verbose) {
            echo "   Код виджета сгенерирован, длина: " . mb_strlen($widgetCode) . " символов\n";
        }

        // Получаем токен для обновления
        $accessToken = $widget->decryptToken();
        
        if (!$accessToken) {
            // Если токена нет, пытаемся использовать сервисный ключ (может не работать)
            $accessToken = Yii::$app->settings->get('vk_app_sever_key');
            if ($verbose && $accessToken) {
                echo "   Используется сервисный ключ из настроек\n";
            }
        } else {
            if ($verbose) {
                echo "   Токен получен из БД\n";
            }
        }

        if (!$accessToken) {
            $errorMsg = "No access token for widget group_id={$widget->group_id}";
            Yii::error($errorMsg, 'vk-widget');
            if ($verbose) {
                echo "   ERROR: Отсутствует токен доступа. Проверьте, что токен сохранен в БД или установлен vk_app_sever_key в настройках.\n";
            }
            return false;
        }

        // Обновляем виджет через API ВК
        try {
            if ($verbose) {
                echo "   Отправка запроса в VK API...\n";
            }
            
            $result = $this->updateWidgetByCode(
                $widget->group_id,
                $widgetCode,
                'table',
                $accessToken
            );

            if (isset($result['error'])) {
                $errorMsg = "VK API error for widget group_id={$widget->group_id}: " . json_encode($result['error']);
                Yii::error($errorMsg, 'vk-widget');
                if ($verbose) {
                    $errorInfo = is_array($result['error']) ? json_encode($result['error'], JSON_UNESCAPED_UNICODE) : $result['error'];
                    echo "   ERROR от VK API: " . $errorInfo . "\n";
                    if (isset($result['error']['error_code'])) {
                        echo "   Код ошибки: " . $result['error']['error_code'] . "\n";
                    }
                    if (isset($result['error']['error_msg'])) {
                        echo "   Сообщение: " . $result['error']['error_msg'] . "\n";
                    }
                }
                return false;
            }

            Yii::info("Widget updated successfully for group_id={$widget->group_id}", 'vk-widget');
            if ($verbose) {
                echo "   Виджет успешно обновлен в VK\n";
            }
            return true;
        } catch (\Exception $e) {
            $errorMsg = "Exception updating widget group_id={$widget->group_id}: " . $e->getMessage();
            Yii::error($errorMsg, 'vk-widget');
            if ($verbose) {
                echo "   EXCEPTION: " . $e->getMessage() . "\n";
                echo "   Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
            }
            return false;
        }
    }

    /**
     * Получить данные о серверах
     * 
     * @param string|null $apiUrl
     * @return array
     */
    protected function getServersData($apiUrl = null)
    {
        if (!$apiUrl) {
            $apiUrl = Yii::$app->params['api_url'] ?? 'https://api.prostoj.store/servers';
        }

        try {
            $response = @file_get_contents($apiUrl);
            
            if ($response === false) {
                return [];
            }

            $data = json_decode($response, true);
            
            return is_array($data) ? $data : [];
        } catch (\Exception $e) {
            Yii::error("Failed to get servers data: " . $e->getMessage(), 'vk-widget');
            return [];
        }
    }

    /**
     * Создать код виджета из данных серверов
     * 
     * @param array $serversData
     * @param string|null $logoIconId
     * @return string
     */
    protected function createWidgetCode($serversData, $logoIconId = null)
    {
        // Форматирование прогресс-бара
        $formatOnlineProgress = function($online, $max) {
            $onlineValue = $online ?? 0;
            $maxValue = max($max ?? 1, 30);
            $percentage = round(($onlineValue / $maxValue) * 100);
            
            $totalBlocks = 4;
            $filledBlocks = round(($percentage / 100) * $totalBlocks);
            
            $progressBar = '';
            for ($i = 0; $i < $totalBlocks; $i++) {
                $progressBar .= $i < $filledBlocks ? '🟩' : '⬜️';
            }
            
            return $onlineValue . '/' . $maxValue . ' 👤 ' . $progressBar;
        };

        // Форматирование названия сервера
        $formatServerName = function($server) {
            $name = $server['name'] ?? 'Сервер';
            
            if (isset($server['wipe_type_text'])) {
                $name = $name . ' (' . $server['wipe_type_text'] . ')';
            } elseif (isset($server['wipe_type'])) {
                switch ($server['wipe_type']) {
                    case 7:
                        $name = $name . ' (Недельный)';
                        break;
                    case 14:
                        $name = $name . ' (Двухнедельный)';
                        break;
                    case 30:
                        $name = $name . ' (Месячный)';
                        break;
                }
            }
            
            return mb_substr($name, 0, 50);
        };

        // Формируем тело таблицы
        $tableBody = [];
        $servers = array_slice($serversData, 0, 6);
        
        foreach ($servers as $server) {
            $firstCell = [
                'text' => $formatServerName($server)
            ];
            
            if ($logoIconId) {
                $firstCell['icon_id'] = $logoIconId;
            }
            
            $tableBody[] = [
                $firstCell,
                [
                    'text' => $formatOnlineProgress($server['online'] ?? 0, $server['max'] ?? 1) . ' | ' . mb_substr($server['text_ip'] ?? $server['ip'] ?? '—', 0, 50),
                    'align' => 'right'
                ]
            ];
        }

        if (empty($tableBody)) {
            $placeholderFirstCell = ['text' => 'Загрузка данных...'];
            if ($logoIconId) {
                $placeholderFirstCell['icon_id'] = $logoIconId;
            }
            
            $tableBody[] = [
                $placeholderFirstCell,
                ['text' => '—', 'align' => 'right']
            ];
        }

        $widgetObject = [
            'title' => 'Мониторинг серверов',
            'head' => [
                ['text' => 'Сервер'],
                ['text' => 'Игроки | IP', 'align' => 'right']
            ],
            'body' => $tableBody
        ];

        return 'return ' . json_encode($widgetObject, JSON_UNESCAPED_UNICODE) . ';';
    }
}

