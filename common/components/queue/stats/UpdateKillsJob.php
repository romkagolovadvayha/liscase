<?php

namespace common\components\queue\stats;

use common\components\oauth\Steam;
use common\models\rcon\RconTasks;
use common\models\servers\Servers;
use common\models\statistics\Chats;
use common\models\statistics\Kills;
use common\models\statistics\Reports;
use common\models\statistics\Statistics;
use common\models\statistics\Teams;
use common\models\team\Team;
use common\models\user\User;
use common\models\user\UserTop;
use Yii;
use yii\base\BaseObject;
use yii\db\IntegrityException;
use yii\queue\JobInterface;

class UpdateKillsJob extends BaseObject implements JobInterface
{
    public $item;
    public $serverTag;
    public $wipeDate;
    public $serverId;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        $item = is_array($this->item) ? $this->item : [];
        $transaction = null;
        try {
            $steamId = (string)($item['steam_id'] ?? '');
            $type = (string)($item['type'] ?? '');
            $dead = (string)($item['dead'] ?? '');
            if (!preg_match('/^\d{17}$/', $steamId) || !in_array($type, ['kill', 'animal', 'deaths'], true)) {
                throw new \InvalidArgumentException('Invalid kill event');
            }

            $inventoryWear = is_array($item['inventoryWear'] ?? null) ? $item['inventoryWear'] : [];
            $signs = is_array($item['signs'] ?? null) ? $item['signs'] : [];
            $transaction = Yii::$app->db->beginTransaction();
            $model = new Kills();
            $model->event_id = !empty($item['event_id']) ? substr((string)$item['event_id'], 0, 64) : null;
            $model->steam_id = $steamId;
            $model->type = $type;
            $model->dead = substr($dead, 0, 255);
            $model->weapon = substr((string)($item['weapon'] ?? ''), 0, 255);
            $model->distance = max(0, min(100000, (int)($item['distance'] ?? 0)));
            $model->created_at = (string)($item['date'] ?? date('Y-m-d H:i:s'));
            $model->server_tag = $this->serverTag;
            $model->wipe = $this->wipeDate;

            if ($signs !== []) {
                $model->signs = json_encode(array_slice($signs, 0, 32));
            }
            if ($inventoryWear !== []) {
                $model->wears = json_encode(array_slice($inventoryWear, 0, 128));
            }

            if (!$model->save(false)) {
                throw new \RuntimeException('Failed to save kill event');
            }

            if ($type === 'kill') {
                $rows = [];
                if ($inventoryWear === [] && $signs === []) {
                    $rows[] = [$steamId, $this->serverTag, 'nude_kills', 1, $this->wipeDate];
                }
                if ($signs === []) {
                    $rows[] = [$steamId, $this->serverTag, 'kills', 1, $this->wipeDate];
                }
                if (preg_match('/^\d{17}$/', $dead)) {
                    $rows[] = [$dead, $this->serverTag, 'deaths', 1, $this->wipeDate];
                }
                Statistics::batchUpsertIncrementValues($rows);
            }
            $transaction->commit();
        } catch (IntegrityException $e) {
            if ($transaction !== null && $transaction->isActive) {
                $transaction->rollBack();
            }
            if (!empty($item['event_id']) && $this->isDuplicateKey($e)) {
                return;
            }
            throw $e;
        } catch (\Exception $e) {
            if ($transaction !== null && $transaction->isActive) {
                $transaction->rollBack();
            }
            Yii::$app->telegramChats->sendMessage("UpdateKillsJob" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
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
