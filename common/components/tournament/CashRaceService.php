<?php

namespace common\components\tournament;

use common\components\helpers\Role;
use common\models\medals\Medal;
use common\models\medals\UserMedal;
use common\models\servers\Servers;
use common\models\tournament\CashRaceDeposit;
use common\models\tournament\CashRaceKeyToken;
use common\models\tournament\CashRaceScore;
use common\models\tournament\CashRaceTerminalSession;
use common\models\tournament\CashRaceTournament;
use common\models\tournament\Tournament;
use common\models\user\User;
use Yii;

final class CashRaceService
{
    public const PREVIEW_STEAM_ID = '76561198394504608';
    private const LEADERBOARD_CACHE_TTL = 10;

    public static function canPreview(?User $user, ?CashRaceTournament $config = null): bool
    {
        if (!$user) return false;
        if ($user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT])) return true;
        $steamId = $config && $config->preview_steam_id ? (string)$config->preview_steam_id : self::PREVIEW_STEAM_ID;
        return hash_equals($steamId, (string)$user->steam_id);
    }

    public static function canPlayerParticipate(User $user, CashRaceTournament $config, bool $serverAdmin = false): bool
    {
        return true;
    }

    public static function findCurrent(?int $serverId = null, bool $withRewards = false): ?CashRaceTournament
    {
        $base = static function () use ($serverId) {
            $query = CashRaceTournament::find()->alias('cr')
            ->innerJoin(['t' => Tournament::tableName()], 't.id = cr.tournament_id')
            ->where(['t.type' => Tournament::TYPE_CASH_RACE, 't.status' => Tournament::STATUS_PUBLISHED]);
            if ($serverId !== null) $query->andWhere(['t.server_id' => $serverId]);
            return $query;
        };
        $relations = $withRewards ? ['tournament.server', 'tournament.rewards'] : ['tournament.server'];
        $current = $base()->andWhere(['>=', 't.ends_at', date('Y-m-d H:i:s')])
            ->orderBy(['t.starts_at' => SORT_ASC, 't.id' => SORT_DESC])
            ->with($relations)->one();
        if ($current) return $current;
        return $base()->andWhere(['>=', 't.ends_at', date('Y-m-d H:i:s', time() - 30 * 86400)])
            ->orderBy(['t.ends_at' => SORT_DESC, 't.id' => SORT_DESC])
            ->with($relations)->one();
    }

    public static function score(Tournament $tournament, User $user): CashRaceScore
    {
        $score = CashRaceScore::findOne(['tournament_id' => $tournament->id, 'user_id' => $user->id]);
        if ($score) return $score;
        $now = time();
        Yii::$app->db->createCommand()->upsert(CashRaceScore::tableName(), [
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
            'steam_id' => (string)$user->steam_id,
            'keys_found' => 0,
            'keys_lost' => 0,
            'keys_deposited' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['updated_at' => $now])->execute();
        $score = CashRaceScore::findOne(['tournament_id' => $tournament->id, 'user_id' => $user->id]);
        if (!$score) throw new \RuntimeException('CASH_RACE_SCORE_CREATE_FAILED');
        return $score;
    }

    /** @return CashRaceKeyToken[] */
    public static function mint(CashRaceTournament $config, Servers $server, User $user, array $uuids): array
    {
        $tournament = $config->tournament;
        if (!$tournament || $tournament->getPublicPhase() !== Tournament::PHASE_ACTIVE) throw new \DomainException('TOURNAMENT_NOT_ACTIVE');
        $uuids = array_values(array_unique(array_filter($uuids, [self::class, 'validUuid'])));
        if (!$uuids || count($uuids) > 10) throw new \DomainException('INVALID_TOKEN_BATCH');

        $tx = Yii::$app->db->beginTransaction();
        try {
            $score = self::score($tournament, $user);
            $models = [];
            $rows = [];
            $now = time();
            $issuedAt = date('Y-m-d H:i:s', $now);
            foreach ($uuids as $uuid) {
                $attributes = [
                    'token_uuid' => $uuid, 'tournament_id' => $tournament->id, 'server_id' => $server->id,
                    'user_id' => $user->id, 'steam_id' => (string)$user->steam_id,
                    'state' => CashRaceKeyToken::STATE_HELD, 'issued_at' => $issuedAt,
                    'created_at' => $now, 'updated_at' => $now,
                ];
                $models[] = new CashRaceKeyToken($attributes);
                $rows[] = array_values($attributes);
            }
            Yii::$app->db->createCommand()->batchInsert(CashRaceKeyToken::tableName(), [
                'token_uuid', 'tournament_id', 'server_id', 'user_id', 'steam_id',
                'state', 'issued_at', 'created_at', 'updated_at',
            ], $rows)->execute();
            CashRaceScore::updateAllCounters(['keys_found' => count($models)], ['id' => $score->id]);
            CashRaceScore::updateAll(['last_found_at' => $issuedAt, 'updated_at' => $now], ['id' => $score->id]);
            $tx->commit();
            self::invalidateLeaderboard((int)$tournament->id);
            return $models;
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    public static function markLost(CashRaceTournament $config, User $user, array $uuids): int
    {
        $uuids = array_values(array_unique(array_filter($uuids, [self::class, 'validUuid'])));
        if (!$uuids) return 0;
        $tx = Yii::$app->db->beginTransaction();
        try {
            $count = CashRaceKeyToken::updateAll([
                'state' => CashRaceKeyToken::STATE_LOST,
                'consumed_at' => date('Y-m-d H:i:s'),
                'updated_at' => time(),
            ], [
                'token_uuid' => $uuids, 'tournament_id' => $config->tournament_id,
                'user_id' => $user->id, 'state' => CashRaceKeyToken::STATE_HELD,
            ]);
            if ($count > 0) {
                $score = self::score($config->tournament, $user);
                CashRaceScore::updateAllCounters(['keys_lost' => $count], ['id' => $score->id]);
            }
            $tx->commit();
            if ($count > 0) self::invalidateLeaderboard((int)$config->tournament_id);
            return $count;
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    public static function openTerminal(CashRaceTournament $config, Servers $server, array $data): CashRaceTerminalSession
    {
        $session = CashRaceTerminalSession::findOne(['session_uuid' => (string)($data['session_uuid'] ?? '')]);
        if ($session) return $session;
        if ($config->tournament->getPublicPhase() !== Tournament::PHASE_ACTIVE) throw new \DomainException('TOURNAMENT_NOT_ACTIVE');
        $session = new CashRaceTerminalSession([
            'tournament_id' => $config->tournament_id, 'server_id' => $server->id,
            'session_uuid' => (string)($data['session_uuid'] ?? ''),
            'monument_key' => mb_substr((string)($data['monument_key'] ?? ''), 0, 128),
            'monument_name' => mb_substr((string)($data['monument_name'] ?? ''), 0, 255),
            'position_json' => mb_substr((string)($data['position'] ?? ''), 0, 255),
            'spawned_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', time() + max(60, min(7200, (int)$config->terminal_active_seconds))),
            'status' => CashRaceTerminalSession::STATUS_ACTIVE,
        ]);
        if (!$session->save()) throw new \RuntimeException(json_encode($session->errors, JSON_UNESCAPED_UNICODE));
        return $session;
    }

    public static function closeTerminal(CashRaceTournament $config, string $uuid, bool $destroyed = false): void
    {
        $session = CashRaceTerminalSession::findOne(['tournament_id' => $config->tournament_id, 'session_uuid' => $uuid]);
        if (!$session || $session->status !== CashRaceTerminalSession::STATUS_ACTIVE) return;
        $session->status = $destroyed ? CashRaceTerminalSession::STATUS_DESTROYED : CashRaceTerminalSession::STATUS_EXPIRED;
        $session->closed_at = date('Y-m-d H:i:s');
        $session->save(false);
    }

    public static function deposit(CashRaceTournament $config, Servers $server, User $user, string $depositUuid, string $terminalUuid, array $tokenUuids): array
    {
        if (!self::validUuid($depositUuid)) throw new \DomainException('INVALID_DEPOSIT_UUID');
        $existing = CashRaceDeposit::findOne(['deposit_uuid' => $depositUuid]);
        if ($existing) {
            if ((int)$existing->tournament_id !== (int)$config->tournament_id || (int)$existing->server_id !== (int)$server->id || (int)$existing->user_id !== (int)$user->id) {
                throw new \DomainException('DEPOSIT_UUID_OWNERSHIP_MISMATCH');
            }
            return ['count' => (int)$existing->keys_count, 'total' => (int)self::score($config->tournament, $user)->keys_deposited, 'duplicate' => true];
        }
        $terminal = CashRaceTerminalSession::findOne([
            'tournament_id' => $config->tournament_id, 'server_id' => $server->id,
            'session_uuid' => $terminalUuid, 'status' => CashRaceTerminalSession::STATUS_ACTIVE,
        ]);
        if (!$terminal || strtotime((string)$terminal->expires_at) < time()) throw new \DomainException('TERMINAL_NOT_ACTIVE');
        $tokenUuids = array_values(array_unique(array_filter($tokenUuids, [self::class, 'validUuid'])));
        if (!$tokenUuids || count($tokenUuids) > 500) throw new \DomainException('INVALID_TOKEN_BATCH');

        $tx = Yii::$app->db->beginTransaction();
        try {
            $tokens = CashRaceKeyToken::find()->where([
                'token_uuid' => $tokenUuids, 'tournament_id' => $config->tournament_id,
                'server_id' => $server->id, 'user_id' => $user->id,
                'state' => CashRaceKeyToken::STATE_HELD,
            ])->all();
            if (count($tokens) !== count($tokenUuids)) throw new \DomainException('TOKEN_OWNERSHIP_MISMATCH');
            $ids = array_map(static fn(CashRaceKeyToken $t) => (int)$t->id, $tokens);
            $count = CashRaceKeyToken::updateAll([
                'state' => CashRaceKeyToken::STATE_DEPOSITED, 'terminal_session_id' => $terminal->id,
                'consumed_at' => date('Y-m-d H:i:s'), 'updated_at' => time(),
            ], ['id' => $ids, 'state' => CashRaceKeyToken::STATE_HELD]);
            if ($count !== count($ids)) throw new \RuntimeException('TOKEN_CONCURRENCY_CONFLICT');

            $deposit = new CashRaceDeposit([
                'deposit_uuid' => $depositUuid, 'tournament_id' => $config->tournament_id,
                'terminal_session_id' => $terminal->id, 'server_id' => $server->id,
                'user_id' => $user->id, 'steam_id' => (string)$user->steam_id, 'keys_count' => $count,
            ]);
            if (!$deposit->save()) throw new \RuntimeException(json_encode($deposit->errors, JSON_UNESCAPED_UNICODE));
            $score = self::score($config->tournament, $user);
            CashRaceScore::updateAllCounters(['keys_deposited' => $count], ['id' => $score->id]);
            CashRaceScore::updateAll(['last_deposited_at' => date('Y-m-d H:i:s'), 'updated_at' => time()], ['id' => $score->id]);
            $score->refresh();
            $tx->commit();
            self::invalidateLeaderboard((int)$config->tournament_id);
            return ['count' => $count, 'total' => (int)$score->keys_deposited, 'duplicate' => false];
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    public static function updateScoreByAdmin(CashRaceTournament $config, CashRaceScore $score, int $keysFound, int $keysLost, int $keysDeposited): CashRaceScore
    {
        if ($config->awards_issued_at) throw new \DomainException('TOURNAMENT_ALREADY_AWARDED');
        if ((int)$score->tournament_id !== (int)$config->tournament_id) throw new \DomainException('SCORE_TOURNAMENT_MISMATCH');
        foreach ([$keysFound, $keysLost, $keysDeposited] as $value) {
            if ($value < 0 || $value > 1000000) throw new \DomainException('INVALID_SCORE_VALUE');
        }
        $tx = Yii::$app->db->beginTransaction();
        try {
            $driver = Yii::$app->db->driverName;
            if ($driver === 'mysql' || $driver === 'pgsql') {
                Yii::$app->db->createCommand(
                    'SELECT id FROM ' . CashRaceScore::tableName() . ' WHERE id = :id FOR UPDATE',
                    [':id' => (int)$score->id]
                )->queryScalar();
            }

            $lockedScore = CashRaceScore::findOne([
                'id' => (int)$score->id,
                'tournament_id' => (int)$config->tournament_id,
            ]);
            if (!$lockedScore) throw new \DomainException('SCORE_NOT_FOUND');

            $heldKeys = (int)CashRaceKeyToken::find()->where([
                'tournament_id' => (int)$config->tournament_id,
                'user_id' => (int)$lockedScore->user_id,
                'state' => CashRaceKeyToken::STATE_HELD,
            ])->count();
            if ($keysLost + $keysDeposited + $heldKeys > $keysFound) {
                throw new \DomainException('SCORE_TOTAL_EXCEEDS_FOUND');
            }

            $lockedScore->keys_found = $keysFound;
            $lockedScore->keys_lost = $keysLost;
            $lockedScore->keys_deposited = $keysDeposited;
            $lockedScore->position = null;
            $lockedScore->last_found_at = $keysFound > 0
                ? ($lockedScore->last_found_at ?: date('Y-m-d H:i:s'))
                : null;
            $lockedScore->last_deposited_at = $keysDeposited > 0
                ? ($lockedScore->last_deposited_at ?: date('Y-m-d H:i:s'))
                : null;
            $lockedScore->updated_at = time();
            if (!$lockedScore->save()) throw new \RuntimeException(json_encode($lockedScore->errors, JSON_UNESCAPED_UNICODE));

            CashRaceScore::updateAll(['position' => null], ['tournament_id' => (int)$config->tournament_id]);
            $tx->commit();
            self::invalidateLeaderboard((int)$config->tournament_id);
            return $lockedScore;
        } catch (\Throwable $e) {
            if ($tx->isActive) $tx->rollBack();
            throw $e;
        }
    }

    public static function finalize(CashRaceTournament $config): array
    {
        if ($config->awards_issued_at) return self::leaderboard((int)$config->tournament_id, 3);
        $tx = Yii::$app->db->beginTransaction();
        try {
            $rows = CashRaceScore::find()->where(['tournament_id' => $config->tournament_id])
                ->andWhere(['>', 'keys_deposited', 0])
                ->orderBy(['keys_deposited' => SORT_DESC, 'last_deposited_at' => SORT_ASC, 'user_id' => SORT_ASC])->all();
            $medalCodes = ['cash-race-gold', 'cash-race-silver', 'cash-race-bronze'];
            foreach ($rows as $index => $row) {
                $row->position = $index + 1;
                $row->save(false);
                if ($index < 3) {
                    $medal = Medal::findOne(['code' => $medalCodes[$index]]);
                    if ($medal) UserMedal::award((int)$row->user_id, (int)$medal->id, UserMedal::SOURCE_CASH_RACE, (int)$config->tournament_id, 'Место ' . ($index + 1) . ' в турнире «Денежная гонка»');
                }
            }
            $config->finished_at = $config->finished_at ?: date('Y-m-d H:i:s');
            $config->awards_issued_at = date('Y-m-d H:i:s');
            $config->save(false);
            $tx->commit();
            self::invalidateLeaderboard((int)$config->tournament_id);
            return self::leaderboard((int)$config->tournament_id, 3);
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    public static function leaderboard(int $tournamentId, int $limit = 20): array
    {
        $loader = static function () use ($tournamentId): array {
            $rows = CashRaceScore::find()->where(['tournament_id' => $tournamentId])
                ->with('user')->orderBy(['keys_deposited' => SORT_DESC, 'last_deposited_at' => SORT_ASC, 'user_id' => SORT_ASC])
                ->limit(100)->all();
            $items = [];
            foreach ($rows as $index => $row) {
                $items[] = [
                    'position' => $row->position ?: $index + 1,
                    'steam_id' => (string)$row->steam_id,
                    'username' => $row->user ? (string)$row->user->username : 'Игрок',
                    'avatar' => $row->user ? (string)$row->user->avatar : '',
                    'keys_found' => (int)$row->keys_found,
                    'keys_lost' => (int)$row->keys_lost,
                    'keys_deposited' => (int)$row->keys_deposited,
                ];
            }
            return $items;
        };
        $items = Yii::$app->has('cache')
            ? Yii::$app->cache->getOrSet(self::leaderboardCacheKey($tournamentId), $loader, self::LEADERBOARD_CACHE_TTL)
            : $loader();
        return array_slice($items, 0, max(1, min(100, $limit)));
    }

    private static function invalidateLeaderboard(int $tournamentId): void
    {
        if (Yii::$app->has('cache')) Yii::$app->cache->delete(self::leaderboardCacheKey($tournamentId));
    }

    private static function leaderboardCacheKey(int $tournamentId): array
    {
        return [self::class, 'leaderboard', $tournamentId];
    }

    public static function validUuid($value): bool
    {
        return is_string($value) && (bool)preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value);
    }
}
