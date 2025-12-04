<?php

namespace api\controllers;

use common\components\vk\VkApiHelper;
use common\components\vk\VkBotSystem;
use common\models\user\User;
use common\models\user\UserConfirmCode;
use Yii;
use yii\web\Controller;
use yii\web\Response;

/**
 * Контроллер для обработки вебхуков VK
 */
class VkController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * Обработка вебхука от VK Callback API
     * @return array
     */
    public function actionWebhook()
    {
        try {
            // Читаем raw body правильно
            $rawBody = Yii::$app->request->getRawBody();
            if (empty($rawBody)) {
                $rawBody = file_get_contents('php://input');
            }
            $data = json_decode($rawBody, true);
            
            if (empty($data)) {
                Yii::error("VK Webhook: Empty request body", __METHOD__);
                Yii::$app->response->format = Response::FORMAT_RAW;
                return 'ok';
            }

            $type = $data['type'] ?? null;
            $groupId = $data['group_id'] ?? null;
            $secret = $data['secret'] ?? null;

            // Обработка подтверждения вебхука - возвращаем строку, а не JSON
            if ($type === 'confirmation') {
                $confirmationToken = Yii::$app->settings->get('vk_webhook_confirmation_token');
                // Если токен не задан в настройках, используем значение по умолчанию из сообщения VK
                if (empty($confirmationToken) || trim($confirmationToken) === '') {
                    $confirmationToken = 'bf3d02ef';
                }
                
                // VK ожидает просто строку, без JSON
                Yii::$app->response->format = Response::FORMAT_RAW;
                Yii::$app->response->data = $confirmationToken;
                Yii::$app->response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
                return $confirmationToken;
            }

            // Проверка секретного ключа (если задан в настройках)
            $webhookSecret = Yii::$app->settings->get('vk_webhook_secret');
            if (!empty($webhookSecret) && $secret !== $webhookSecret) {
                Yii::error("VK Webhook: Invalid secret key", __METHOD__);
                Yii::$app->response->format = Response::FORMAT_RAW;
                return 'ok';
            }

            // Для остальных типов событий возвращаем строку "ok"
            Yii::$app->response->format = Response::FORMAT_RAW;

            // Обработка нового сообщения
            if ($type === 'message_new') {
                $message = $data['object']['message'] ?? null;
                
                if (empty($message)) {
                    Yii::error("VK Webhook: Empty message object", __METHOD__);
                    return 'ok';
                }

                $text = trim($message['text'] ?? '');
                $fromId = $message['from_id'] ?? null;
                $peerId = $message['peer_id'] ?? null;
                $out = $message['out'] ?? 1; // 0 - входящее, 1 - исходящее
                $payload = $message['payload'] ?? null; // Payload кнопки, если сообщение пришло от нажатия кнопки

                // Проверяем, что сообщение пришло в личные сообщения группы
                // Для личных сообщений группы: from_id положительный (ID пользователя), out = 0 (входящее)
                if (empty($fromId) || $fromId <= 0) {
                    return 'ok';
                }

                // Пропускаем исходящие сообщения (от группы пользователю)
                if ($out === 1) {
                    return 'ok';
                }

                // Проверяем, что это входящее сообщение (от пользователя группе)
                // peer_id может быть как положительным (ID пользователя), так и отрицательным (ID группы)
                // Главное - это входящее сообщение (out = 0) и есть group_id в запросе
                if (!empty($groupId) && $fromId > 0 && $out === 0) {
                    // Это сообщение в личные сообщения группы - обрабатываем
                } else {
                    // Не подходит под наши критерии - пропускаем
                    return 'ok';
                }

                // Инициализируем компоненты один раз
                $vkHelper = new VkApiHelper();
                $vkHelper->setAccessToken(Yii::$app->settings->get('vk_token'));
                $botSystem = new VkBotSystem();

                // ВАЖНО: Сначала проверяем код подтверждения, чтобы он не обрабатывался как текст кнопки
                // Коды имеют длину 20 символов и генерируются случайно (только буквы и цифры)
                // Проверяем, является ли текст кодом подтверждения (длина 20 символов и только буквы/цифры)
                // Используем mb_strlen для правильного подсчета символов в UTF-8
                $textLength = mb_strlen($text, 'UTF-8');
                if ($textLength === 20 && ctype_alnum($text)) {
                    $user = UserConfirmCode::getUserByVkCode($text);
                        
                        if ($user) {
                            // Код найден, привязываем пользователя
                            $user->vk_id = $fromId;
                            if ($user->save(false)) {
                                // Отмечаем код как использованный
                                $vkCode = UserConfirmCode::find()
                                    ->andWhere(['user_id' => $user->id])
                                    ->andWhere(['type' => UserConfirmCode::TYPE_VK_GROUP])
                                    ->andWhere(['code' => $text])
                                    ->andWhere(['status' => UserConfirmCode::STATUS_ACTIVE])
                                    ->one();
                                
                                if ($vkCode) {
                                    $vkCode->status = UserConfirmCode::STATUS_DISABLED;
                                    $vkCode->save(false);
                                }

                                // Отправляем ответ пользователю с приветствием
                                $responseMessage = "✅ Код успешно подтвержден! Теперь перейдите на сайт и нажмите кнопку \"Проверить\" для завершения задания.";
                                $vkHelper->sendMessage($fromId, $responseMessage);
                                
                                // Отправляем приветственное сообщение
                                $greetingData = $botSystem->getNodeMessage(VkBotSystem::NODE_GREETING, $fromId);
                                $vkHelper->sendMessage($fromId, $greetingData['message'], null, $greetingData['keyboard'] ?? null);
                                
                                Yii::info("VK Webhook: User {$user->id} successfully linked with VK ID {$fromId}", __METHOD__);
                            } else {
                                Yii::error("VK Webhook: Failed to save vk_id for user {$user->id}", __METHOD__);
                            }
                            return 'ok';
                        }
                    }

                // Обработка нажатия на кнопку (если есть payload)
                // В VK payload может быть строкой JSON, нужно распарсить
                if (!empty($payload)) {
                    try {
                        // Если payload - строка, пытаемся распарсить
                        $payloadString = is_string($payload) ? $payload : json_encode($payload);
                        $payloadData = json_decode($payloadString, true);
                        
                        if (json_last_error() === JSON_ERROR_NONE && !empty($payloadData)) {
                            $nodeData = $botSystem->handleButtonClick($payloadString, $fromId);
                            if ($nodeData) {
                                $vkHelper->sendMessage($fromId, $nodeData['message'], null, $nodeData['keyboard'] ?? null);
                                return 'ok';
                            }
                        }
                    } catch (\Exception $e) {
                        Yii::error("VK Webhook: Error handling button click: " . $e->getMessage() . "\nPayload: " . var_export($payload, true), __METHOD__);
                    }
                }

                // Обрабатываем обычное текстовое сообщение через бота (включая текст кнопок)
                // В VK при нажатии на кнопку текст кнопки отправляется как сообщение
                try {
                    $nodeData = $botSystem->handleTextMessage($text, $fromId);
                    if ($nodeData) {
                        $vkHelper->sendMessage($fromId, $nodeData['message'], null, $nodeData['keyboard'] ?? null);
                    }
                } catch (\Exception $e) {
                    Yii::error("VK Webhook: Error handling text message: " . $e->getMessage() . "\nText: " . $text, __METHOD__);
                }

                return 'ok';
            }

            // Для других типов событий просто подтверждаем получение
            return 'ok';

        } catch (\Exception $e) {
            Yii::error("VK Webhook error: " . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            return 'ok';
        }
    }
}

