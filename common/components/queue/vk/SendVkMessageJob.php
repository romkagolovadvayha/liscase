<?php

namespace common\components\queue\vk;

use common\components\vk\VkApiHelper;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class SendVkMessageJob extends BaseObject implements JobInterface
{
    public $user_id;
    public $message;
    public $photo;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            $vkApi = new VkApiHelper();
            $result = $vkApi->sendMessage($this->user_id, $this->message, $this->photo);
            
            if ($result === false || !empty($result['error'])) {
                Yii::error("SendVkMessageJob: Failed to send message to VK user {$this->user_id}", __METHOD__);
            }
        } catch (\Exception $e) {
            Yii::error("SendVkMessageJob: Exception - " . $e->getMessage(), __METHOD__);
        }
    }
}

