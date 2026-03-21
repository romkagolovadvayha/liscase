<?php

namespace common\tests\unit\models\clan;

use common\models\clan\Clan;
use common\models\clan\ClanMember;
use common\models\clan\ClanEvent;
use common\models\servers\Servers;
use common\models\user\User;

/**
 * Unit tests for Clan model
 */
class ClanTest extends \Codeception\Test\Unit
{
    protected function _before()
    {
        // Setup test data
    }

    protected function _after()
    {
        // Cleanup test data
    }

    public function testCreateClan()
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
        $clan->privacy = Clan::PRIVACY_INVITE_ONLY;

        verify($clan->save())->true();
        verify($clan->id)->notEmpty();
        verify($clan->name)->equals('Test Clan');
        verify($clan->tag)->equals('TEST');
        verify($clan->privacy)->equals(Clan::PRIVACY_INVITE_ONLY);
    }

    public function testClanValidation()
    {
        $clan = new Clan();
        
        // Test required fields
        verify($clan->validate())->false();
        verify($clan->hasErrors('name'))->true();
        verify($clan->hasErrors('tag'))->true();
        verify($clan->hasErrors('leader_user_id'))->true();
        verify($clan->hasErrors('server_id'))->true();
    }

    public function testGetLogoUrl()
    {
        $clan = new Clan();
        $clan->logo = null;
        
        $logoUrl = $clan->getLogoUrl();
        verify($logoUrl)->equals('/images/default-clan-logo.png');
    }

    public function testGetPrivacyLabel()
    {
        $clan = new Clan();
        $clan->privacy = Clan::PRIVACY_OPEN;
        verify($clan->getPrivacyLabel())->notEmpty();
        
        $clan->privacy = Clan::PRIVACY_CLOSED;
        verify($clan->getPrivacyLabel())->notEmpty();
        
        $clan->privacy = Clan::PRIVACY_INVITE_ONLY;
        verify($clan->getPrivacyLabel())->notEmpty();
    }

    public function testIsOpen()
    {
        $clan = new Clan();
        $clan->privacy = Clan::PRIVACY_OPEN;
        verify($clan->isOpen())->true();
        
        $clan->privacy = Clan::PRIVACY_CLOSED;
        verify($clan->isOpen())->false();
    }

    public function testIsClosed()
    {
        $clan = new Clan();
        $clan->privacy = Clan::PRIVACY_CLOSED;
        verify($clan->isClosed())->true();
        
        $clan->privacy = Clan::PRIVACY_OPEN;
        verify($clan->isClosed())->false();
    }

    public function testIsInviteOnly()
    {
        $clan = new Clan();
        $clan->privacy = Clan::PRIVACY_INVITE_ONLY;
        verify($clan->isInviteOnly())->true();
        
        $clan->privacy = Clan::PRIVACY_OPEN;
        verify($clan->isInviteOnly())->false();
    }

    public function testAddEvent()
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

        $result = $clan->addEvent('clan_created', 'Test event', $user->id);
        verify($result)->true();

        $event = ClanEvent::find()
            ->where(['clan_id' => $clan->id, 'event_type' => 'clan_created'])
            ->one();
        verify($event)->notEmpty();
    }

    public function testAddMember()
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

        $member = $clan->addMember($newUser->id);
        verify($member)->notEmpty();
        verify($member->clan_id)->equals($clan->id);
        verify($member->user_id)->equals($newUser->id);
        verify($member->role)->equals(ClanMember::ROLE_MEMBER);
    }

    public function testRemoveMember()
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

        $member = $clan->addMember($newUser->id);
        verify($member)->notEmpty();

        $result = $clan->removeMember($newUser->id);
        verify($result)->true();

        $member = ClanMember::find()
            ->where(['clan_id' => $clan->id, 'user_id' => $newUser->id])
            ->andWhere(['IS', 'leave_date', null])
            ->one();
        verify($member)->empty();
    }
}

