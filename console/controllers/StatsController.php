<?php

namespace console\controllers;

use common\components\web\User;
use common\models\servers\Servers;
use common\models\statistics\Reports;
use common\models\statistics\Statistics;
use common\models\statistics\Kills;
use common\models\statistics\Teams;
use common\models\user\UserTop;
use common\helpers\StatsCacheHelper;
use yii\base\BaseObject;
use yii\console\Controller;
use yii\db\Query;
use Yii;

class StatsController extends Controller
{
    /** @deprecated используйте {@see StatsCacheHelper::CACHE_KEY_ACTIVE_PLAYERS_GLOBAL} */
    public const CACHE_KEY_ACTIVE_PLAYERS_GLOBAL = StatsCacheHelper::CACHE_KEY_ACTIVE_PLAYERS_GLOBAL;

    /** Размер батча steam_id для агрегирующего запроса (меньше пик памяти в PHP и в драйвере). */
    private const ACTIVE_PLAYERS_STEAM_BATCH = 50;

    /**
     * Собирает пользователей с суммарным playtime ≥ 20 суток (по всем statistics) и кладёт в кэш один массив.
     * Очки reider, farmer, fermer, hunter, fishing — по весам {@see UserTop::getRaiting()} (как «Лучший рейдер» и т.д.).
     *
     * Два этапа: (1) только steam_id с подходящим суммарным playtime; (2) по 50 id — отдельный GROUP BY
     * по всем ключам statistics, без одного гигантского JOIN (иначе queryAll съедает память, см. mysqli/буфер).
     *
     * По умолчанию TTL = {@see StatsCacheHelper::ACTIVE_PLAYERS_GLOBAL_CACHE_TTL} (48 ч).
     * Дополнительно пишет per-server ключи {@see StatsCacheHelper::cacheKeyActivePlayersServer} для серверов
     * со статусом выключен / включён / скоро (0, 1, 2).
     * Пример: `php yii stats/active-players-cache` или `php yii stats/active-players-cache 7200` (свой TTL, сек).
     * Индекс под шаг 1: покрывающий (key, steam_id, value) — m260402_100000_statistics_covering_index_playtime_agg
     * (двухколоночный m260401 часто хуже: без value идут lookups в таблицу).
     *
     * @param int|null $ttl время жизни кэша в секундах; null/пусто — 48 ч
     */
    public function actionActivePlayersCache($ttl = null): void
    {
        ini_set('memory_limit', '512M');
        $minMinutes = StatsCacheHelper::ACTIVE_PLAYERS_MIN_PLAYTIME_MINUTES;
        if ($ttl === null || $ttl === '') {
            $ttl = StatsCacheHelper::ACTIVE_PLAYERS_GLOBAL_CACHE_TTL;
        }
        $ttl = (int) $ttl;
        $batch = self::ACTIVE_PLAYERS_STEAM_BATCH;
        $t0 = microtime(true);
        $log = static function (string $msg) use ($t0): string {
            return sprintf('[%.2fs] active-players-cache: %s', microtime(true) - $t0, $msg);
        };

        $this->stdout($log("старт, порог playtime >= {$minMinutes} мин, TTL кэша {$ttl} с, батч steam_id = {$batch}\n"));
        Yii::info("active-players-cache старт: min_playtime={$minMinutes}, ttl={$ttl}, batch={$batch}", __METHOD__);

        $this->stdout($log("глобальный кэш: шаг 1 — steam_id с SUM(playtime) >= {$minMinutes}…\n"));
        $chunkLogger = function (string $msg) use ($log): void {
            $this->stdout($log($msg . "\n"));
        };
        $list = $this->buildActivePlayersList(null, $minMinutes, $batch, $chunkLogger);
        $final = count($list);
        $this->stdout($log("глобальный кэш: в выборке {$final} игроков\n"));
        Yii::$app->cache->set(StatsCacheHelper::CACHE_KEY_ACTIVE_PLAYERS_GLOBAL, $list, $ttl);
        $this->stdout($log("записан ключ «" . StatsCacheHelper::CACHE_KEY_ACTIVE_PLAYERS_GLOBAL . "»\n"));

        $servers = Servers::find()
            ->where(['in', 'status', [Servers::STATUS_NOACTIVE, Servers::STATUS_ACTIVE, Servers::STATUS_WAIT]])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        foreach ($servers as $server) {
            $tag = trim((string) $server->tag);
            if ($tag === '') {
                continue;
            }
            $this->stdout($log("сервер «{$tag}»: сбор активных игроков…\n"));
            $listS = $this->buildActivePlayersList($tag, $minMinutes, $batch, null);
            Yii::$app->cache->set(StatsCacheHelper::cacheKeyActivePlayersServer($tag), $listS, $ttl);
            $this->stdout($log("сервер «{$tag}»: записано " . count($listS) . " игроков\n"));
        }

        $sec = round(microtime(true) - $t0, 2);
        $this->stdout($log("готово за {$sec} с (глобально {$final} игроков)\n"));
        Yii::info("active-players-cache завершено: global={$final}, {$sec} с", __METHOD__);
    }

    /**
     * Список активных игрокей с агрегатами и очками (как для глобального кэша).
     *
     * @param string|null $serverTag null — по всем server_tag; иначе только эта строка server_tag
     * @param callable(string):void|null $chunkLog сообщения по чанкам (только для глобального прогона)
     * @return array<int, array<string, mixed>>
     */
    private function buildActivePlayersList(?string $serverTag, int $minMinutes, int $batchSize, ?callable $chunkLog): array
    {
        $tn = Statistics::tableName();
        $q1 = (new Query())
            ->select('steam_id')
            ->from($tn)
            ->where(['key' => 'playtime']);
        if ($serverTag !== null && $serverTag !== '') {
            $q1->andWhere(['server_tag' => $serverTag]);
        }
        $steamIds = $q1
            ->groupBy('steam_id')
            ->having('SUM(CAST([[value]] AS SIGNED)) >= :min', [':min' => $minMinutes])
            ->column();

        if ($steamIds === []) {
            return [];
        }

        if ($chunkLog !== null) {
            $chunkLog('найдено steam_id: ' . count($steamIds));
        }

        $list = [];
        $chunks = array_chunk($steamIds, $batchSize);
        $totalChunks = count($chunks);
        $foundSteam = count($steamIds);
        $processedSteam = 0;

        foreach ($chunks as $i => $chunk) {
            $n = $i + 1;
            if ($chunkLog !== null) {
                $chunkLog("чанк {$n}/{$totalChunks}: SUM по всем ключам для " . count($chunk) . " steam_id…");
            }

            $q2 = (new Query())
                ->select(['steam_id', 'key', 'sum' => 'SUM(CAST([[value]] AS SIGNED))'])
                ->from($tn)
                ->where(['steam_id' => $chunk]);
            if ($serverTag !== null && $serverTag !== '') {
                $q2->andWhere(['server_tag' => $serverTag]);
            }
            $rows = $q2->groupBy(['steam_id', 'key'])->all();
            $rowCount = count($rows);

            $batchParams = [];
            foreach ($rows as $row) {
                $sid = $row['steam_id'];
                $batchParams[$sid][$row['key']] = (int) $row['sum'];
            }
            unset($rows);

            $bySteamServer = [];
            if ($serverTag === null || $serverTag === '') {
                $q3 = (new Query())
                    ->select(['steam_id', 'server_tag', 'key', 'sum' => 'SUM(CAST([[value]] AS SIGNED))'])
                    ->from($tn)
                    ->where(['steam_id' => $chunk])
                    ->groupBy(['steam_id', 'server_tag', 'key']);
                $rowsByServer = $q3->all();
                foreach ($rowsByServer as $row) {
                    $sidKey = (string) $row['steam_id'];
                    $stag = trim((string) ($row['server_tag'] ?? ''));
                    if ($stag === '') {
                        continue;
                    }
                    $bySteamServer[$sidKey][$stag][$row['key']] = (int) $row['sum'];
                }
                unset($rowsByServer);
            }

            foreach ($chunk as $sid) {
                $processedSteam++;
                $params = $batchParams[$sid] ?? [];
                $playtime = (int) ($params['playtime'] ?? 0);
                if ($playtime < $minMinutes) {
                    continue;
                }
                $scores = UserTop::computeScoresFromAggregatedParams($params);
                $rowOut = [
                    'steam_id' => (string) $sid,
                    'playtime' => $playtime,
                    'kills' => (int) ($params['kills'] ?? 0),
                    'deaths' => (int) ($params['deaths'] ?? 0),
                    'scientists' => (int) ($params['scientists'] ?? 0),
                    'reider' => round((float) $scores['reider'], 2),
                    'farmer' => round((float) $scores['farmer'], 2),
                    'fermer' => round((float) $scores['fermer'], 2),
                    'hunter' => round((float) $scores['hunter'], 2),
                    'fishing' => round((float) $scores['fishing'], 2),
                ];
                if ($serverTag === null || $serverTag === '') {
                    $byServerOut = [];
                    foreach ($bySteamServer[(string) $sid] ?? [] as $stag => $p) {
                        $pt = (int) ($p['playtime'] ?? 0);
                        if ($pt < $minMinutes) {
                            continue;
                        }
                        $srvScores = UserTop::computeScoresFromAggregatedParams($p);
                        $byServerOut[$stag] = [
                            'playtime' => $pt,
                            'kills' => (int) ($p['kills'] ?? 0),
                            'deaths' => (int) ($p['deaths'] ?? 0),
                            'scientists' => (int) ($p['scientists'] ?? 0),
                            'reider' => round((float) $srvScores['reider'], 2),
                            'farmer' => round((float) $srvScores['farmer'], 2),
                            'fermer' => round((float) $srvScores['fermer'], 2),
                            'hunter' => round((float) $srvScores['hunter'], 2),
                            'fishing' => round((float) $srvScores['fishing'], 2),
                        ];
                    }
                    $rowOut['by_server'] = $byServerOut;
                }
                $list[] = $rowOut;
            }
            unset($batchParams);

            if ($chunkLog !== null) {
                $chunkLog("чанк {$n}/{$totalChunks}: строк GROUP BY: {$rowCount}, обработано steam_id: {$processedSteam}/{$foundSteam}, в списке: " . count($list));
            }
            Yii::info("active-players-cache чанк {$n}/{$totalChunks}: rows={$rowCount}, list=" . count($list), __METHOD__);
        }

        return $list;
    }

    /**
     * Топ по очкам «фармер» (руда/дерево) из кэша {@see CACHE_KEY_ACTIVE_PLAYERS_GLOBAL}: таблица в консоль + буфер обмена (Windows: clip).
     * Сначала выполните `stats/active-players-cache`, иначе кэш пуст.
     *
     * `php yii stats/active-players-farmer-clipboard`
     * `php yii stats/active-players-farmer-clipboard 100` — другое число строк
     *
     * @param int $limit сколько строк (по умолчанию 50)
     */
    public function actionActivePlayersFarmerClipboard($limit = 50): void
    {
        $limit = max(1, min(500, (int) $limit));
        $raw = Yii::$app->cache->get(StatsCacheHelper::CACHE_KEY_ACTIVE_PLAYERS_GLOBAL);

        if (!is_array($raw) || $raw === []) {
            $this->stderr("Кэш пуст или устарел. Сначала: yii stats/active-players-cache\n");

            return;
        }

        usort($raw, static function ($a, $b) {
            $fa = (float) ($a['farmer'] ?? 0);
            $fb = (float) ($b['farmer'] ?? 0);

            return $fb <=> $fa;
        });

        $top = array_slice($raw, 0, $limit);
        $steamIds = array_column($top, 'steam_id');
        $names = [];
        if ($steamIds !== []) {
            $rows = \common\models\user\User::find()
                ->select(['steam_id', 'username'])
                ->where(['steam_id' => $steamIds])
                ->asArray()
                ->all();
            foreach ($rows as $row) {
                $names[(string) $row['steam_id']] = (string) ($row['username'] ?? '');
            }
        }

        $header = ['#', 'steam_id', 'username', 'farmer', 'playtime', 'kills', 'deaths'];
        $tsvLines = [implode("\t", $header)];
        $rowsOut = [];

        foreach ($top as $i => $row) {
            $sid = (string) ($row['steam_id'] ?? '');
            $num = $i + 1;
            $username = $names[$sid] ?? '';
            $farmer = (float) ($row['farmer'] ?? 0);
            $playtime = (int) ($row['playtime'] ?? 0);
            $kills = (int) ($row['kills'] ?? 0);
            $deaths = (int) ($row['deaths'] ?? 0);
            $tsvLines[] = implode("\t", [
                (string) $num,
                $sid,
                $username,
                (string) $farmer,
                (string) $playtime,
                (string) $kills,
                (string) $deaths,
            ]);
            $rowsOut[] = [
                $num,
                $sid,
                $username,
                number_format($farmer, 2, '.', ''),
                (string) $playtime,
                (string) $kills,
                (string) $deaths,
            ];
        }

        $tsv = implode("\r\n", $tsvLines) . "\r\n";

        $this->stdout("Топ {$limit} по farmer из кэша «" . StatsCacheHelper::CACHE_KEY_ACTIVE_PLAYERS_GLOBAL . "»\n");
        $this->stdout('| ' . implode(' | ', $header) . " |\n");
        $this->stdout('|' . str_repeat('---|', count($header)) . "\n");
        foreach ($rowsOut as $cells) {
            $this->stdout('| ' . implode(' | ', $cells) . " |\n");
        }

        if (stripos(PHP_OS, 'WIN') === 0) {
            $descriptorspec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc = @proc_open('clip', $descriptorspec, $pipes, null, null, ['bypass_shell' => true]);
            if (is_resource($proc)) {
                fwrite($pipes[0], $tsv);
                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proc);
                $this->stdout("Таблица (TSV) скопирована в буфер обмена — вставьте в Excel / Google Таблицы.\n");
            } else {
                $this->stderr("Не удалось запустить clip; скопируйте вывод вручную.\n");
            }
        } else {
            $this->stdout("(Не Windows — в буфер не копируем; ниже TSV для ручного копирования)\n\n");
            $this->stdout($tsv);
        }
    }

    /**
     * stats/calculate
     * @throws \Exception
     */
    public function actionCalculate() {
        ini_set('memory_limit', '512M');
        /** @var Servers[] $servers */
        $servers = Servers::find()
                         ->andWhere(['status' => Servers::STATUS_ACTIVE])
                         ->orderBy(['sort' => SORT_ASC])
                         ->all();

        foreach ($servers as $server) {
            $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
            $statisticsKills = Statistics::find()
                                       ->andWhere(['server_tag' => $server->tag])
                                       ->andWhere(['wipe' => $wipeDate])
                                       ->andWhere(['key' => 'kills'])
                                       ->indexBy('steam_id')
                                       ->all();
            $statisticsNudeKills = Statistics::find()
                                       ->andWhere(['server_tag' => $server->tag])
                                       ->andWhere(['wipe' => $wipeDate])
                                       ->andWhere(['key' => 'nude_kills'])
                                       ->indexBy('steam_id')
                                       ->all();
            $statisticsDeaths = Statistics::find()
                                         ->andWhere(['server_tag' => $server->tag])
                                         ->andWhere(['wipe' => $wipeDate])
                                         ->andWhere(['key' => 'deaths'])
                                         ->indexBy('steam_id')
                                         ->all();

            $killsData = Kills::find()
                          ->select([
                                      'count' => 'COUNT(*)',
                                      'steam_id' => 'steam_id'
                                   ])
                          ->andWhere(['type' => 'kill'])
                          ->andWhere('signs IS NULL')
                          ->andWhere(['server_tag' => $server->tag])
                          ->andWhere(['wipe' => $wipeDate])
                          ->asArray()
                          ->groupBy(['steam_id'])
                          ->indexBy('steam_id')
                          ->all();

            $nudeKillsData = Kills::find()
                          ->select([
                                      'count' => 'COUNT(*)',
                                      'steam_id' => 'steam_id'
                                   ])
                          ->andWhere(['type' => 'kill'])
                          ->andWhere('wears IS NULL')
                          ->andWhere('signs IS NULL')
                          ->andWhere(['server_tag' => $server->tag])
                          ->andWhere(['wipe' => $wipeDate])
                          ->asArray()
                          ->groupBy(['steam_id'])
                          ->indexBy('steam_id')
                          ->all();

            $deadData = Kills::find()
                          ->select([
                                      'count' => 'COUNT(*)',
                                      'dead' => 'dead'
                                   ])
                          ->andWhere(['type' => 'kill'])
//                          ->andWhere(['>', 'distance', 0])
                          ->andWhere(['server_tag' => $server->tag])
                          ->andWhere(['wipe' => $wipeDate])
                          ->asArray()
                          ->groupBy(['dead'])
                          ->indexBy('dead')
                          ->all();


            foreach ($nudeKillsData as $steamId => $item) {
                if (!empty($statisticsNudeKills[$steamId])) {
                    $statisticsNudeKills[$steamId]->value = $item['count'];
                    $statisticsNudeKills[$steamId]->save();
                } else {
                    $model = new Statistics();
                    $model->key = 'nude_kills';
                    $model->value = $item['count'];
                    $model->steam_id = $steamId;
                    $model->server_tag = $server->tag;
                    $model->wipe = $wipeDate;
                    $model->save(false);
                }
            }
            foreach ($killsData as $steamId => $item) {
                if (!empty($statisticsKills[$steamId])) {
                    $statisticsKills[$steamId]->value = $item['count'];
                    $statisticsKills[$steamId]->save();
                } else {
                    $model = new Statistics();
                    $model->key = 'kills';
                    $model->value = $item['count'];
                    $model->steam_id = $steamId;
                    $model->server_tag = $server->tag;
                    $model->wipe = $wipeDate;
                    $model->save(false);
                }
                if ($item['count'] > 24) {
                    $user = \common\models\user\User::find()
                                ->andWhere(['steam_id' => $steamId])
                                ->one();
                    if (!empty($user)) {
                        $userTop = UserTop::find()
                                          ->andWhere(['user_id' => $user->id])
                                          ->andWhere(['key' => UserTop::TYPE_KILLS])
                                          ->andWhere(['server_id' => $server->id])
                                          ->andWhere(['wipe' => $wipeDate])
                                          ->one();
                        if (!empty($userTop)) {
                            $userTop->value = $item['count'];
                            $userTop->save();
                        } else {
                            $model = new UserTop();
                            $model->key = UserTop::TYPE_KILLS;
                            $model->value = $item['count'];
                            $model->user_id = $user->id;
                            $model->server_id = $server->id;
                            $model->wipe = $wipeDate;
                            $model->save(false);
                        }
                    }
                }
            }
            foreach ($deadData as $steamId => $item) {
                if (!empty($statisticsDeaths[$steamId])) {
                    $statisticsDeaths[$steamId]->value = $item['count'];
                    $statisticsDeaths[$steamId]->save();
                } else {
                    $model = new Statistics();
                    $model->key = 'deaths';
                    $model->value = $item['count'];
                    $model->steam_id = $steamId;
                    $model->server_tag = $server->tag;
                    $model->wipe = $wipeDate;
                    $model->save();
                }
            }
        }
    }

    /**
     * stats/winner
     */
    public function actionWinner() {
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->andWhere(['status' => Servers::STATUS_ACTIVE])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        $users = [];
        foreach ($servers as $server) {
            $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
            /** @var Statistics[] $statisticsPlaytime */
            $statisticsPlaytime = Statistics::find()
                                          ->andWhere(['server_tag' => $server->tag])
                                          ->andWhere(['wipe' => $wipeDate])
                                          ->andWhere(['key' => 'playtime'])
                                          ->andWhere(['>=', 'value', 10*60])
                                          ->indexBy('steam_id')
                                          ->all();


            foreach ($statisticsPlaytime as $item) {
                if (empty($item->user->telegram_chat_id)) {
                    continue;
                }
                if (strtotime($item->user->last_visit_server_at) < strtotime('2024-12-27 00:00:01')) {
                    continue;
                }
                $countQuery = Reports::find()
                                     ->andWhere(['recepient_steam_id' => $item->steam_id])
                                     ->andWhere(['wipe' => $wipeDate])
                                     ->andWhere(['server_tag' => $server->tag]);

                $count = $countQuery->count();
                if ($count > 10) {
                    continue;
                }

                $users[] = [
                    'username' => $item->user->username,
                    'steam_id' => $item->steam_id,
                ];
            }
        }
        shuffle($users);
        $index = 1;
        foreach ($users as $user) {
            echo "{$index}. {$user['username']} ({$user['steam_id']})" . PHP_EOL;
            $index++;
        }

    }
}
