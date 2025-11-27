<?php

namespace api\controllers;

use common\components\vk\VkApiHelper;
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
                Yii::$app->response->format = Response::FORMAT_JSON;
                return ['ok' => false];
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
                Yii::$app->response->format = Response::FORMAT_JSON;
                return ['ok' => false];
            }

            // Для остальных типов событий используем JSON формат
            Yii::$app->response->format = Response::FORMAT_JSON;

            // Обработка нового сообщения
            if ($type === 'message_new') {
                $message = $data['object']['message'] ?? null;
                
                if (empty($message)) {
                    Yii::error("VK Webhook: Empty message object", __METHOD__);
                    return ['ok' => false];
                }

                $text = trim($message['text'] ?? '');
                $fromId = $message['from_id'] ?? null;
                $peerId = $message['peer_id'] ?? null;
                $out = $message['out'] ?? 1; // 0 - входящее, 1 - исходящее

                // Проверяем, что сообщение пришло в личные сообщения группы
                // Для личных сообщений группы: from_id положительный (ID пользователя), out = 0 (входящее)
                if (empty($text) || empty($fromId) || $fromId <= 0) {
                    return ['ok' => true];
                }

                // Пропускаем исходящие сообщения (от группы пользователю)
                if ($out === 1) {
                    return ['ok' => true];
                }

                // Проверяем, что это входящее сообщение (от пользователя группе)
                // peer_id может быть как положительным (ID пользователя), так и отрицательным (ID группы)
                // Главное - это входящее сообщение (out = 0) и есть group_id в запросе
                if (!empty($groupId) && $fromId > 0 && $out === 0) {
                    // Это сообщение в личные сообщения группы - обрабатываем
                } else {
                    // Не подходит под наши критерии - пропускаем
                    return ['ok' => true];
                }

                // Проверяем, является ли текст кодом
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

                        // Отправляем ответ пользователю
                        $vkHelper = new VkApiHelper();
                        $vkHelper->setAccessToken(Yii::$app->settings->get('vk_token'));
                        
                        $responseMessage = "✅ Код успешно подтвержден! Теперь перейдите на сайт и нажмите кнопку \"Проверить\" для завершения задания.";
                        $vkHelper->sendMessage($fromId, $responseMessage);
                        
                        Yii::info("VK Webhook: User {$user->id} successfully linked with VK ID {$fromId}", __METHOD__);
                    } else {
                        Yii::error("VK Webhook: Failed to save vk_id for user {$user->id}", __METHOD__);
                    }
                } else {
                    // Код не найден, но это нормально - может быть обычное сообщение
                    Yii::info("VK Webhook: Code not found for text: {$text}", __METHOD__);
                }

                return ['ok' => true];
            }

            // Для других типов событий просто подтверждаем получение
            return ['ok' => true];

        } catch (\Exception $e) {
            Yii::error("VK Webhook error: " . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            return ['ok' => false];
        }
    }
}

