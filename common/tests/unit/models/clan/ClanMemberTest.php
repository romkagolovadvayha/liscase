<?php

namespace common\tests\unit\models\clan;

use common\models\clan\Clan;
use common\models\clan\ClanMember;
use common\models\clan\ClanPermission;
use common\models\servers\Servers;
use common\models\user\User;

/**
 * Unit tests for ClanMember model
 */
class ClanMemberTest extends \Codeception\Test\Unit
{
    protected function _before()
    {
        // Setup test data
    }

    protected function _after()
    {
        // Cleanup test data
    }

    public function testCreateMember()
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

        $member = new ClanMember();
        $member->clan_id = $clan->id;
        $member->user_id = $user->id;
        $member->role = ClanMember::ROLE_MEMBER;
        $member->join_date = date('Y-m-d H:i:s');

        verify($member->save())->true();
        verify($member->id)->notEmpty();
    }

    public function testIsActive()
    {
        $member = new ClanMember();
        $member->leave_date = null;
        verify($member->isActive())->true();

        $member->leave_date = date('Y-m-d H:i:s');
        verify($member->isActive())->false();
    }

    public function testIsLeader()
    {
        $member = new ClanMember();
        $member->role = ClanMember::ROLE_LEADER;
        verify($member->isLeader())->true();

        $member->role = ClanMember::ROLE_MEMBER;
        verify($member->isLeader())->false();
    }

    public function testHasPermission()
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

        $member = new ClanMember();
        $member->clan_id = $clan->id;
        $member->user_id = $user->id;
        $member->role = ClanMember::ROLE_LEADER;
        $member->join_date = date('Y-m-d H:i:s');
        $member->save();

        // Leader should have all permissions
        verify($member->hasPermission('invite'))->true();
        verify($member->hasPermission('kick'))->true();
        verify($member->canInvite())->true();
        verify($member->canKick())->true();
        verify($member->canPromoteDemote())->true();
        verify($member->canEditClan())->true();
        verify($member->canManagePermissions())->true();
    }

    public function testAddPermission()
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

        $newUser = User::find()->where(['!=', 'id', $user->id])->one();
        if (!$newUser) {
            $this->markTestSkipped('No second user found for testing');
        }

        $member = new ClanMember();
        $member->clan_id = $clan->id;
        $member->user_id = $newUser->id;
        $member->role = ClanMember::ROLE_MEMBER;
        $member->join_date = date('Y-m-d H:i:s');
        $member->save();

        $result = $member->addPermission('invite');
        verify($result)->true();
        verify($member->hasPermission('invite'))->true();
    }

    public function testRemovePermission()
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

        $newUser = User::find()->where(['!=', 'id', $user->id])->one();
        if (!$newUser) {
            $this->markTestSkipped('No second user found for testing');
        }

        $member = new ClanMember();
        $member->clan_id = $clan->id;
        $member->user_id = $newUser->id;
        $member->role = ClanMember::ROLE_MEMBER;
        $member->join_date = date('Y-m-d H:i:s');
        $member->save();

        $member->addPermission('invite');
        verify($member->hasPermission('invite'))->true();

        $result = $member->removePermission('invite');
        verify($result)->true();
        verify($member->hasPermission('invite'))->false();
    }

    public function testSyncPermissions()
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

        $newUser = User::find()->where(['!=', 'id', $user->id])->one();
        if (!$newUser) {
            $this->markTestSkipped('No second user found for testing');
        }

        $member = new ClanMember();
        $member->clan_id = $clan->id;
        $member->user_id = $newUser->id;
        $member->role = ClanMember::ROLE_MEMBER;
        $member->join_date = date('Y-m-d H:i:s');
        $member->save();

        $member->addPermission('invite');
        $member->addPermission('kick');

        $result = $member->syncPermissions(['edit_clan', 'manage_permissions']);
        verify($result)->true();
        verify($member->hasPermission('invite'))->false();
        verify($member->hasPermission('kick'))->false();
        verify($member->hasPermission('edit_clan'))->true();
        verify($member->hasPermission('manage_permissions'))->true();
    }
}

