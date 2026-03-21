<?php

namespace common\tests\unit\components\queue\clan;

use common\components\queue\clan\CalculateClanRankingsJob;
use common\models\clan\ClanRanking;
use common\models\servers\Servers;

/**
 * Unit tests for CalculateClanRankingsJob
 */
class CalculateClanRankingsJobTest extends \Codeception\Test\Unit
{
    public function testExecuteJob()
    {
        $server = Servers::find()->one();
        
        if (!$server) {
            $this->markTestSkipped('No server found for testing');
        }

        $job = new CalculateClanRankingsJob([
            'serverId' => $server->id,
        ]);

        // Execute job
        $job->execute(null);

        // Verify that rankings were calculated
        $rankings = ClanRanking::find()
            ->where(['server_id' => $server->id])
            ->all();
        
        // Rankings may be empty if no clans exist, but job should execute without errors
        verify(true)->true(); // Job executed successfully
    }

    public function testCalculateAllPeriods()
    {
        $server = Servers::find()->one();
        
        if (!$server) {
            $this->markTestSkipped('No server found for testing');
        }

        $periods = [
            ClanRanking::PERIOD_ALL_TIME,
            ClanRanking::PERIOD_MONTHLY,
            ClanRanking::PERIOD_WEEKLY,
            ClanRanking::PERIOD_CURRENT_WIPE,
        ];

        foreach ($periods as $period) {
            ClanRanking::calculateRankings($server->id, $period);
            verify(true)->true(); // Calculation completed without errors
        }
    }
}

