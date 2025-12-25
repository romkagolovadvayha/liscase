<?php

namespace common\components\queue\support;

use common\components\queue\process\UserSteamInfoUpdateJob;
use common\components\queue\telegram\SendMessageJob;
use common\models\signs\Signs;
use common\models\servers\Servers;
use common\models\support\Support;
use common\models\support\SupportMessage;
use common\models\support\SupportRead;
use common\models\user\Auth;
use common\models\user\User;
use common\models\user\UserProfile;
use common\models\user\UserRaid;
use common\models\user\UserTree;
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
            $chat = Support::findOne($this->chatId);
            if ($chat->status !== Support::STATUS_OPEN) {
                return;
            }
            
            // Проверяем, были ли ответы от админа/модератора (не ChatGPT)
            $hasStaffReply = SupportMessage::find()
                ->alias('sm')
                ->joinWith('user u')
                ->andWhere(['sm.support_id' => $this->chatId])
                ->andWhere(['IS NOT', 'sm.user_id', null])
                ->andWhere(['!=', 'sm.user_id', $chat->user_id]) // Не автор тикета
                ->andWhere(['!=', 'u.steam_id', 777]) // Не ChatGPT бот (steam_id = 777)
                ->exists();
            
            // Если админ/модератор уже вступил в диалог - ChatGPT не отвечает
            if ($hasStaffReply) {
                echo "Admin/Moderator already replied to ticket #{$chat->getNumber()}, ChatGPT skipped." . PHP_EOL;
                return;
            }
            
            $chatHistory = [];
            /** @var SupportMessage[] $histories */
            $histories = SupportMessage::find()
                                       ->andWhere(['support_id' => $this->chatId])
                                       ->andWhere('user_id IS NOT NULL')
                                       ->orderBy(['id' => SORT_ASC])
                                       ->all();
            $isReplay = false;
            foreach ($histories as $history) {
                if ($history->user_id == $chat->user_id) {
                    if (!empty($history->supportFiles)) {
                        $history->message = "Пользователь отправил файл.";
                    }
                    $chatHistory[] = ['user' => $history->message];
                    $isReplay = false;
                } else {
                    $chatHistory[] = ['bot' => $history->message];
                    $isReplay = true;
                }
            }
            if ($isReplay) {
                return;
            }
            $server = $chat->user->getCurrentServer();
            $reply = Yii::$app->openAiSupport->getReply(trim($this->message), $chat->user->username, $server->monitoring_name, $chatHistory, $chat->getNumber(), $chat->user);
            if ($reply == 'unknown') {
                $chat->is_bot = false;
                $chat->save(false);
                return;
            }

            $admin = User::find()
                ->andWhere(['steam_id' => 777])
                ->one();

            if (empty($admin)) {
                $admin = $this->createUser(777, Yii::$app->settings->get('openAi_username'));
            }

            $modelBot = new SupportMessage();
            $modelBot->user_id = $admin->id;
            $modelBot->message = trim($reply);
            $modelBot->support_id = $chat->id;
            $modelBot->created_at = date('Y-m-d H:i:s');
            $modelBot->save();
            SupportRead::createRecord($chat->user_id, $admin->id, $modelBot->id, $chat->id);

            Yii::$app->queueProcess->push(new BeforeMessageJob([
                'chatId' => $chat->id,
                'userId' => $admin->id,
                'message' => trim($reply),
                'username' => "Chat GPT",
                'chatNumber' => $this->chatNumber,
            ]));
            try {
                \console\controllers\ChatServer::broadcastChatUpdate($this->chatNumber, $this->ownerUserId, $modelBot->id);
            } catch (\Exception $ex) {
                Yii::$app->telegramChats->sendMessage('Update chat: ' . $ex->getFile() . ':' . $ex->getLine() . ' ' . $ex->getMessage());
            }

        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("OpenAiJob: " . $e->getLine() . ":" . $e->getMessage());
        }
    }

    private function createUser($steamId, $username) {
        $dbTransaction = Yii::$app->db->beginTransaction();
        try {
            $avatar = Yii::$app->settings->get('openAi_avatar');
            $user           = new User();
            $user->email    = "chatgpt@steam.com";
            $user->steam_id = $steamId;
            $user->auto = 1;
            $user->username = $username;
            $user->updated_at = null;
            $user->setPassword(Yii::$app->security->generateRandomString());
            $user->status = User::STATUS_ACTIVE;
            $user->generateAuthKey();
            $user->generateRefCode();
            $user->generateSocketRoom();
            if ($user->save()) {
                $user->user_id = $user->id;
                $user->update(false, ['user_id']);
                $auth = new Auth(
                    [
                        'user_id'   => $user->id,
                        'source'    => 'steam',
                        'source_id' => (string)$steamId,
                    ]
                );
                $auth->save();
                $dbTransaction->commit();
                UserTree::appendUser($user->id, 509);
                UserProfile::createModel($user, $username);
                $user->userProfile->name = $username;
                // Сохраняем URL аватара из Steam вместо загрузки на сервер
                if (!empty($avatar)) {
                    $user->userProfile->steam_avatar_url = $avatar;
                }
                $user->userProfile->save();
                return $user;
            }
        } catch (\Exception $e) {
            $dbTransaction->rollBack();
            Yii::$app->telegramChats->sendMessage("User findBySteamId: {$steamId} " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
            throw new \Exception(Yii::t('common', 'Произошла ошибка, попробуйте обновить страницу!'));
        }
    }

    public static function _loadImage($imageUrl, $id) {
        $uploadDir = \Yii::getAlias('@frontend/web');
        $fileUrl = "/uploads/avatar/steam/{$id}.png";
        $filePath = $uploadDir . $fileUrl;
        if (!file_exists(dirname(dirname($filePath)))) {
            mkdir(dirname(dirname($filePath)));
            chmod(dirname(dirname($filePath)), 0777);
        }
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath));
            chmod(dirname($filePath), 0777);
        }
        file_put_contents($filePath, file_get_contents($imageUrl));
        return $fileUrl;
    }
}