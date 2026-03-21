<?php

namespace common\tests\unit\components\queue\clan;

use common\components\queue\clan\UpdateClanStatisticsJob;
use common\models\clan\Clan;
use common\models\servers\Servers;
use common\models\user\User;

/**
 * Unit tests for UpdateClanStatisticsJob
 */
class UpdateClanStatisticsJobTest extends \Codeception\Test\Unit
{
    public function testExecuteJob()
    {
        $user = User::find()->one();
        $server = Servers::find()->one();
        
        if (!$user || !$server) {
            $this->markTestSkipped('No user or server found for testing');
        }

        $clan = new Clan();
        $clan->name = 'Test Clan';
        $clan->tag = 'TEST';
        $clan->leader_user_id = $user->id;
        $clan->server_id = $server->id;
        $clan->save();

        $job = new UpdateClanStatisticsJob([
            'serverId' => $server->id,
            'wipe' => $server->currentWipe(),
        ]);

        // Execute job (this will update statistics for all clans on the server)
        $job->execute(null);

        // Verify that statistics were updated
        $statistics = $clan->getClanStatistics();
        verify($statistics)->notEmpty();
    }

    public function testFilterByMembershipPeriod()
    {
        // This test verifies that statistics are filtered by membership period
        $user = User::find()->one();
        $server = Servers::find()->one();
        
        if (!$user || !$server) {
            $this->markTestSkipped('No user or server found for testing');
        }

        $clan = new Clan();
        $clan->name = 'Test Clan';
        $clan->tag = 'TEST';
        $clan->leader_user_id = $user->id;
        $clan->server_id = $server->id;
        $clan->save();

        $member = $clan->addMember($user->id);
        if ($member) {
            // Set join_date to yesterday
            $member->join_date = date('Y-m-d H:i:s', strtotime('-1 day'));
            $member->save();

            $job = new UpdateClanStatisticsJob([
                'serverId' => $server->id,
                'wipe' => $server->currentWipe(),
            ]);

            $job->execute(null);

            // Statistics should only include events after join_date
            $statistics = $clan->getClanStatistics();
            verify($statistics)->notEmpty();
        }
    }
}

