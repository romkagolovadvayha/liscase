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
        return;
        $date = new \DateTime();
        $date->modify('-10 minute');
        /** @var Chats[] $messages */
        $messages = Chats::find()
            ->andWhere(['>', 'created_at', $date->format('Y-m-d H:i:s')])
            ->andWhere(['is_muted' => 0])
            ->orderBy(['id' => SORT_DESC])
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

        $result = \Yii::$app->openAiChat->getReply($requestMessage);

        if (empty($result)) {
            return;
        }

        foreach ($result as $item) {
            if ($item['type'] == 4) {
                \Yii::$app->telegramSupport->sendMessage("Просит помощи админа {$item['steam_id']} сообщение \"{$item['message']}\"");
            }
            Chats::mute($item);
        }
    }

}