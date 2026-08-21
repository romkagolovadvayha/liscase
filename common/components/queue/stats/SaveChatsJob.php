<?php

namespace common\components\queue\stats;

use common\components\queue\telegram\SendMessageJob;
use common\models\servers\Servers;
use common\models\statistics\Chats;
use common\models\teams\Teams;
use common\models\user\User;
use common\models\user\UserRaid;
use Yii;
use yii\base\BaseObject;
use yii\db\IntegrityException;
use yii\queue\JobInterface;

class SaveChatsJob extends BaseObject implements JobInterface
{
    public $messages;
    public $serverTag;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            foreach ($this->messages as $item) {
                if (!is_array($item)) {
                    throw new \InvalidArgumentException('Invalid chat event');
                }
                $steamId = (string)($item['steam_id'] ?? '');
                $message = (string)($item['message'] ?? '');
                if (!preg_match('/^\d{17}$/', $steamId) || $message === '') {
                    throw new \InvalidArgumentException('Invalid chat event');
                }
                $model = new Chats();
                $model->event_id = !empty($item['event_id']) ? substr((string)$item['event_id'], 0, 64) : null;
                $model->steam_id = $steamId;
                $model->message = mb_substr($message, 0, 2000);
                $model->created_at = (string)($item['created_at'] ?? date('Y-m-d H:i:s'));
                $model->server_tag = $this->serverTag;
                try {
                    if (!$model->save(false)) {
                        throw new \RuntimeException('Failed to save chat event');
                    }
                } catch (IntegrityException $e) {
                    if (!empty($item['event_id']) && $this->isDuplicateKey($e)) {
                        continue;
                    }
                    throw $e;
                }

                $type = Chats::getMuteType($message);
                Chats::mute($type, $message, $steamId);
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("SaveChatsJob: " . $e->getFile() . ":" . $e->getLine() . ": " . $e->getMessage());
            throw $e;
        }
    }

    private function isDuplicateKey(IntegrityException $e): bool
    {
        $info = $e->errorInfo ?? [];
        return (isset($info[1]) && (int)$info[1] === 1062)
            || stripos($e->getMessage(), 'Duplicate entry') !== false
            || stripos($e->getMessage(), 'UNIQUE constraint failed') !== false;
    }
}
