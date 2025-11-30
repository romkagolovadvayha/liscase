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
     * Использует сервисный ключ доступа приложения или токен пользователя
     */
    public function actionGetUploadServer()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        $groupId = Yii::$app->request->get('group_id');
        
        if (!$groupId) {
            Yii::$app->response->statusCode = 400;
            return [
                'error' => 'group_id is required'
            ];
        }
        
        // Получаем токен доступа: переданный токен или сервисный ключ из настроек
        $passedToken = Yii::$app->request->get('access_token');
        $serviceToken = Yii::$app->settings->get('vk_app_sever_key');
        $accessToken = $passedToken ?: $serviceToken;
        
        if (!$accessToken) {
            Yii::$app->response->statusCode = 400;
            return [
                'error' => 'access_token is required. Provide access_token parameter or set vk_app_sever_key in settings.'
            ];
        }
        
        try {
            $url = "https://api.vk.com/method/appWidgets.getGroupImageUploadServer?" .
                   "group_id=" . urlencode($groupId) .
                   "&image_type=24x24" .
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
     * Прокси для сохранения изображения виджета ВК
     * Использует сервисный ключ доступа приложения или токен пользователя
     */
    public function actionSaveImage()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        $groupId = Yii::$app->request->post('group_id');
        $image = Yii::$app->request->post('image');
        
        if (!$groupId || !$image) {
            Yii::$app->response->statusCode = 400;
            return [
                'error' => 'group_id and image are required'
            ];
        }
        
        // Получаем токен доступа: сервисный ключ из настроек или переданный токен
        $serviceToken = Yii::$app->settings->get('vk_app_sever_key');
        $accessToken = Yii::$app->request->post('access_token') ?: $serviceToken;
        
        if (!$accessToken) {
            Yii::$app->response->statusCode = 400;
            return [
                'error' => 'access_token is required. Provide access_token parameter or set vk_app_sever_key in settings.'
            ];
        }
        
        try {
            $url = "https://api.vk.com/method/appWidgets.saveGroupImage?" .
                   "group_id=" . urlencode($groupId) .
                   "&image=" . urlencode($image) .
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
}

