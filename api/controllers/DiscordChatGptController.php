<?php

namespace api\controllers;

use common\components\openAi\OpenAiSupport;
use Yii;
use yii\web\Controller;
use yii\web\BadRequestHttpException;

class DiscordChatGptController extends Controller
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
     * Получить ответ от ChatGPT для Discord
     * POST /api/discord-chatgpt/reply
     * 
     * Body:
     * {
     *   "message": "текст сообщения",
     *   "username": "имя пользователя",
     *   "chatHistory": [{"user": "сообщение1"}, {"bot": "ответ1"}]
     * }
     */
    public function actionReply()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $message = Yii::$app->request->post('message');
        $username = Yii::$app->request->post('username', 'Пользователь');
        $chatHistory = Yii::$app->request->post('chatHistory', []);
        $server = Yii::$app->request->post('server', 'Сервер');
        
        if (empty($message)) {
            throw new BadRequestHttpException('Необходимо указать сообщение');
        }
        
        try {
            /** @var OpenAiSupport $openAiSupport */
            $openAiSupport = Yii::$app->openAiSupport;
            
            $reply = $openAiSupport->getReply(
                trim($message),
                $username,
                $server,
                $chatHistory,
                null, // ticketId
                null  // user
            );
            
            if (empty($reply)) {
                return [
                    'success' => false,
                    'message' => 'Не удалось получить ответ от ChatGPT'
                ];
            }
            
            return [
                'success' => true,
                'reply' => $reply
            ];
            
        } catch (\Exception $e) {
            Yii::error('Discord ChatGPT error: ' . $e->getMessage(), __METHOD__);
            return [
                'success' => false,
                'message' => 'Ошибка при обработке запроса'
            ];
        }
    }
}


