<?php

namespace common\components\clan;

use common\components\rustcheck\RustCheck;
use common\models\bans\Bans;
use common\models\clan\Clan;
use common\models\user\User;
use Yii;

/**
 * Краткая оценка надёжности заявителя в клан (для лидера/офицера).
 * Учитывает активные баны на проекте ({{%bans}}) и данные RustCheatCheck (rustcheatcheck.ru), как на сайте/боте.
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
     *   bans: list<array{server_tag: ?string, server_name: string, reason: string, banned_at: ?string}>,
     *   rustcheatcheck: array<string, mixed>
     * }
     */
    public static function summarize(User $user, Clan $clan): array
    {
        $steamId = RustCheck::normalizePlayerSteamId($user->steam_id);
        if ($steamId === null || $steamId === '') {
            $steamId = trim((string) $user->steam_id);
        }
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

        $rustcheatcheck = self::buildRustCheatCheckBlock($steamId, $score);
        $score = (int)$rustcheatcheck['adjusted_score'];
        unset($rustcheatcheck['adjusted_score']);
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
            'rustcheatcheck' => $rustcheatcheck,
        ];
    }

    /**
     * Данные API rustcheatcheck.ru (getInfo) + корректировка trust_score.
     *
     * @return array{available: bool, adjusted_score: int, status?: string, checks_count?: int, active_ban_count?: int, total_ban_count?: int, last_nick?: ?string, bans?: list<array{server_name: string, reason: string, ban_at: ?int, unban_at: ?int, active: bool}>}
     */
    private static function buildRustCheatCheckBlock(string $steamId, int $scoreSoFar): array
    {
        $base = [
            'available' => false,
            'adjusted_score' => $scoreSoFar,
        ];

        if ($steamId === '' || !Yii::$app->has('rustCheck')) {
            return $base;
        }

        $apiKey = Yii::$app->settings->get('banSystem_rustcheatcheck');
        if (empty($apiKey)) {
            return $base;
        }

        try {
            $raw = Yii::$app->rustCheck->getInfo($steamId);
        } catch (\Throwable $e) {
            Yii::warning('ApplicantTrustHelper: RustCheatCheck getInfo failed: ' . $e->getMessage(), __METHOD__);
            return $base;
        }

        if (!is_array($raw) || $raw === []) {
            return $base;
        }

        $payload = self::flattenRustCheckPayload($raw);
        $statusRaw = isset($payload['status']) ? (string) $payload['status'] : '';

        if (!self::rustCheckPayloadIsUsable($payload)) {
            $apiMessage = $payload['message'] ?? $payload['error'] ?? $payload['msg'] ?? null;
            if (!is_string($apiMessage) || $apiMessage === '') {
                $apiMessage = null;
            }

            return [
                'available' => true,
                'status' => $statusRaw !== '' ? $statusRaw : 'error',
                'api_message' => $apiMessage,
                'adjusted_score' => $scoreSoFar,
            ];
        }

        $checksCount = isset($payload['rcc_checks']) ? (int) $payload['rcc_checks'] : 0;
        if ($checksCount <= 0 && !empty($payload['last_check']) && is_array($payload['last_check'])) {
            $checksCount = count($payload['last_check']);
        }
        $lastNick = isset($payload['last_nick']) ? (string) $payload['last_nick'] : null;
        if ($lastNick === '') {
            $lastNick = null;
        }

        $bansOut = [];
        $activeRcc = 0;
        $rccBans = !empty($payload['bans']) && is_array($payload['bans']) ? $payload['bans'] : [];
        foreach ($rccBans as $ban) {
            if (!is_array($ban)) {
                continue;
            }
            $banAt = !empty($ban['banDate']) ? (int)$ban['banDate'] : null;
            $unbanAt = isset($ban['unbanDate']) ? (int)$ban['unbanDate'] : 0;
            $active = $unbanAt === 0 || $unbanAt > time();
            if ($active) {
                $activeRcc++;
            }
            $bansOut[] = [
                'server_name' => isset($ban['serverName']) ? (string)$ban['serverName'] : '',
                'reason' => isset($ban['reason']) ? (string)$ban['reason'] : '',
                'ban_at' => $banAt ?: null,
                'unban_at' => $unbanAt > 0 ? $unbanAt : null,
                'active' => $active,
            ];
        }

        $totalRccBans = count($bansOut);
        $adj = $scoreSoFar;
        // Активные баны в RCC — сильный негативный сигнал
        $adj -= min($activeRcc * 16, 48);
        // История разбаненных записей в RCC — умеренно
        $inactive = max(0, $totalRccBans - $activeRcc);
        $adj -= min($inactive * 4, 16);
        // Очень частые проверки — дополнительная осторожность
        if ($checksCount > 20) {
            $adj -= 6;
        } elseif ($checksCount > 12) {
            $adj -= 3;
        }

        return [
            'available' => true,
            'status' => 'success',
            'checks_count' => $checksCount,
            'active_ban_count' => $activeRcc,
            'total_ban_count' => $totalRccBans,
            'last_nick' => $lastNick,
            'bans' => $bansOut,
            'adjusted_score' => $adj,
        ];
    }

    /**
     * Некоторые ответы RCC кладут поля внутрь data/response/result.
     */
    private static function flattenRustCheckPayload(array $raw): array
    {
        foreach (['data', 'response', 'result'] as $wrap) {
            if (empty($raw[$wrap]) || !is_array($raw[$wrap])) {
                continue;
            }
            $inner = $raw[$wrap];
            if (
                isset($inner['last_check'])
                || isset($inner['bans'])
                || isset($inner['rcc_checks'])
                || isset($inner['last_nick'])
            ) {
                return array_merge($raw, $inner);
            }
        }

        return $raw;
    }

    /**
     * Учитываем ответы без поля status (как в игровом плагине) и явные ошибки.
     */
    private static function rustCheckPayloadIsUsable(array $payload): bool
    {
        $status = strtolower((string) ($payload['status'] ?? ''));
        if (in_array($status, ['error', 'fail', 'failed'], true)) {
            return false;
        }
        if ($status === 'success') {
            return true;
        }

        return array_key_exists('rcc_checks', $payload)
            || array_key_exists('last_check', $payload)
            || array_key_exists('bans', $payload)
            || array_key_exists('last_nick', $payload);
    }
}
