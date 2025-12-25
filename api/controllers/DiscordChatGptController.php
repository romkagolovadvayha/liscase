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
        Yii::info('DiscordChatGptController::beforeAction вызван для action: ' . $action->id, __METHOD__);
        
        // Устанавливаем CORS заголовки
        Yii::$app->response->headers->set('Access-Control-Allow-Origin', '*');
        Yii::$app->response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        Yii::$app->response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        Yii::$app->response->headers->set('Access-Control-Max-Age', '3600');
        
        // Обработка preflight запросов
        if (Yii::$app->request->method === 'OPTIONS') {
            Yii::info('DiscordChatGptController: OPTIONS запрос, завершаем', __METHOD__);
            Yii::$app->response->statusCode = 200;
            Yii::$app->end();
        }
        
        Yii::info('DiscordChatGptController::beforeAction завершен, продолжаем', __METHOD__);
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
        Yii::info('Discord ChatGPT: начало обработки запроса', __METHOD__);
        
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        // Получаем данные из POST (может быть JSON или form-data)
        // Yii2 автоматически парсит JSON если Content-Type: application/json
        $message = Yii::$app->request->post('message');
        $username = Yii::$app->request->post('username', 'Пользователь');
        $chatHistory = Yii::$app->request->post('chatHistory', []);
        $server = Yii::$app->request->post('server', 'Discord');
        
        // Если через post() не получилось, пробуем rawBody
        if (empty($message)) {
            $rawBody = Yii::$app->request->rawBody;
            if (!empty($rawBody)) {
                $jsonData = json_decode($rawBody, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                    $message = $jsonData['message'] ?? null;
                    $username = $jsonData['username'] ?? $username;
                    $chatHistory = $jsonData['chatHistory'] ?? $chatHistory;
                    $server = $jsonData['server'] ?? $server;
                }
            }
        }
        
        Yii::info('Discord ChatGPT: получены данные - message: ' . mb_substr($message ?? '', 0, 50) . ', username: ' . $username . ', rawBody length: ' . strlen($rawBody ?? ''), __METHOD__);
        
        if (empty($message)) {
            Yii::warning('Discord ChatGPT: пустое сообщение', __METHOD__);
            Yii::$app->response->statusCode = 400;
            return [
                'success' => false,
                'message' => 'Необходимо указать сообщение'
            ];
        }
        
        try {
            Yii::info('Discord ChatGPT: проверка компонента openAiSupport', __METHOD__);
            
            // Проверяем наличие компонента
            if (!isset(Yii::$app->openAiSupport)) {
                Yii::error('Discord ChatGPT: компонент openAiSupport не найден', __METHOD__);
                Yii::$app->response->statusCode = 500;
                return [
                    'success' => false,
                    'message' => 'Компонент OpenAI не настроен'
                ];
            }
            
            /** @var OpenAiSupport $openAiSupport */
            $openAiSupport = Yii::$app->openAiSupport;
            
            Yii::info('Discord ChatGPT: проверка API ключа', __METHOD__);
            
            // Проверяем настройки
            $apiKey = Yii::$app->settings->get('openAi_apiKey');
            if (empty($apiKey)) {
                Yii::error('Discord ChatGPT: API ключ OpenAI не настроен', __METHOD__);
                Yii::$app->response->statusCode = 500;
                return [
                    'success' => false,
                    'message' => 'API ключ OpenAI не настроен'
                ];
            }
            
            Yii::info('Discord ChatGPT: запрос от ' . $username . ', сообщение: ' . mb_substr($message, 0, 100), __METHOD__);
            Yii::info('Discord ChatGPT: вызов getReply()', __METHOD__);
            
            $reply = $openAiSupport->getReply(
                trim($message),
                $username,
                $server,
                $chatHistory,
                null, // ticketId
                null, // user
                true  // useDiscordInstructions - использовать инструкции для Discord
            );
            
            Yii::info('Discord ChatGPT: getReply() завершен, reply: ' . (empty($reply) ? 'пусто' : mb_substr($reply, 0, 50)), __METHOD__);
            
            if (empty($reply)) {
                Yii::error('Discord ChatGPT: пустой ответ от OpenAI', __METHOD__);
                Yii::$app->response->statusCode = 500;
                return [
                    'success' => false,
                    'message' => 'Не удалось получить ответ от ChatGPT'
                ];
            }
            
            Yii::info('Discord ChatGPT: успешно получен ответ длиной ' . mb_strlen($reply), __METHOD__);
            
            return [
                'success' => true,
                'reply' => $reply
            ];
            
        } catch (\Throwable $e) {
            Yii::error('Discord ChatGPT error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine(), __METHOD__);
            Yii::error('Discord ChatGPT stack trace: ' . $e->getTraceAsString(), __METHOD__);
            
            // В режиме разработки возвращаем детальную информацию
            $errorMessage = 'Ошибка при обработке запроса';
            if (YII_DEBUG) {
                $errorMessage .= ': ' . $e->getMessage();
            }
            
            Yii::$app->response->statusCode = 500;
            return [
                'success' => false,
                'message' => $errorMessage,
                'error_type' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'debug' => YII_DEBUG ? $e->getTraceAsString() : null
            ];
        }
    }
}


