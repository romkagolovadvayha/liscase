<?php

namespace common\components\queue\clan;

use common\models\clan\ClanRanking;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

/**
 * Job для расчета рейтингов кланов
 */
class CalculateClanRankingsJob extends BaseObject implements JobInterface
{
    public $serverId;

    /**
     * @param \yii\queue\Queue $queue
     * @return void
     */
    public function execute($queue)
    {
        if (!$this->serverId) {
            return;
        }

        $periods = [
            ClanRanking::PERIOD_ALL_TIME,
            ClanRanking::PERIOD_MONTHLY,
            ClanRanking::PERIOD_WEEKLY,
            ClanRanking::PERIOD_CURRENT_WIPE,
        ];

        foreach ($periods as $period) {
            try {
                ClanRanking::calculateRankings($this->serverId, $period);
            } catch (\Exception $e) {
                Yii::error("Error calculating clan rankings for server {$this->serverId}, period {$period}: " . $e->getMessage(), 'clan');
            }
        }
    }
}

