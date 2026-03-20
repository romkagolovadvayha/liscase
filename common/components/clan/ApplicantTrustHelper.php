<?php

namespace common\components\clan;

use common\models\bans\Bans;
use common\models\clan\Clan;
use common\models\user\User;
use Yii;

/**
 * Краткая оценка надёжности заявителя в клан (для лидера/офицера).
 * Основана на активных банах на проекте (таблица {{%bans}}).
 */
final class ApplicantTrustHelper
{
    /**
     * @return array{
     *   trust_score: int,
     *   level: string,
     *   recommendation: string,
     *   active_ban_count: int,
     *   ban_on_clan_server: bool,
     *   bans: list<array{server_tag: ?string, server_name: string, reason: string, banned_at: ?string}>
     * }
     */
    public static function summarize(User $user, Clan $clan): array
    {
        $steamId = (string)$user->steam_id;
        $clanServerId = (int)$clan->server_id;

        $banRows = Bans::find()
            ->alias('b')
            ->with(['server'])
            ->where(['b.steam_id' => $steamId])
            ->andWhere([
                'OR',
                ['>=', 'b.unbanned_at', date('Y-m-d H:i:s')],
                ['IS', 'b.unbanned_at', null],
            ])
            ->orderBy(['b.banned_at' => SORT_DESC])
            ->limit(30)
            ->all();

        $bans = [];
        $banOnClanServer = false;
        foreach ($banRows as $ban) {
            $sid = $ban->server_id !== null ? (int)$ban->server_id : null;
            if ($sid === $clanServerId) {
                $banOnClanServer = true;
            }
            $server = $ban->server;
            $bans[] = [
                'server_tag' => $server ? $server->tag : null,
                'server_name' => $server
                    ? (string)($server->monitoring_name ?: $server->tag)
                    : Yii::t('common', 'Все сервера'),
                'reason' => (string)($ban->reason ?? ''),
                'banned_at' => $ban->banned_at,
            ];
        }

        $n = count($banRows);
        $score = 100;
        $score -= min($n * 18, 72);
        if ($banOnClanServer) {
            $score -= 15;
        }
        if ($n >= 3) {
            $score -= 10;
        }
        if ($n >= 5) {
            $score -= 8;
        }
        $score = max(0, min(100, $score));

        if ($score >= 72) {
            $level = 'good';
            $recommendation = 'accept';
        } elseif ($score >= 42) {
            $level = 'caution';
            $recommendation = 'review_carefully';
        } else {
            $level = 'high_risk';
            $recommendation = 'not_recommended';
        }

        return [
            'trust_score' => $score,
            'level' => $level,
            'recommendation' => $recommendation,
            'active_ban_count' => $n,
            'ban_on_clan_server' => $banOnClanServer,
            'bans' => $bans,
        ];
    }
}
