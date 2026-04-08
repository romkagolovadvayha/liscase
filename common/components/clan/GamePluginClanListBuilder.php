<?php

namespace common\components\clan;

use common\models\clan\Clan;
use common\models\clan\ClanMember;
use common\models\servers\Servers;
use yii\db\Query;

/**
 * Плоский JSON списка кланов для плагина Oxide ClanManager (GET …/clans/list?ip=&port=).
 */
final class GamePluginClanListBuilder
{
    /**
     * Флаги авторизации в объектах для плагина (совпадают с правами auth_*).
     *
     * @param string[] $permissionKeys
     * @return array{lock: bool, turrets: bool, defense: bool, cupboard_auth: bool}
     */
    public static function memberAuthFlags(ClanMember $member, array $permissionKeys): array
    {
        if ($member->isLeader()) {
            return [
                'lock' => true,
                'turrets' => true,
                'defense' => true,
                'cupboard_auth' => true,
            ];
        }

        $set = array_fill_keys($permissionKeys, true);

        return [
            'lock' => !empty($set['auth_lock']),
            'turrets' => !empty($set['auth_turret']),
            'defense' => !empty($set['auth_sam']),
            'cupboard_auth' => !empty($set['auth_cupboard']),
        ];
    }

    /**
     * @return list<array{tag: string, color_tag: string, update_at: string, users: list<array<string, mixed>>}>
     */
    public static function buildForIpPort(string $ip, int $port): array
    {
        $server = Servers::find()
            ->where(['port' => $port])
            ->andWhere(['or', ['ip' => $ip], ['text_ip' => $ip]])
            ->orderBy(['status' => SORT_DESC, 'id' => SORT_ASC])
            ->one();

        if (!$server || !$server->isClansSystemEnabled()) {
            return [];
        }

        $clans = Clan::find()
            ->where(['server_id' => $server->id])
            ->with(['activeMembers.user'])
            ->all();

        $out = [];
        foreach ($clans as $clan) {
            $members = $clan->activeMembers;
            $memberIds = array_map(static function (ClanMember $m) {
                return (int) $m->id;
            }, $members);

            $permByMemberId = [];
            if ($memberIds !== []) {
                $rows = (new Query())
                    ->from(['cmp' => 'clan_member_permissions'])
                    ->innerJoin(['cp' => 'clan_permissions'], '[[cp]].[[id]] = [[cmp]].[[permission_id]]')
                    ->where(['cmp.clan_member_id' => $memberIds])
                    ->select(['cmp.clan_member_id', 'cp.key'])
                    ->all();
                foreach ($rows as $row) {
                    $mid = (int) $row['clan_member_id'];
                    if (!isset($permByMemberId[$mid])) {
                        $permByMemberId[$mid] = [];
                    }
                    $permByMemberId[$mid][] = (string) $row['key'];
                }
            }

            $users = [];
            foreach ($members as $member) {
                $user = $member->user;
                if (!$user) {
                    continue;
                }
                $steamId = (string) $user->steam_id;
                if ($steamId === '' || $steamId === '0') {
                    continue;
                }

                $flags = self::memberAuthFlags($member, $permByMemberId[(int) $member->id] ?? []);
                $users[] = array_merge([
                    'steam_id' => $steamId,
                ], $flags);
            }

            $colorTag = $clan->color_tag;
            if (!in_array($colorTag, Clan::TAG_COLOR_PRESETS, true)) {
                $colorTag = Clan::DEFAULT_TAG_COLOR;
            }

            $out[] = [
                'tag' => $clan->tag,
                'color_tag' => $colorTag,
                'update_at' => gmdate('c', (int) $clan->updated_at),
                'users' => $users,
            ];
        }

        return $out;
    }
}
