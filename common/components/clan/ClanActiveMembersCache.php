<?php

namespace common\components\clan;

use common\models\clan\Clan;
use common\models\clan\ClanMember;
use common\models\servers\Servers;
use common\models\user\User;
use Yii;

/**
 * Кэш steam_id участников кланов на сервере: активное членство + был онлайн не позже N дней.
 * Обновляется консолью (рекомендуется раз в 5 минут).
 */
class ClanActiveMembersCache
{
    /** @var int TTL кэша (секунды), обновление снаружи по cron чаще */
    public const CACHE_DURATION = 3600;

    /** @var int Окно «был онлайн» (дней) */
    public const ONLINE_WITHIN_DAYS = 3;

    public static function cacheKey(int $serverId): string
    {
        return 'clan_active_member_steam_ids_' . $serverId;
    }

    /**
     * Набор steam_id (ключи — строки) для быстрой проверки isset.
     *
     * @return array<string, true>
     */
    public static function getSteamIdSet(int $serverId): array
    {
        $data = Yii::$app->cache->get(self::cacheKey($serverId));
        if (!is_array($data)) {
            return [];
        }

        return $data;
    }

    /**
     * Пересчитать кэш для одного сервера.
     */
    public static function refreshServer(int $serverId): void
    {
        $since = date('Y-m-d H:i:s', time() - self::ONLINE_WITHIN_DAYS * 86400);

        $steamIds = ClanMember::find()
            ->alias('m')
            ->select(['u.steam_id'])
            ->innerJoin(['c' => Clan::tableName()], '[[c]].[[id]] = [[m]].[[clan_id]]')
            ->innerJoin(['u' => User::tableName()], '[[u]].[[id]] = [[m]].[[user_id]]')
            ->where(['c.server_id' => $serverId])
            ->andWhere(['IS', 'm.leave_date', null])
            ->andWhere(['>=', 'u.last_visit_server_at', $since])
            ->andWhere(['not', ['u.steam_id' => null]])
            ->andWhere(['<>', 'u.steam_id', ''])
            ->column();

        $set = [];
        foreach ($steamIds as $sid) {
            $set[(string)$sid] = true;
        }

        Yii::$app->cache->set(self::cacheKey($serverId), $set, self::CACHE_DURATION);
    }

    /**
     * Пересчитать кэш для всех серверов.
     */
    public static function refreshAll(): void
    {
        $ids = Servers::find()->select(['id'])->column();
        foreach ($ids as $id) {
            self::refreshServer((int)$id);
        }
    }
}
