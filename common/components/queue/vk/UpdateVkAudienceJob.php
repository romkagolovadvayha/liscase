<?php

namespace common\components\queue\vk;

use common\components\vk\VkApiHelper;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class UpdateVkAudienceJob extends BaseObject implements JobInterface
{
    public $groupId;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            if (empty($this->groupId)) {
                $this->groupId = Yii::$app->settings->get('vk_group_id');
            }

            if (empty($this->groupId)) {
                try {
                    Yii::$app->telegramChats->sendMessage("VK: group_id не настроен в настройках");
                } catch (\Exception $e) {
                    // Игнорируем ошибки отправки в Telegram
                }
                return;
            }

            $vkApi = new VkApiHelper();
            $stats = $vkApi->updateAudience($this->groupId);

            try {
                Yii::$app->telegramChats->sendMessage(
                    "Аудитория ВКонтакте обновлена!\n" .
                    "Всего участников: {$stats['total']}\n" .
                    "С разрешением на сообщения: {$stats['with_permission']}\n" .
                    "Сохранено в базу: {$stats['saved']}"
                );
            } catch (\Exception $e) {
                // Игнорируем ошибки отправки в Telegram
            }
        } catch (\Exception $e) {
            try {
                Yii::$app->telegramChats->sendMessage("Ошибка при обновлении аудитории ВКонтакте: " . $e->getMessage());
            } catch (\Exception $ex) {
                // Игнорируем ошибки отправки в Telegram
            }
            Yii::error("UpdateVkAudienceJob error: " . $e->getMessage(), __METHOD__);
        }
    }
}

