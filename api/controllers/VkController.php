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
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        try {
            $data = json_decode(Yii::$app->request->rawBody, true);
            
            if (empty($data)) {
                Yii::error("VK Webhook: Empty request body", __METHOD__);
                return ['ok' => false];
            }

            $type = $data['type'] ?? null;
            $groupId = $data['group_id'] ?? null;
            $secret = $data['secret'] ?? null;

            // Проверка секретного ключа (если задан в настройках)
            $webhookSecret = Yii::$app->settings->get('vk_webhook_secret');
            if (!empty($webhookSecret) && $secret !== $webhookSecret) {
                Yii::error("VK Webhook: Invalid secret key", __METHOD__);
                return ['ok' => false];
            }

            // Обработка подтверждения вебхука
            if ($type === 'confirmation') {
                $confirmationToken = Yii::$app->settings->get('vk_webhook_confirmation_token');
                if (!empty($confirmationToken)) {
                    return $confirmationToken;
                }
                Yii::error("VK Webhook: Confirmation token not set", __METHOD__);
                return ['ok' => false];
            }

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

                // Проверяем, что сообщение пришло в личные сообщения группы
                // Для личных сообщений группы: peer_id отрицательный (ID группы), from_id положительный (ID пользователя)
                if (empty($text) || empty($fromId) || $fromId <= 0) {
                    return ['ok' => true];
                }

                // Проверяем, что сообщение пришло в личные сообщения группы (peer_id должен быть отрицательным)
                // Если peer_id положительный - это может быть беседа или личное сообщение пользователю, пропускаем
                if ($peerId > 0) {
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

