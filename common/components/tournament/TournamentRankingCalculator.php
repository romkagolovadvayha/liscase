<?php

namespace common\components\tournament;

use common\models\tournament\Tournament;
use common\models\tournament\TournamentParticipant;
use common\models\tournament\TournamentRanking;
use common\models\tournament\TournamentRegistration;
use common\models\user\UserRaid;
use Yii;

/**
 * Пересчёт рейтинга турнира: сумма score по real_raid в окне турнира на сервере турнира.
 */
class TournamentRankingCalculator
{
    public static function recalculate(Tournament $tournament): void
    {
        $tid = (int)$tournament->id;
        if ($tid <= 0) {
            return;
        }

        $registrations = TournamentRegistration::find()
            ->where(['tournament_id' => $tid])
            ->all();

        if ($registrations === []) {
            TournamentRanking::deleteAll(['tournament_id' => $tid]);
            return;
        }

        $registrationByClan = [];
        $registrationIds = [];
        foreach ($registrations as $reg) {
            $registrationByClan[(int)$reg->clan_id] = $reg;
            $registrationIds[] = (int)$reg->id;
        }

        $participantUserIdsByClan = [];
        $hasRosterLimit = $tournament->max_participants_per_clan !== null && (int)$tournament->max_participants_per_clan > 0;
        if ($hasRosterLimit && $registrationIds !== []) {
            $rows = TournamentParticipant::find()
                ->alias('tp')
                ->select(['tp.user_id', 'tr.clan_id'])
                ->innerJoin(['tr' => TournamentRegistration::tableName()], 'tr.id = tp.registration_id')
                ->where(['tp.registration_id' => $registrationIds])
                ->asArray()
                ->all();
            foreach ($rows as $row) {
                $cid = (int)($row['clan_id'] ?? 0);
                $uid = (int)($row['user_id'] ?? 0);
                if ($cid <= 0 || $uid <= 0) {
                    continue;
                }
                $participantUserIdsByClan[$cid][$uid] = true;
            }
        }

        $scores = static::sumRaidScoresByClan($tournament, $registrationByClan, $participantUserIdsByClan, $hasRosterLimit);

        $now = time();
        foreach ($registrationByClan as $clanId => $reg) {
            $score = (float)($scores[$clanId] ?? 0.0);
            $ranking = TournamentRanking::find()
                ->where(['tournament_id' => $tid, 'clan_id' => $clanId])
                ->one();
            if (!$ranking) {
                $ranking = new TournamentRanking();
                $ranking->tournament_id = $tid;
                $ranking->clan_id = $clanId;
            }
            $ranking->score = $score;
            $ranking->calculated_at = $now;
            $ranking->save(false);
        }

        $validClanIds = array_keys($registrationByClan);
        if ($validClanIds === []) {
            TournamentRanking::deleteAll(['tournament_id' => $tid]);
        } else {
            TournamentRanking::deleteAll([
                'and',
                ['tournament_id' => $tid],
                ['not in', 'clan_id', $validClanIds],
            ]);
        }

        static::updatePositions($tid);
    }

    /**
     * @param array<int, TournamentRegistration> $registrationByClan
     * @param array<int, array<int, bool>> $participantUserIdsByClan
     * @return array<int, float> clan_id => score
     */
    private static function sumRaidScoresByClan(
        Tournament $tournament,
        array $registrationByClan,
        array $participantUserIdsByClan,
        bool $hasRosterLimit
    ): array {
        $serverId = (int)$tournament->server_id;
        $clanIds = array_keys($registrationByClan);
        if ($clanIds === []) {
            return [];
        }

        $q = UserRaid::find()
            ->alias('ur')
            ->select([
                'ur.raider_clan_id',
                'SUM(COALESCE([[ur.score]], 0)) AS raid_total',
            ])
            ->where(['ur.server_id' => $serverId])
            ->andWhere(['in', 'ur.raider_clan_id', $clanIds])
            ->andWhere(['>', 'ur.raider_clan_id', 0]);

        $schema = Yii::$app->db->getTableSchema(UserRaid::tableName(), true);
        if ($schema === null || $schema->getColumn('real_raid') === null) {
            return [];
        }
        $q->andWhere(['ur.real_raid' => 1]);

        $startsAt = (string)$tournament->starts_at;
        $endsAt = (string)$tournament->ends_at;
        if ($startsAt !== '') {
            $q->andWhere(['>=', 'ur.created_at', $startsAt]);
        }
        if ($endsAt !== '') {
            $q->andWhere(['<=', 'ur.created_at', $endsAt]);
        }

        // Очки только после регистрации клана в турнире
        $q->innerJoin(
            ['tr' => TournamentRegistration::tableName()],
            'tr.clan_id = ur.raider_clan_id AND tr.tournament_id = :tid',
            [':tid' => (int)$tournament->id]
        );
        $q->andWhere('ur.created_at >= tr.registered_at');

        if ($hasRosterLimit) {
            $q->innerJoin(
                ['tp' => TournamentParticipant::tableName()],
                'tp.registration_id = tr.id AND tp.user_id = ur.user_id'
            );
        }

        $q->groupBy(['ur.raider_clan_id']);
        $rows = $q->asArray()->all();
        $out = [];
        foreach ($rows as $row) {
            $cid = (int)($row['raider_clan_id'] ?? 0);
            if ($cid <= 0) {
                continue;
            }
            $out[$cid] = (float)($row['raid_total'] ?? 0);
        }
        return $out;
    }

    private static function updatePositions(int $tournamentId): void
    {
        $rankings = TournamentRanking::find()
            ->where(['tournament_id' => $tournamentId])
            ->orderBy(['score' => SORT_DESC, 'clan_id' => SORT_ASC])
            ->all();

        $position = 1;
        foreach ($rankings as $ranking) {
            $ranking->position = $position++;
            $ranking->save(false);
        }
    }
}
