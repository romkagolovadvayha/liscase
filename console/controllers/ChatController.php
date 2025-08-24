<?php

namespace console\controllers;

use common\models\statistics\Chats;
use common\models\user\User;
use yii\console\Controller;

class ChatController extends Controller
{

    /**
     * chat/check
     * @return array|string[]
     */
    public function actionCheck()
    {
        $date = new \DateTime();
//        $date->modify('-50 minute');
        /** @var Chats[] $messages */
        $messages = Chats::find()
            ->andWhere(['>', 'created_at', $date->format('Y-m-d H:i:s')])
            ->andWhere(['is_muted' => 0])
            ->limit(1000)
            ->all();

        $list = [];
        foreach ($messages as $message) {
            $list[] = [
              'steam_id' => $message->steam_id,
              'message' => $message->message,
            ];
        }
        $requestMessage = json_encode(
            ['messages' => $list],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
print_r($requestMessage);
        $result = \Yii::$app->openAiChat->getReply($requestMessage);


        \Yii::$app->telegramChats->sendMessage(json_encode($result));
        if (empty($result)) {
            return;
        }

        foreach ($result as $item) {
            Chats::mute($item);
        }
    }

}