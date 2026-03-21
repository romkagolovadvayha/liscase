<?php

namespace common\tests\unit\models\clan;

use common\models\clan\Clan;
use common\models\clan\ClanInvite;
use common\models\servers\Servers;
use common\models\user\User;

/**
 * Unit tests for ClanInvite model
 */
class ClanInviteTest extends \Codeception\Test\Unit
{
    public function testCreateInvite()
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

        $invite = new ClanInvite();
        $invite->clan_id = $clan->id;
        $invite->inviter_user_id = $user->id;
        $invite->invited_user_id = $newUser->id;

        verify($invite->save())->true();
        verify($invite->id)->notEmpty();
        verify($invite->status)->equals(ClanInvite::STATUS_PENDING);
    }

    public function testIsExpired()
    {
        $invite = new ClanInvite();
        $invite->expires_at = null;
        verify($invite->isExpired())->false();

        $invite->expires_at = date('Y-m-d H:i:s', strtotime('-1 day'));
        verify($invite->isExpired())->true();

        $invite->expires_at = date('Y-m-d H:i:s', strtotime('+1 day'));
        verify($invite->isExpired())->false();
    }

    public function testAccept()
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

        $invite = new ClanInvite();
        $invite->clan_id = $clan->id;
        $invite->inviter_user_id = $user->id;
        $invite->invited_user_id = $newUser->id;
        $invite->save();

        $result = $invite->accept();
        verify($result)->true();
        verify($invite->status)->equals(ClanInvite::STATUS_ACCEPTED);

        // Check if member was added
        $member = \common\models\clan\ClanMember::find()
            ->where(['clan_id' => $clan->id, 'user_id' => $newUser->id])
            ->andWhere(['IS', 'leave_date', null])
            ->one();
        verify($member)->notEmpty();
    }

    public function testDecline()
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

        $invite = new ClanInvite();
        $invite->clan_id = $clan->id;
        $invite->inviter_user_id = $user->id;
        $invite->invited_user_id = $newUser->id;
        $invite->save();

        $result = $invite->decline();
        verify($result)->true();
        verify($invite->status)->equals(ClanInvite::STATUS_DECLINED);
    }
}

