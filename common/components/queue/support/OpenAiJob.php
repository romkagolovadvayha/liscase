<?php

namespace common\components\queue\support;

use common\components\openAi\OpenAiSettings;
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
    public $messageId;
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
        if (!OpenAiSettings::isEnabled(OpenAiSettings::SUPPORT)) {
            if (!empty($this->chatId) && !empty($this->messageId)) {
                Yii::$app->cache->delete('openai_support_job:' . $this->chatId . ':' . $this->messageId);
            }
            return;
        }

        echo $this->message . PHP_EOL;
        try {
            $chat = Support::findOne($this->chatId);
            if ($chat === null || $chat->status !== Support::STATUS_OPEN || !$chat->is_bot) {
                return;
            }

            // Не отвечаем на устаревшую job, если после её постановки пользователь уже написал ещё раз.
            if (!empty($this->messageId)) {
                $latestMessageId = SupportMessage::find()
                    ->select('id')
                    ->andWhere(['support_id' => $this->chatId])
                    ->orderBy(['id' => SORT_DESC])
                    ->scalar();
                if ((int)$latestMessageId !== (int)$this->messageId) {
                    return;
                }
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
                                       ->with('supportFiles')
                                       ->andWhere(['support_id' => $this->chatId])
                                       ->andWhere('user_id IS NOT NULL')
                                       ->orderBy(['id' => SORT_ASC])
                                       ->all();
            $isReplay = false;
            foreach ($histories as $history) {
                if ($history->user_id == $chat->user_id) {
                    $images = $this->collectImageUrls($history);
                    $text = trim((string)$history->message);
                    if ($text === '') {
                        $text = !empty($images)
                            ? 'Пользователь отправил скриншот.'
                            : (!empty($history->supportFiles) ? 'Пользователь отправил файл.' : '');
                    }
                    $entry = ['user' => $text];
                    if (!empty($images)) {
                        $entry['images'] = $images;
                    }
                    $chatHistory[] = $entry;
                    $isReplay = false;
                } else {
                    $chatHistory[] = ['bot' => $history->message];
                    $isReplay = true;
                }
            }
            if ($isReplay) {
                return;
            }
            // Сервер, выбранный в самом тикете, точнее текущего сервера игрока.
            $server = $chat->server ?: $chat->user->getCurrentServer();

            // Один вызов API: повтор на пустом ответе только жег токены (GPT-5 reasoning),
            // не меняя результат.
            $candidate = Yii::$app->openAiSupport->getReply(
                trim((string)$this->message),
                $chat->user->username,
                $server ? (string)$server->monitoring_name : '',
                $chatHistory,
                $chat->getNumber(),
                $chat->user,
                false,
                [],
                $server ? (string)$server->tag : ($chat->server_tag ?: null)
            );

            if (!$this->hasVisibleText($candidate)) {
                Yii::warning([
                    'message' => 'OpenAiJob received an empty reply',
                    'ticket_id' => $chat->id,
                    'ticket_number' => $chat->getNumber(),
                    'source_message_id' => $this->messageId,
                ], __METHOD__);
                $this->handOffToStaff($chat, 'OpenAI returned an empty reply');
                return;
            }

            $parsedReply = self::parseReply((string)$candidate);
            $reply = $parsedReply['message'];
            if (!$this->hasVisibleText($reply)) {
                $this->handOffToStaff($chat, 'OpenAI requested staff handoff');
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
            if (!$modelBot->save()) {
                throw new \RuntimeException(
                    'Failed to save OpenAI support reply: ' . json_encode($modelBot->getErrors(), JSON_UNESCAPED_UNICODE)
                );
            }
            SupportRead::createRecord($chat->user_id, $admin->id, $modelBot->id, $chat->id);

            Yii::$app->queueProcess->push(new BeforeMessageJob([
                'chatId' => $chat->id,
                'userId' => $admin->id,
                'message' => trim($reply),
                'username' => "Chat GPT",
                'chatNumber' => $this->chatNumber,
                'messageId' => $modelBot->id,
            ]));
            try {
                \console\controllers\ChatServer::broadcastChatUpdate($this->chatNumber, $this->ownerUserId, $modelBot->id);
            } catch (\Exception $ex) {
                Yii::$app->telegramChats->sendMessage('Update chat: ' . $ex->getFile() . ':' . $ex->getLine() . ' ' . $ex->getMessage());
            }

            if ($parsedReply['handoff']) {
                $this->handOffToStaff($chat, 'OpenAI marked reply for staff handoff');
            }

        } catch (\Throwable $e) {
            if (isset($chat) && $chat instanceof Support) {
                $this->handOffToStaff($chat, 'Unexpected OpenAiJob error: ' . $e->getMessage());
            }
            Yii::$app->telegramChats->sendMessage("OpenAiJob: " . $e->getLine() . ":" . $e->getMessage());
        }
    }

    private function hasVisibleText($reply): bool
    {
        if (!is_string($reply)) {
            return false;
        }

        $plainText = str_replace(['&nbsp;', "\xc2\xa0"], ' ', $reply);
        $plainText = html_entity_decode(strip_tags($plainText), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($plainText) !== '';
    }

    /**
     * Удаляет служебный маркер из видимого ответа и сообщает, надо ли отключить бота.
     * Legacy-ответ unknown тоже остаётся поддержанным.
     *
     * @return array{message:string,handoff:bool}
     */
    public static function parseReply(string $reply): array
    {
        $reply = trim($reply);
        if (strcasecmp(trim(strip_tags($reply)), 'unknown') === 0) {
            return ['message' => '', 'handoff' => true];
        }

        $markerPattern = '/\[\[\s*STAFF_HANDOFF\s*\]\]/iu';
        $handoff = preg_match($markerPattern, $reply) === 1;
        $message = preg_replace($markerPattern, '', $reply);

        return [
            'message' => trim(is_string($message) ? $message : $reply),
            'handoff' => $handoff,
        ];
    }

    private function handOffToStaff(Support $chat, string $reason): void
    {
        if ($chat->is_bot) {
            $chat->is_bot = false;
            if (!$chat->save(false, ['is_bot'])) {
                Yii::error([
                    'message' => 'Failed to disable support bot',
                    'ticket_id' => $chat->id,
                    'reason' => $reason,
                ], __METHOD__);
            }
        }

        Yii::error([
            'message' => 'Support ticket handed off to staff',
            'ticket_id' => $chat->id,
            'ticket_number' => $chat->getNumber(),
            'source_message_id' => $this->messageId,
            'reason' => $reason,
        ], __METHOD__);
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

    /**
     * Публичные URL картинок из вложений сообщения (для vision API).
     *
     * @return string[]
     */
    private function collectImageUrls(SupportMessage $message): array
    {
        $urls = [];
        $imageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

        foreach ($message->supportFiles as $file) {
            $mime = strtolower((string)$file->mimetype);
            $ext = strtolower(pathinfo((string)$file->file, PATHINFO_EXTENSION));
            $isImage = (strpos($mime, 'image/') === 0 && $mime !== 'image/svg+xml')
                || ($mime === '' && in_array($ext, $imageExt, true));

            if (!$isImage) {
                continue;
            }

            $url = $file->getPublicUrl();
            if ($url !== '') {
                $urls[] = $url;
            }
        }

        return array_slice($urls, 0, 4);
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
