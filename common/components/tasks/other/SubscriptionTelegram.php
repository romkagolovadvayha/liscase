<?php

namespace common\components\tasks\other;

use common\components\tasks\BaseClass;
use common\components\promotion\SocialNetworkPromotion;
use common\models\promotion\UserPromotion;
use common\models\tasks\Tasks;
use common\models\user\UserSocialNetwork;
use common\models\user\UserTasks;
use Yii;
use yii\base\BaseObject;
use yii\base\Component;

class SubscriptionTelegram extends BaseClass
{

    public function check($taskId, $userId): UserTasks
    {
        $task = Tasks::findOne($taskId);
        $userTask = $this->updateUserTaskStatus($taskId, $userId, UserTasks::STATUS_WAITING);
        $social = new SocialNetworkPromotion();
        /** @var UserSocialNetwork $userSocialNetwork */
        $userSocialNetwork = UserSocialNetwork::find()
            ->andWhere(['user_id' => $userId])
            ->andWhere(['network' => UserSocialNetwork::NETWORK_TELEGRAM])
            ->one();

        if (!empty($userSocialNetwork)) {
//            if (!empty($this->getChatId($task->url_link ?? $task->button_url))) {
//                $status = UserTasks::STATUS_REJECTED;
//                $result = $social->getChatMember($this->getChatId($task->url_link ?? $task->button_url), $userSocialNetwork->foreign_number);
//                if (isset($result['ok']) && $result['ok']) {
//                    $status = UserTasks::STATUS_GET_PROFIT;
//                }
//                $userTask->status = $status;
//                $userTask->save(false);
//            }
            if (empty($userTask->result)) {
                $userTask->result = $userSocialNetwork->foreign_number;
                $userTask->save(false);
            }
        }

        return $userTask;
    }

    /**
     * Принимать автоматически задание через это время
     */
    public function autoSuccessTime() {
        return 60 * 60 * 3;
    }

    private function getChatId($key) {
        $list = [
            'https://t.me/DigiUPartners' => SocialNetworkPromotion::DIGIU_PARTNERS_CHANNEL,
            'https://t.me/digiupartnersru' => SocialNetworkPromotion::DIGIU_PARTNERS_RU_CHANNEL,
            'https://t.me/digiu_ai' => SocialNetworkPromotion::DIGIU_AI_CHANNEL,
            'https://t.me/Antipad_channel' => SocialNetworkPromotion::DIGIU_WEBWISE_CHANNEL,
            'https://t.me/Antipad_channel_ru' => SocialNetworkPromotion::DIGIU_WEBWISE_CHANNEL_RU,
            'https://t.me/apato_estate_group' => SocialNetworkPromotion::APATO_CHANNEL,
        ];

        if (empty($list[$key])) {
            return null;
        }

        return $list[$key];
    }

}
