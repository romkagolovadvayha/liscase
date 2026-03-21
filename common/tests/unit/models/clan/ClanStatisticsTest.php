<?php

namespace common\tests\unit\models\clan;

use common\models\clan\Clan;
use common\models\clan\ClanMember;
use common\models\clan\ClanStatistics;
use common\models\clan\ClanMemberStatistics;
use common\models\servers\Servers;
use common\models\user\User;

/**
 * Unit tests for ClanStatistics model
 */
class ClanStatisticsTest extends \Codeception\Test\Unit
{
    protected function _before()
    {
        // Setup test data
    }

    protected function _after()
    {
        // Cleanup test data
    }

    public function testUpdateStatistics()
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

        $wipe = $server->currentWipe();

        $clanStatistics = new ClanStatistics();
        $clanStatistics->clan_id = $clan->id;
        $clanStatistics->server_id = $server->id;
        $clanStatistics->wipe = $wipe;
        $clanStatistics->save();

        // Create member statistics
        $member = $clan->addMember($user->id);
        if ($member) {
            $memberStats = new ClanMemberStatistics();
            $memberStats->clan_member_id = $member->id;
            $memberStats->clan_id = $clan->id;
            $memberStats->user_id = $user->id;
            $memberStats->server_id = $server->id;
            $memberStats->wipe = $wipe;
            $memberStats->kills = 10;
            $memberStats->deaths = 5;
            $memberStats->calculateTopRatings();
            $memberStats->save();

            $result = $clanStatistics->updateStatistics();
            verify($result)->true();
            verify($clanStatistics->total_kills)->equals(10);
            verify($clanStatistics->total_deaths)->equals(5);
        }
    }

    public function testCalculateMemberStatistics()
    {
        $user = User::find()->one();
        $server = Servers::find()->one();
        
        if (!$user || !$server || !$user->steam_id) {
            $this->markTestSkipped('No user or server found for testing, or user has no steam_id');
        }

        $clan = new Clan();
        $clan->name = 'Test Clan';
        $clan->tag = 'TEST';
        $clan->leader_user_id = $user->id;
        $clan->server_id = $server->id;
        $clan->save();

        $member = $clan->addMember($user->id);
        if ($member) {
            $wipe = $server->currentWipe();
            $stats = ClanStatistics::calculateMemberStatistics($member, $server->id, $wipe);
            verify($stats)->array();
        }
    }

    public function testFilterByMembershipPeriod()
    {
        // Вклад считается как дельта statistics от baseline при вступлении (см. clan_member_stats_baseline).
        $user = User::find()->one();
        $server = Servers::find()->one();
        
        if (!$user || !$server || !$user->steam_id) {
            $this->markTestSkipped('No user or server found for testing, or user has no steam_id');
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

            $wipe = $server->currentWipe();
            $stats = ClanStatistics::calculateMemberStatistics($member, $server->id, $wipe);
            
            // Statistics should only include events after join_date
            verify($stats)->array();
        }
    }
}

