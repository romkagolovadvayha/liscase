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
}

