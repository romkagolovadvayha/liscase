<?php

namespace console\controllers;

use common\components\clan\ClanActiveMembersCache;
use common\components\queue\clan\UpdateClanStatisticsJob;
use common\models\clan\Clan;
use common\models\clan\ClanMember;
use common\models\clan\ClanPermission;
use common\models\rcon\RconTasks;
use common\models\servers\Servers;
use common\models\user\User;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Query;

/**
 * Кланы: кэш активных участников и полный пересчёт статистики/рейтингов (cron).
 */
class ClanController extends Controller
{
    /**
     * Обновить кэш steam_id участников кланов (онлайн за последние N дней) по всем серверам.
     * Рекомендуется cron: каждые 5 минут.
     *
     * @param int|null $serverId только один сервер (опционально)
     */
    public function actionRefreshActiveMembersCache($serverId = null)
    {
        if ($serverId !== null && $serverId !== '') {
            ClanActiveMembersCache::refreshServer((int)$serverId);
            $this->stdout("Cache refreshed for server {$serverId}\n");
            return ExitCode::OK;
        }

        ClanActiveMembersCache::refreshAll();
        $this->stdout("Cache refreshed for all servers\n");

        return ExitCode::OK;
    }

    /**
     * Полный пересчёт статистики кланов на сервере(ах) и рейтингов (через очередь {@see UpdateClanStatisticsJob}).
     * Основной путь обновления кланов после записи в `statistics` — этот action по cron (см. `console.php` → clanUpdateStatistics).
     *
     * @param int|null $serverId только один сервер (опционально)
     */
    public function actionUpdateStatistics($serverId = null)
    {
        $query = Servers::find()
            ->where(['status' => Servers::STATUS_ACTIVE]);
        if (Servers::hasClansEnabledColumn()) {
            $query->andWhere(['clans_enabled' => 1]);
        }
        if ($serverId !== null && $serverId !== '') {
            $query->andWhere(['id' => (int)$serverId]);
        }

        $servers = $query->all();
        foreach ($servers as $server) {
            $wipe = $server->currentWipe();
            try {
                Yii::$app->queueParams->push(new UpdateClanStatisticsJob([
                    'serverId' => $server->id,
                    'wipe' => $wipe,
                ]));
                $this->stdout("Queued UpdateClanStatisticsJob for server {$server->id} ({$server->tag})\n");
            } catch (\Throwable $e) {
                Yii::error('ClanController::actionUpdateStatistics: ' . $e->getMessage(), 'clan');
                $this->stderr("Error queueing server {$server->id}: {$e->getMessage()}\n");
            }
        }

        return ExitCode::OK;
    }

    /**
     * Окно «онлайн на сервере» для рассылки уведомления (минуты). Совпадает с {@see User::getStatus()}.
     */
    private const ONLINE_WITHIN_MINUTES = 10;

    /** Цвет «есть доступ» в Rust-разметке `<color=#…>` для чат-сообщения плагина Helper. */
    private const COLOR_ACCESS_OK = '#6CD96C';

    /** Цвет «нет доступа». */
    private const COLOR_ACCESS_DENIED = '#FF5C5C';

    /**
     * Отправить онлайн-участникам кланов чат-сообщение со списком их прав авторизации
     * (кодлоки/ПВО/шкафы/турели), если хотя бы одно право отсутствует.
     *
     * Запуск вручную: `yii clan/notify-missing-access [serverId] [dryRun]`.
     * Лидеры пропускаются (у них все права автоматически).
     *
     * @param int|null $serverId фильтр по серверу (опционально)
     * @param int      $dryRun   1 — только вывести план, без RCON-запросов
     */
    public function actionNotifyMissingAccess($serverId = null, $dryRun = 0)
    {
        $dryRun = filter_var($dryRun, FILTER_VALIDATE_BOOLEAN);

        $serversQuery = Servers::find()
            ->where(['status' => Servers::STATUS_ACTIVE]);
        if (Servers::hasClansEnabledColumn()) {
            $serversQuery->andWhere(['clans_enabled' => 1]);
        }
        if ($serverId !== null && $serverId !== '') {
            $serversQuery->andWhere(['id' => (int)$serverId]);
        }

        /** @var Servers[] $servers */
        $servers = $serversQuery->all();
        if ($servers === []) {
            $this->stdout("No active servers with enabled clans found\n");
            return ExitCode::OK;
        }

        $permRows = ClanPermission::find()
            ->select(['id', 'key'])
            ->where(['key' => ClanPermission::AUTH_ENTITY_KEYS])
            ->asArray()
            ->all();
        if ($permRows === []) {
            $this->stderr("clan_permissions has no auth_* rows\n");
            return ExitCode::DATAERR;
        }
        $permIdByKey = [];
        foreach ($permRows as $row) {
            $permIdByKey[(string)$row['key']] = (int)$row['id'];
        }

        $onlineSince = date('Y-m-d H:i:s', time() - self::ONLINE_WITHIN_MINUTES * 60);

        $totalSent = 0;
        foreach ($servers as $server) {
            $sent = $this->notifyMissingAccessForServer(
                $server,
                $permIdByKey,
                $onlineSince,
                $dryRun
            );
            $totalSent += $sent;
            $this->stdout("Server {$server->id} ({$server->tag}): notified {$sent}\n");
        }

        $this->stdout("Done. Total notified: {$totalSent}" . ($dryRun ? ' (dry-run)' : '') . "\n");
        return ExitCode::OK;
    }

    /**
     * Один сервер: найти онлайн-участников клана без полного набора auth_* и разослать им сообщение.
     *
     * @param array<string, int> $permIdByKey id разрешения по ключу (auth_lock|auth_turret|auth_cupboard|auth_sam)
     * @return int сколько сообщений отправлено
     */
    private function notifyMissingAccessForServer(
        Servers $server,
        array $permIdByKey,
        string $onlineSince,
        bool $dryRun
    ): int {
        if (!$server->isClansSystemEnabled()) {
            return 0;
        }

        /** @var ClanMember[] $members */
        $members = ClanMember::find()
            ->alias('m')
            ->innerJoin(['c' => Clan::tableName()], '[[c]].[[id]] = [[m]].[[clan_id]]')
            ->innerJoin(['u' => User::tableName()], '[[u]].[[id]] = [[m]].[[user_id]]')
            ->with(['user', 'clan'])
            ->where(['c.server_id' => $server->id])
            ->andWhere(['IS', 'm.leave_date', null])
            ->andWhere(['<>', 'm.role', ClanMember::ROLE_LEADER])
            ->andWhere(['u.status' => User::STATUS_ACTIVE])
            ->andWhere(['>=', 'u.last_visit_server_at', $onlineSince])
            ->andWhere(['not', ['u.steam_id' => null]])
            ->andWhere(['<>', 'u.steam_id', ''])
            ->all();

        if ($members === []) {
            return 0;
        }

        $memberIds = array_map(static function (ClanMember $m): int {
            return (int)$m->id;
        }, $members);

        $permIds = array_values($permIdByKey);
        $permIdToKey = array_flip($permIdByKey);

        $rows = (new Query())
            ->from('clan_member_permissions')
            ->where(['clan_member_id' => $memberIds])
            ->andWhere(['permission_id' => $permIds])
            ->select(['clan_member_id', 'permission_id'])
            ->all();

        $ownPermsByMember = [];
        foreach ($rows as $row) {
            $mid = (int)$row['clan_member_id'];
            $key = $permIdToKey[(int)$row['permission_id']] ?? null;
            if ($key !== null) {
                $ownPermsByMember[$mid][$key] = true;
            }
        }

        $sentCount = 0;
        foreach ($members as $member) {
            $own = $ownPermsByMember[(int)$member->id] ?? [];

            $flags = [
                'auth_lock'     => !empty($own['auth_lock']),
                'auth_sam'      => !empty($own['auth_sam']),
                'auth_cupboard' => !empty($own['auth_cupboard']),
                'auth_turret'   => !empty($own['auth_turret']),
            ];

            if (!in_array(false, $flags, true)) {
                continue;
            }

            if ($this->sendMissingAccessMessage($server, $member, $flags, $dryRun)) {
                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * Сформировать RU/EN сообщение со списком прав и отправить его через RCON плагину Helper
     * (`helper message "<ru>" "<en>" "" "<steam_id>"`). Лог пишется в `rcon_tasks`.
     *
     * @param array{auth_lock: bool, auth_sam: bool, auth_cupboard: bool, auth_turret: bool} $flags
     */
    private function sendMissingAccessMessage(
        Servers $server,
        ClanMember $member,
        array $flags,
        bool $dryRun
    ): bool {
        $user = $member->user;
        $clan = $member->clan;
        if (!$user || !$clan) {
            return false;
        }
        $steamId = (string)$user->steam_id;
        if ($steamId === '' || $steamId === '0') {
            return false;
        }

        $clanTag = (string)$clan->tag;
        $clanColor = in_array($clan->color_tag, Clan::TAG_COLOR_PRESETS, true)
            ? (string)$clan->color_tag
            : Clan::DEFAULT_TAG_COLOR;

        $okColor = self::COLOR_ACCESS_OK;
        $badColor = self::COLOR_ACCESS_DENIED;

        $okRu  = "<color={$okColor}>Есть доступ</color>";
        $badRu = "<color={$badColor}>Нет доступа</color>";
        $okEn  = "<color={$okColor}>granted</color>";
        $badEn = "<color={$badColor}>denied</color>";

        $tag = "<color={$clanColor}>[{$clanTag}]</color>";

        $messageRu  = "Вы состоите в клане {$tag}\n\n";
        $messageRu .= "Ваши доступы:\n";
        $messageRu .= "Кодлоки: " . ($flags['auth_lock']     ? $okRu : $badRu) . "\n";
        $messageRu .= "ПВО: "      . ($flags['auth_sam']      ? $okRu : $badRu) . "\n";
        $messageRu .= "Шкафы: "    . ($flags['auth_cupboard'] ? $okRu : $badRu) . "\n";
        $messageRu .= "Турели: "   . ($flags['auth_turret']   ? $okRu : $badRu) . "\n\n";
        $messageRu .= "Попросите лидера клана выдать вам доступ на сайте, иначе вы не будете авторизованы.";

        $messageEn  = "You are in clan {$tag}\n\n";
        $messageEn .= "Your access:\n";
        $messageEn .= "Codelocks: " . ($flags['auth_lock']     ? $okEn : $badEn) . "\n";
        $messageEn .= "SAM sites: " . ($flags['auth_sam']      ? $okEn : $badEn) . "\n";
        $messageEn .= "Cupboards: " . ($flags['auth_cupboard'] ? $okEn : $badEn) . "\n";
        $messageEn .= "Turrets: "   . ($flags['auth_turret']   ? $okEn : $badEn) . "\n\n";
        $messageEn .= "Ask your clan leader to grant you access on the site, otherwise you will not be authorized.";

        $command = "helper message \"{$messageRu}\" \"{$messageEn}\" \"\" \"{$steamId}\"";

        if ($dryRun) {
            $this->stdout("[DRY] server={$server->tag} steam={$steamId} clan=[{$clanTag}] missing="
                . implode(',', array_keys(array_filter($flags, static function ($v) { return !$v; })))
                . "\n");
            return true;
        }

        try {
            $response = (Yii::$app->curl)
                ->setHeaders(['Content-Type' => 'application/json'])
                ->setRawPostData(json_encode(['server' => $server->tag, 'command' => $command]))
                ->post(Yii::$app->settings->get('site_rconUrl') . '/send');
        } catch (\Throwable $e) {
            Yii::error('ClanController::sendMissingAccessMessage: ' . $e->getMessage(), 'clan');
            $this->stderr("Send error for steam={$steamId}: {$e->getMessage()}\n");
            return false;
        }

        $rconTask = new RconTasks();
        $rconTask->status = RconTasks::STATUS_DONE;
        $rconTask->command = $command;
        $rconTask->result = is_scalar($response) ? (string)$response : json_encode($response);
        $rconTask->server_tag = $server->tag;
        $rconTask->created_at = date('Y-m-d H:i:s');
        $rconTask->save();

        return true;
    }
}
