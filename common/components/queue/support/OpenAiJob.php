<?php

namespace common\components\queue\support;

use common\components\queue\telegram\SendMessageJob;
use common\models\signs\Signs;
use common\models\servers\Servers;
use common\models\support\Support;
use common\models\support\SupportMessage;
use common\models\support\SupportRead;
use common\models\user\User;
use common\models\user\UserRaid;
use WebSocket\Client;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class OpenAiJob extends BaseObject implements JobInterface
{
    public $chatId;
    public $ownerUserId;
    public $userId;
    public $message;
    public $chatNumber;
    public $username;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        echo $this->message . PHP_EOL;
        try {
            sleep(60);
            $chat = Support::findOne($this->chatId);
            if ($chat->status !== Support::STATUS_OPEN) {
                return;
            }
            $chatHistory = [];
            /** @var SupportMessage[] $histories */
            $histories = SupportMessage::find()
                                       ->andWhere(['support_id' => $this->chatId])
                                       ->andWhere('user_id IS NOT NULL')
                                       ->orderBy(['id' => SORT_ASC])
                                       ->all();
            foreach ($histories as $history) {
                if ($history->user_id == $this->userId) {
                    $chatHistory[] = ['user' => $history->message];
                } else {
                    $chatHistory[] = ['bot' => $history->message];
                }
            }
            $reply = Yii::$app->openAiSupport->getReply(trim($this->message), $chatHistory);
            if ($reply == 'unknown') {
                $chat->is_bot = false;
                $chat->save(false);
                return;
            }

            $admin = User::findBySteamId(76561198394504608);

            $modelBot = new SupportMessage();
            $modelBot->user_id = $admin->id;
            $modelBot->message = trim($reply);
            $modelBot->support_id = $this->chatId;
            $modelBot->created_at = date('Y-m-d H:i:s');
            $modelBot->save();
            SupportRead::createRecord($this->ownerUserId, $admin->id, $modelBot->id, $this->chatId);

            Yii::$app->queueProcess->push(new BeforeMessageJob([
                'chatId' => $this->chatId,
                'userId' => $this->userId,
                'message' => $this->message,
                'username' => $this->username,
                'chatNumber' => $this->chatNumber,
            ]));
            try {
                $client = new Client(Yii::$app->params['ws']);
                $client->send(
                    json_encode(
                        [
                            'action'    => 'chatUpdate',
                            'code'      => 200,
                            'id'        => $this->chatNumber,
                            'user_id'   => $this->ownerUserId,
                            'messageId' => $modelBot->id,
                        ]
                    )
                );
            } catch (\Exception $ex) {
                Yii::$app->telegramChats->sendMessage('Update chat: ' . $ex->getFile() . ':' . $ex->getLine() . ' ' . $ex->getMessage());
            }

        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("OpenAiJob: " . $e->getLine() . ":" . $e->getMessage());
        }
    }
}