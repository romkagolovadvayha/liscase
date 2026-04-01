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
    /** Суммарный playtime (мин.) по всем серверам/вайпам не ниже этого порога — по умолчанию 20 суток. */
    private const ACTIVE_PLAYERS_MIN_PLAYTIME = 20 * 24 * 60;

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
     * Пример: `php yii stats/active-players-cache` или `php yii stats/active-players-cache 7200` (TTL, сек).
     * Индекс под шаг 1: покрывающий (key, steam_id, value) — m260402_100000_statistics_covering_index_playtime_agg
     * (двухколоночный m260401 часто хуже: без value идут lookups в таблицу).
     *
     * @param int $ttl время жизни кэша в секундах
     */
    public function actionActivePlayersCache($ttl = 3600): void
    {
        ini_set('memory_limit', '512M');
        $minMinutes = self::ACTIVE_PLAYERS_MIN_PLAYTIME;
        $ttl = (int) $ttl;
        $batch = self::ACTIVE_PLAYERS_STEAM_BATCH;
        $t0 = microtime(true);
        $log = static function (string $msg) use ($t0): string {
            return sprintf('[%.2fs] active-players-cache: %s', microtime(true) - $t0, $msg);
        };

        $this->stdout($log("старт, порог playtime >= {$minMinutes} мин, TTL кэша {$ttl} с, батч steam_id = {$batch}\n"));
        Yii::info("active-players-cache старт: min_playtime={$minMinutes}, ttl={$ttl}, batch={$batch}", __METHOD__);

        $this->stdout($log("шаг 1: steam_id с SUM(playtime) >= {$minMinutes} (только колонка steam_id)…\n"));
        $steamIds = (new Query())
            ->select('steam_id')
            ->from(Statistics::tableName())
            ->where(['key' => 'playtime'])
            ->groupBy('steam_id')
            ->having('SUM(CAST([[value]] AS SIGNED)) >= :min', [':min' => $minMinutes])
            ->column();

        $foundSteam = count($steamIds);
        $this->stdout($log("найдено steam_id: {$foundSteam}\n"));
        Yii::info("active-players-cache: steam_id с порогом playtime: {$foundSteam}", __METHOD__);

        if ($steamIds === []) {
            $this->stdout($log("запись пустого массива в кэш…\n"));
            Yii::$app->cache->set(StatsCacheHelper::CACHE_KEY_ACTIVE_PLAYERS_GLOBAL, [], $ttl);
            $this->stdout($log('готово: в кэше 0 записей, ключ ' . StatsCacheHelper::CACHE_KEY_ACTIVE_PLAYERS_GLOBAL . "\n"));

            return;
        }

        $list = [];
        $chunks = array_chunk($steamIds, $batch);
        $totalChunks = count($chunks);
        $this->stdout($log("шаг 2: по чанкам — SQL агрегат + расчёт очков (без хранения всех игроков в одном массиве)…\n"));

        $processedSteam = 0;
        foreach ($chunks as $i => $chunk) {
            $n = $i + 1;
            $this->stdout($log("чанк {$n}/{$totalChunks}: SUM по всем ключам для " . count($chunk) . " steam_id…\n"));
            $rows = (new Query())
                ->select(['steam_id', 'key', 'sum' => 'SUM(CAST([[value]] AS SIGNED))'])
                ->from(Statistics::tableName())
                ->where(['steam_id' => $chunk])
                ->groupBy(['steam_id', 'key'])
                ->all();
            $rowCount = count($rows);

            $batchParams = [];
            foreach ($rows as $row) {
                $sid = $row['steam_id'];
                $batchParams[$sid][$row['key']] = (int) $row['sum'];
            }
            unset($rows);

            foreach ($chunk as $sid) {
                $processedSteam++;
                $params = $batchParams[$sid] ?? [];
                $playtime = (int) ($params['playtime'] ?? 0);
                if ($playtime < $minMinutes) {
                    continue;
                }
                $scores = UserTop::computeScoresFromAggregatedParams($params);
                $list[] = [
                    'steam_id' => (string) $sid,
                    'playtime' => $playtime,
                    'kills' => (int) ($params['kills'] ?? 0),
                    'deaths' => (int) ($params['deaths'] ?? 0),
                    'scientists' => (int) ($params['scientists'] ?? 0),
                    'reider' => $scores['reider'],
                    'farmer' => $scores['farmer'],
                    'fermer' => $scores['fermer'],
                    'hunter' => $scores['hunter'],
                    'fishing' => $scores['fishing'],
                ];
            }
            unset($batchParams);

            $this->stdout($log("чанк {$n}/{$totalChunks}: строк GROUP BY: {$rowCount}, обработано steam_id: {$processedSteam}/{$foundSteam}, в списке: " . count($list) . "\n"));
            Yii::info("active-players-cache чанк {$n}/{$totalChunks}: rows={$rowCount}, list=" . count($list), __METHOD__);
        }

        $final = count($list);
        if ($final === 0) {
            $this->stdout($log("запись пустого массива в кэш…\n"));
        } else {
            $this->stdout($log("запись в кэш ключ «" . StatsCacheHelper::CACHE_KEY_ACTIVE_PLAYERS_GLOBAL . "», элементов: {$final}\n"));
        }
        Yii::$app->cache->set(StatsCacheHelper::CACHE_KEY_ACTIVE_PLAYERS_GLOBAL, $list, $ttl);
        $sec = round(microtime(true) - $t0, 2);
        $this->stdout($log("готово за {$sec} с: в кэше {$final} игроков\n"));
        Yii::info("active-players-cache завершено: {$final} игроков, {$sec} с", __METHOD__);
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
