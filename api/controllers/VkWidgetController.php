<?php

namespace api\controllers;

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
     * Прокси для обновления виджета ВК
     * Использует appWidgets.update для обновления виджета сообщества
     * Требует токен пользователя с правами app_widget
     */
    public function actionUpdate()
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
        
        try {
            $url = "https://api.vk.com/method/appWidgets.update?" .
                   "code=" . urlencode($code) .
                   "&type=" . urlencode($type) .
                   "&access_token=" . urlencode($accessToken) .
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
}

