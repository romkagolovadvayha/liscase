<?php

namespace common\models\clan;

use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\user\UserTop;
use Yii;
use yii\db\ActiveRecord;

/**
 * Baseline значений statistics на момент вступления (дельта = текущее − baseline).
 *
 * @property int $id
 * @property int $clan_member_id
 * @property int $server_id
 * @property string $wipe
 * @property string $stat_key
 * @property int $value
 * @property int $created_at
 */
class ClanMemberStatsBaseline extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'clan_member_stats_baseline';
    }

    /**
     * Все ключи метрик, которые пишутся в clan_member_statistics_values (дельта за вайп).
     */
    public static function getTrackedStatKeys(): array
    {
        $keys = UserTop::getRaitingKeys();
        $keys = array_merge($keys, ['helicopters', 'bradleys', 'research_table_looted', 'excavator_mined']);

        return array_values(array_unique($keys));
    }

    /**
     * Текущее значение из statistics (одна строка на ключ за вайп).
     */
    public static function getCurrentStatisticsValue(string $steamId, string $serverTag, string $wipe, string $statKey): int
    {
        $map = self::getCurrentStatisticsValuesMap($steamId, $serverTag, $wipe, [$statKey]);

        return (int)($map[$statKey] ?? 0);
    }

    /**
     * Все нужные ключи за один запрос (вместо N отдельных SELECT на каждый stat_key).
     *
     * @param string[] $statKeys
     * @return array<string, int> key => value
     */
    public static function getCurrentStatisticsValuesMap(string $steamId, string $serverTag, string $wipe, array $statKeys): array
    {
        if ($statKeys === []) {
            return [];
        }
        $rows = Statistics::find()
            ->select(['key', 'value'])
            ->where([
                'steam_id' => $steamId,
                'server_tag' => $serverTag,
                'wipe' => $wipe,
            ])
            ->andWhere(['key' => $statKeys])
            ->asArray()
            ->all();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['key']] = (int)$row['value'];
        }

        return $map;
    }

    /**
     * Снимок baseline при вступлении (или ленивая инициализация, если строк ещё нет).
     */
    public static function captureBaseline(ClanMember $member, int $serverId, string $wipe): void
    {
        $server = Servers::findOne($serverId);
        $user = $member->user;
        if (!$server || !$user || !$user->steam_id) {
            return;
        }

        $now = time();
        $keys = self::getTrackedStatKeys();
        $currentMap = self::getCurrentStatisticsValuesMap($user->steam_id, $server->tag, $wipe, $keys);

        foreach ($keys as $statKey) {
            $current = (int)($currentMap[$statKey] ?? 0);

            $model = static::find()
                ->where([
                    'clan_member_id' => $member->id,
                    'server_id' => $serverId,
                    'wipe' => $wipe,
                    'stat_key' => $statKey,
                ])
                ->one();

            if (!$model) {
                $model = new static();
                $model->clan_member_id = $member->id;
                $model->server_id = $serverId;
                $model->wipe = $wipe;
                $model->stat_key = $statKey;
                $model->created_at = $now;
            }

            $model->value = $current;
            $model->save(false);
        }
    }

    /**
     * @return array<string, int> stat_key => baseline value
     */
    public static function getBaselineMap(int $clanMemberId, int $serverId, string $wipe): array
    {
        $rows = static::find()
            ->select(['stat_key', 'value'])
            ->where([
                'clan_member_id' => $clanMemberId,
                'server_id' => $serverId,
                'wipe' => $wipe,
            ])
            ->asArray()
            ->all();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['stat_key']] = (int)$row['value'];
        }

        return $map;
    }

    /**
     * Если baseline ещё не создан (старые данные / новый вайп) — зафиксировать текущие значения как baseline.
     */
    public static function ensureBaselineExists(ClanMember $member, int $serverId, string $wipe): void
    {
        $exists = static::find()
            ->where([
                'clan_member_id' => $member->id,
                'server_id' => $serverId,
                'wipe' => $wipe,
            ])
            ->exists();

        if (!$exists) {
            self::captureBaseline($member, $serverId, $wipe);
        }
    }

    public static function deleteAllForMember(int $clanMemberId): int
    {
        return static::deleteAll(['clan_member_id' => $clanMemberId]);
    }
}
