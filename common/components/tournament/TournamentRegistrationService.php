<?php

namespace common\components\tournament;

use common\models\clan\Clan;
use common\models\clan\ClanMember;
use common\models\tournament\Tournament;
use common\models\tournament\TournamentParticipant;
use common\models\tournament\TournamentRegistration;
use common\models\user\User;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

/**
 * Регистрация клана в турнире и дозаполнение состава.
 */
class TournamentRegistrationService
{
    /**
     * @param int[] $memberUserIds
     */
    public static function registerClan(Tournament $tournament, User $actor, array $memberUserIds): TournamentRegistration
    {
        static::assertCanManageRegistration($tournament, $actor);
        $member = static::requireOfficerMembership($tournament, $actor);
        $clan = $member->clan;

        if (TournamentRegistration::find()
            ->where(['tournament_id' => (int)$tournament->id, 'clan_id' => (int)$clan->id])
            ->exists()) {
            throw new BadRequestHttpException('Clan is already registered');
        }

        if (!$tournament->canAcceptMoreClans()) {
            throw new ForbiddenHttpException('Tournament clan limit reached');
        }

        $phase = $tournament->getPublicPhase();
        if ($phase === Tournament::PHASE_PAST) {
            throw new ForbiddenHttpException('Tournament has ended');
        }
        if (!$tournament->isRegistrationOpen()) {
            throw new ForbiddenHttpException('Registration is closed');
        }

        $ids = static::normalizeMemberIds($memberUserIds, $tournament, $clan, true);

        $tx = Yii::$app->db->beginTransaction();
        try {
            $reg = new TournamentRegistration();
            $reg->tournament_id = (int)$tournament->id;
            $reg->clan_id = (int)$clan->id;
            $reg->registered_by_user_id = (int)$actor->id;
            $reg->registered_at = date('Y-m-d H:i:s');
            if (!$reg->save()) {
                throw new BadRequestHttpException(implode(' ', $reg->getFirstErrors()));
            }
            static::insertParticipants($reg, $ids);
            $tx->commit();
            return $reg;
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    /**
     * @param int[] $memberUserIds только новые id
     */
    public static function addParticipants(Tournament $tournament, User $actor, array $memberUserIds): TournamentRegistration
    {
        static::assertCanManageRegistration($tournament, $actor);
        $member = static::requireOfficerMembership($tournament, $actor);
        $clan = $member->clan;

        $reg = TournamentRegistration::find()
            ->where(['tournament_id' => (int)$tournament->id, 'clan_id' => (int)$clan->id])
            ->one();
        if (!$reg) {
            throw new BadRequestHttpException('Clan is not registered');
        }

        if ($tournament->getPublicPhase() === Tournament::PHASE_PAST) {
            throw new ForbiddenHttpException('Tournament has ended');
        }

        $existing = $reg->getParticipantUserIds();
        $newOnly = [];
        foreach ($memberUserIds as $uid) {
            $uid = (int)$uid;
            if ($uid <= 0) {
                continue;
            }
            if (in_array($uid, $existing, true)) {
                throw new ForbiddenHttpException('Cannot change or remove registered participants');
            }
            $newOnly[] = $uid;
        }
        if ($newOnly === []) {
            throw new BadRequestHttpException('member_user_ids required');
        }

        $max = $tournament->max_participants_per_clan !== null ? (int)$tournament->max_participants_per_clan : null;
        if ($max !== null && $max > 0) {
            if (count($existing) + count($newOnly) > $max) {
                throw new ForbiddenHttpException('Participant limit reached');
            }
        }

        static::validateMembersBelongToClan($clan, $newOnly);

        $tx = Yii::$app->db->beginTransaction();
        try {
            static::insertParticipants($reg, $newOnly);
            $tx->commit();
            return $reg;
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    private static function assertCanManageRegistration(Tournament $tournament, User $actor): void
    {
        if (!$tournament->isPubliclyVisible()) {
            throw new ForbiddenHttpException('Tournament not available');
        }
    }

    private static function requireOfficerMembership(Tournament $tournament, User $actor): ClanMember
    {
        $member = ClanMember::find()
            ->alias('m')
            ->innerJoin(['c' => Clan::tableName()], 'c.id = m.clan_id')
            ->where([
                'm.user_id' => (int)$actor->id,
                'c.server_id' => (int)$tournament->server_id,
            ])
            ->andWhere(['IS', 'm.leave_date', null])
            ->with('clan')
            ->one();

        if (!$member) {
            throw new ForbiddenHttpException('You must be in a clan on this tournament server');
        }
        if (!$member->isLeader() && !$member->isOfficer()) {
            throw new ForbiddenHttpException('Only clan leader or officer can register');
        }
        return $member;
    }

    /**
     * @param int[] $memberUserIds
     * @return int[]
     */
    private static function normalizeMemberIds(array $memberUserIds, Tournament $tournament, Clan $clan, bool $isNewRegistration): array
    {
        $ids = [];
        foreach ($memberUserIds as $uid) {
            $uid = (int)$uid;
            if ($uid > 0) {
                $ids[$uid] = true;
            }
        }
        $ids = array_keys($ids);

        $max = $tournament->max_participants_per_clan !== null ? (int)$tournament->max_participants_per_clan : null;
        if ($max !== null && $max > 0) {
            if ($isNewRegistration && $ids === []) {
                throw new BadRequestHttpException('Select at least one participant');
            }
            if (count($ids) > $max) {
                throw new BadRequestHttpException('Too many participants selected');
            }
        }

        if ($ids !== []) {
            static::validateMembersBelongToClan($clan, $ids);
        }
        return $ids;
    }

    /**
     * @param int[] $userIds
     */
    private static function validateMembersBelongToClan(Clan $clan, array $userIds): void
    {
        $active = ClanMember::find()
            ->select('user_id')
            ->where(['clan_id' => (int)$clan->id])
            ->andWhere(['IS', 'leave_date', null])
            ->andWhere(['in', 'user_id', $userIds])
            ->column();
        $active = array_map('intval', $active);
        foreach ($userIds as $uid) {
            if (!in_array((int)$uid, $active, true)) {
                throw new BadRequestHttpException('Invalid clan member in selection');
            }
        }
    }

    /**
     * @param int[] $userIds
     */
    private static function insertParticipants(TournamentRegistration $reg, array $userIds): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ($userIds as $uid) {
            $p = new TournamentParticipant();
            $p->registration_id = (int)$reg->id;
            $p->user_id = (int)$uid;
            $p->added_at = $now;
            if (!$p->save()) {
                throw new BadRequestHttpException(implode(' ', $p->getFirstErrors()));
            }
        }
    }
}
