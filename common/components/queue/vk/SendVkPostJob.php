<?php

namespace common\components\queue\vk;

use common\components\vk\VkApiHelper;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class SendVkPostJob extends BaseObject implements JobInterface
{
    public $group_id;
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
            $result = $vkApi->postToGroup($this->group_id, $this->message, $this->photo);
            
            if ($result === false || !empty($result['error'])) {
                Yii::error("SendVkPostJob: Failed to post to VK group {$this->group_id}", __METHOD__);
            }
        } catch (\Exception $e) {
            Yii::error("SendVkPostJob: Exception - " . $e->getMessage(), __METHOD__);
        }
    }
}

