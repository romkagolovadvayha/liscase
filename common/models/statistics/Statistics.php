<?php

namespace common\models\statistics;

use common\components\base\ActiveRecord;
use common\models\box\Drop;
use common\models\servers\Servers;
use common\models\stats\Wipe;
use common\models\user\Auth;
use common\models\user\User;
use common\models\user\UserTop;
use common\models\user\UserTree;
use Yii;
use yii\db\Exception as DbException;
use yii\db\Query;

/**
 * @property int    $id
 * @property string $steam_id
 * @property string $key
 * @property int    $value
 * @property string $server_tag
 * @property string $wipe
 *
 * @property User    $user
 */
class Statistics extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'statistics';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['wipe', 'server_tag', 'key', 'steam_id', 'value'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'steam_id' => 'Steam ID',
            'key' => 'Key',
            'value' => 'Value',
            'server_tag' => 'Server Tag',
            'wipe' => 'Wipe',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['steam_id' => 'steam_id']);
    }

    public static function getParam($allParams, $key) {
        if ($key == 'kd') {
            $kills = Statistics::getParam($allParams, 'kills');
            $deaths = Statistics::getParam($allParams, 'deaths');
            if ($kills == 0 || $deaths == 0) {
                return 0;
            }
            return $kills / $deaths;
        }
        if (empty($allParams[$key])) {
            return 0;
        }
        if (is_object($allParams[$key])) {
            return $allParams[$key]->value;
        }
        if (is_array($allParams[$key])) {
            return $allParams[$key]['value'];
        }
        return $allParams[$key];
    }

    public static function getImage($images, $key) {
        if (empty($images[$key])) {
            $defaultPath = 'uploads/drop/870_7aca7dcc75a50be0c7bcf772460d2018.png';
            if (Yii::$app->has('s3Api')) {
                return Yii::$app->s3Api->getPublicUrl($defaultPath);
            }
            $baseUrl = Yii::$app->settings->get('s3_publicUrl');
            return $baseUrl ? rtrim($baseUrl, '/') . '/' . $defaultPath : '/' . $defaultPath;
        }
        $v = $images[$key];
        return is_array($v) ? ($v['image'] ?? $v) : $v;
    }

    /** Большое изображение (150px или оригинал) для оружия и т.п. */
    public static function getImageLarge($images, $key) {
        if (empty($images[$key])) {
            return self::getImage($images, $key);
        }
        $v = $images[$key];
        if (is_array($v) && !empty($v['image_large'])) {
            return $v['image_large'];
        }
        return is_array($v) ? ($v['image'] ?? $v) : $v;
    }

    /**
     * URL статической картинки (например, охота) из S3 или baseUrl.
     * @param string $path путь без ведущего слэша, например images/hunters/Boar.png
     * @return string
     */
    public static function getStaticImageUrl($path) {
        $path = ltrim($path, '/');
        if (Yii::$app->has('s3Api')) {
            return Yii::$app->s3Api->getPublicUrl($path);
        }
        $baseUrl = Yii::$app->settings->get('s3_publicUrl');
        return $baseUrl ? rtrim($baseUrl, '/') . '/' . $path : '/' . $path;
    }

    public static function getName($names, $key) {
        if (empty($names[$key])) {
            return Yii::t('common', 'Без названия');
        }
        return Yii::t('database', $names[$key]);
    }

    public static function getPlayerStats(Servers $server, $steamId, $wipe) {
        $statistics = Statistics::find()
            ->cache(60)
            ->select(['value', 'key'])
            ->andWhere(['steam_id' => $steamId])
            ->andWhere(['server_tag' => $server->tag])
            ->andWhere(['wipe' => $wipe])
            ->indexBy('key')
            ->asArray()
            ->all();

        return $statistics;
    }

    /**
     * Агрегированная статистика игрока за всё время по всем серверам и вайпам (для period=all).
     * @param string $steamId
     * @return array key => ['key' => key, 'value' => sum]
     */
    public static function getPlayerStatsAllTime($steamId) {
        $rows = Statistics::find()
            ->cache(120)
            ->select(['key', 'SUM(CAST(value AS SIGNED)) as value'])
            ->andWhere(['steam_id' => $steamId])
            ->groupBy('key')
            ->asArray()
            ->all();
        $result = [];
        foreach ($rows as $row) {
            $k = $row['key'] ?? '';
            $result[$k] = ['key' => $k, 'value' => (int) ($row['value'] ?? 0)];
        }
        return $result;
    }

    /**
     * Сумма метрик игрока за все вайпы на одном сервере (по server_tag), для превью в заявках в клан и т.п.
     *
     * @param string $steamId
     * @param string $serverTag
     * @return array<string, array{key: string, value: int}>
     */
    public static function getPlayerStatsAllTimeForServerTag($steamId, $serverTag) {
        $serverTag = trim((string) $serverTag);
        if ($serverTag === '') {
            return [];
        }
        $rows = Statistics::find()
            ->cache(120)
            ->select(['key', 'SUM(CAST(value AS SIGNED)) as value'])
            ->andWhere(['steam_id' => $steamId])
            ->andWhere(['server_tag' => $serverTag])
            ->groupBy('key')
            ->asArray()
            ->all();
        $result = [];
        foreach ($rows as $row) {
            $k = $row['key'] ?? '';
            $result[$k] = ['key' => $k, 'value' => (int) ($row['value'] ?? 0)];
        }
        return $result;
    }

    /**
     * Очки топ-категорий (рейдер, фармер, рыбак, охотник, фермер) из сырых key => value statistics.
     * Единый источник правил: {@see UserTop::getRaiting()} и {@see UserTop::computeScoresFromAggregatedParams()}.
     *
     * @param array<string, mixed> $item строка игрока (дополняется полями reider, farmer, fishing, hunter, fermer)
     * @param array<string, mixed> $params агрегат по одному steam_id
     * @return array<string, mixed>
     */
    private static function mergeTopCategoryScores(array $item, array $params): array
    {
        foreach (UserTop::computeScoresFromAggregatedParams($params) as $key => $value) {
            $item[$key] = $value;
        }

        return $item;
    }

    public static function getStats(Servers $server, $steamId = null, $all = true, $wipeDate = null, $cache = true) {
        ini_set('memory_limit', '512M');
        // Если запрашивается статистика одного пользователя, используем отдельный кэш-ключ
        if (!empty($steamId) && !$all) {
            $cacheKey = "getStats_data_serverId{$server->id}_steamId{$steamId}_single";
        } else {
            $cacheKey = "getStats_data_serverId{$server->id}_" . ($all ? 1 : 0);
        }
        $data = null;
        if ($cache) {
            $data = Yii::$app->cache->get($cacheKey);
        }
        try {
            if (empty($data)) {
                if (empty($wipeDate)) {
                    $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime(
                            $server->next_wipe
                        ))->format('Y-m-d');
                }
                /** @var Wipe[] $models */
                $query = Statistics::find()
                    ->cache(3*60)
                    ->andWhere(['server_tag' => $server->tag])
                    ->andWhere(['wipe' => $wipeDate]);

                // Оптимизация: если нужна статистика только одного пользователя, фильтруем сразу в SQL
                if (!empty($steamId) && !$all) {
                    $query->andWhere(['steam_id' => $steamId]);
                }

                $statistics = $query->asArray()->all();

                $userList = [];
                foreach ($statistics as $item) {
                    $userList[$item['steam_id']][$item['key']] = $item['value'];
                }

                $steamIds = array_keys($userList);
                $models = [];
                $isSingleUser = !empty($steamId) && !$all;

                foreach ($steamIds as $_steamId) {
                    $params = $userList[$_steamId];
                    if (!$all && Statistics::getParam($params, 'playtime') <= 60) {
                        continue;
                    }
                    $item = $params;
                    $item['steam_id'] = $_steamId;
                    $item['playtime'] = Statistics::getParam($params, 'playtime');
                    $item['deaths'] = Statistics::getParam($params, 'deaths');
                    $item['scientists'] = Statistics::getParam($params, 'scientists');
                    $item['kills'] = Statistics::getParam($params, 'kills');
                    $item = Statistics::mergeTopCategoryScores($item, $params);
                    $models[] = $item;
                }

                // Если запрашивается статистика одного пользователя, не формируем топики (экономим время)
                if ($isSingleUser) {
                    $data = [
                        'models' => $models
                    ];
                } else {
                    $data = [
                        'kills' => Statistics::getTopList($models, 'kills'),
                        'scientists' => Statistics::getTopList($models, 'scientists'),
                        'playtime' => Statistics::getTopList($models, 'playtime'),
                        'reider' => Statistics::getTopList($models, 'reider'),
                        'farmer' => Statistics::getTopList($models, 'farmer'),
                        'fishing' => Statistics::getTopList($models, 'fishing'),
                        'hunter' => Statistics::getTopList($models, 'hunter'),
                        'fermer' => Statistics::getTopList($models, 'fermer'),
                        'deaths' => Statistics::getTopList($models, 'deaths'),
                        'models' => $models
                    ];
                }

                Yii::$app->cache->set($cacheKey, $data, 15 * 60);
            }
        } catch (\Exception $e) {
            Yii::$app->telegramReports->sendMessage("getStats {$server->tag}: " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
        }

        if (!empty($steamId)) {
            foreach ($data['models'] as $item) {
                if (!empty($steamId) && $item['steam_id'] == $steamId) {
                    $item['user'] = \common\models\user\User::findBySteamId($item['steam_id'], false, 'statistics');
                    $data['player'] = $item;
                    break;
                }
            }
        }

        return $data;
    }

    /**
     * Агрегированные метрики по каждому steam_id за вайп на сервере (те же правила топов, что getStats all=true и user_top).
     * Без кэша и без загрузки User.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function aggregatePlayerRowsForWipe(Servers $server, string $wipe): array
    {
        $statistics = Statistics::find()
            ->andWhere(['server_tag' => $server->tag])
            ->andWhere(['wipe' => $wipe])
            ->asArray()
            ->all();

        $userList = [];
        foreach ($statistics as $row) {
            $userList[$row['steam_id']][$row['key']] = $row['value'];
        }

        $models = [];
        foreach (array_keys($userList) as $_steamId) {
            $params = $userList[$_steamId];
            $item = $params;
            $item['steam_id'] = $_steamId;
            $item['playtime'] = Statistics::getParam($params, 'playtime');
            $item['deaths'] = Statistics::getParam($params, 'deaths');
            $item['scientists'] = Statistics::getParam($params, 'scientists');
            $item['kills'] = Statistics::getParam($params, 'kills');
            $item = Statistics::mergeTopCategoryScores($item, $params);
            $models[] = $item;
        }

        return $models;
    }

    public static function getTopWidgetItem($key, $stats, $index = 0) {
        if (empty($stats[$key])) {
            return [];
        }

        $item = $stats[$key]['players'][$index];
        $item['total_score'] = $item[$key];
        $item['user'] = User::findBySteamId($item['steam_id'], false, 'statistics 2');

        return $item;
    }

    /**
     * @param $models
     * @param $attrName
     * @param $steamId
     *
     * @return array
     */
    public static function getTopList($models, $attrName, $steamId = null) {
        usort($models, function ($a, $b) use ($attrName) {
            return ($b[$attrName] < $a[$attrName]) ? -1 : 1;
        });
        $data = [];
        foreach ($models as $i => $item) {
            if ($i <= 2) {
                $item['user'] = \common\models\user\User::findBySteamId($item['steam_id'], false, 'statistics 3');
            }
            $data[] = $item;
        }

        return [
            'players' => $data,
            'attrName' => $attrName,
        ];
    }

    public static function getRaiderItem($names, $images, $player, $key, $score) {
        $result = [];
        $_key = str_replace('.deployed', '', $key);
        $result['image'] = Statistics::getImage($images, $_key);
        $result['name'] = Statistics::getName($names, $_key);
        $result['count'] = Statistics::getParam($player, $key);
        $result['desc'] = Statistics::getParam($player, $key);
        $result['score'] = $score;
        return $result;
    }

    public static function getFermItem($images, $player, $key, $name, $score) {
        $result = [];

        $result['image'] = Statistics::getImage($images, $key);
        $result['name'] = $name;
        $result['score'] = $score;
        $result['count'] = Statistics::getParam($player, $key);
        $result['desc'] = number_format(Statistics::getParam($player, $key), 0);

        return $result;
    }

    public static function getLevelCardItem($images, $names, $player, $key) {
        $result = [];

        $result['name'] = Yii::t('database', Statistics::getParam($names, $key));
        $result['image'] = Statistics::getImage($images, $key);
        $result['count'] = Statistics::getParam($player, $key);
        $result['desc'] = number_format(Statistics::getParam($player, $key), 0);

        return $result;
    }

    public static function getFoodItem($images, $names, $player, $key) {
        $result = [];

        $result['count'] = Statistics::getParam($player, 'mod_' . $key);
        $result['image'] = Statistics::getImage($images, $key);
        $result['key'] = $key;
        $result['name'] = Statistics::getName($names, $key);
        $result['desc'] = number_format(Statistics::getParam($player, 'mod_' . $key), 0);

        return $result;
    }

    public static function getMedicalItem($images, $names, $player, $key) {
        $result = [];

        $result['count'] = Statistics::getParam($player, $key['param']);
        $result['image'] = Statistics::getImage($images, $key['key']);
        $result['name'] = Statistics::getName($names, $key['key']);
        $result['desc'] = number_format(Statistics::getParam($player, $key['param']), 0);

        return $result;
    }

    public static function getFishItem($images, $player, $key, $name, $score) {
        $result = [];

        $result['name'] = $name;
        $result['score'] = $score;
        $result['count'] = Statistics::getParam($player, $key);
        $result['image'] = Statistics::getImage($images, $key);
        $result['desc'] = number_format(Statistics::getParam($player, $key), 0);

        return $result;
    }

    public static function getFarmItem($images, $names, $player, $key, $name, $score) {
        $result = [];
        $result['image'] = Statistics::getImage($images, $key);
        $result['name'] = $name;
        $result['score'] = $score;
        $result['count'] = Statistics::getParam($player, $key);
        $result['desc'] = number_format(Statistics::getParam($player, $key), 0);

        return $result;
    }

    public static function projectStats($update = false) {
        $cacheKey = 'Statistics_projectStats_';
        if (!$update) {
            $cached = Yii::$app->cache->get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $result = [];

        $result['users'] = User::find()
            ->count();

        $result['online'] = Servers::find()
            ->sum('players + joined') ?? 0;

        $result['count'] = Servers::find()
            ->andWhere(['NOT IN', 'status', [Servers::STATUS_CLOSED]])
            ->count();

        /** @var Servers[] $servers */
        $servers = Servers::find()
            ->cache(60)
            ->andWhere(['NOT IN', 'status', [Servers::STATUS_CLOSED]])
            ->all();

        $pingByServerId = [];
        if ($servers !== []) {
            $serverIds = [];
            foreach ($servers as $server) {
                $serverIds[] = (int) $server->id;
            }
            $serverIds = array_values(array_unique(array_filter($serverIds)));
            if ($serverIds !== []) {
                $threshold = date('Y-m-d H:i:s', time() - 5 * 60);
                $rows = (new Query())
                    ->from(['u' => User::tableName()])
                    ->select(['server_id' => 'u.server_id', 'avg_ping' => 'AVG(u.ping)'])
                    ->where(['>=', 'u.last_visit_server_at', $threshold])
                    ->andWhere(['u.status' => User::STATUS_ACTIVE])
                    ->andWhere(['u.server_id' => $serverIds])
                    ->groupBy(['u.server_id'])
                    ->all();
                foreach ($rows as $row) {
                    $sid = (int) ($row['server_id'] ?? 0);
                    if ($sid > 0) {
                        $pingByServerId[$sid] = (float) ($row['avg_ping'] ?? 0);
                    }
                }
            }
        }

        $result['servers'] = [];
        foreach ($servers as $server) {
            $result['servers'][$server->id] = [
                'ping' => $pingByServerId[(int) $server->id] ?? 0,
            ];
        }

        Yii::$app->cache->set($cacheKey, $result, 7*24*60*60);
        return $result;
    }

    public static function productsImages($update = false) {
        $cacheKey = 'Statistics_productsImages3_';
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            return Yii::$app->cache->get($cacheKey);
        }

        $result = [];

        /** @var Drop[] $drops */
        $drops = Drop::find()
            ->andWhere(['<>', 'eng_name', ''])
            ->with('dropImages', 'imageOrig')
            ->all();

        foreach ($drops as $item) {
            $url = $item->image64();
            if ($url === null && $item->imageOrig !== null) {
                $url = $item->imageOrig->getImagePubUrl();
            }
            $urlLarge = $item->image150();
            if ($urlLarge === null) {
                $urlLarge = $item->image();
            }
            if ($urlLarge === null && $item->imageOrig !== null) {
                $urlLarge = $item->imageOrig->getImagePubUrl();
            }
            // Если в БД только 64px — пробуем URL 150px по соглашению путей (drop64 → drop150)
            if ($urlLarge === null && $url !== null && $url !== '') {
                $candidate = str_replace(['/drop64/', 'drop64/'], ['/drop150/', 'drop150/'], $url);
                $urlLarge = ($candidate !== $url) ? $candidate : $url;
            }
            if ($urlLarge === null) {
                $urlLarge = $url;
            }
            $result[$item->eng_name] = [
                'image' => $url,
                'image_large' => $urlLarge,
            ];
        }

        Yii::$app->cache->set($cacheKey, $result, 30*60);
        return $result;
    }

    public static function productsNames($update = false) {
        $cacheKey = 'Statistics_productsNames_';
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            return Yii::$app->cache->get($cacheKey);
        }

        $result = [];

        /** @var Drop[] $drops */
        $drops = Drop::find()
            ->andWhere(['<>', 'eng_name', ''])
            ->all();

        foreach ($drops as $item) {
            $result[$item->eng_name] = $item->name;
        }

        Yii::$app->cache->set($cacheKey, $result, 30*60);
        return $result;
    }

    /**
     * Пакетный INSERT … ON DUPLICATE KEY UPDATE value = value + VALUES(value) по уникальному
     * (steam_id, server_tag, wipe, key). Каждая строка: [steam_id, server_tag, key, delta_int, wipe].
     * MySQL — пачками; иной драйвер — fallback с меньшим числом SELECT.
     *
     * @param array<int, array{0: string, 1: string, 2: string, 3: int, 4: string}> $rows
     */
    public static function batchUpsertIncrementValues(array $rows, int $batchSize = 500): void
    {
        if ($rows === []) {
            return;
        }
        $merged = self::mergeStatisticsIncrementRows($rows);
        if ($merged === []) {
            return;
        }
        $db = Yii::$app->db;
        if ($db->driverName === 'mysql') {
            self::executeMysqlBatchUpsertIncrement($merged, $batchSize);

            return;
        }
        self::executeFallbackBatchUpsertIncrement($merged);
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: string, 3: int, 4: string}> $rows
     * @return array<int, array{0: string, 1: string, 2: string, 3: int, 4: string}>
     */
    private static function mergeStatisticsIncrementRows(array $rows): array
    {
        $sums = [];
        foreach ($rows as $r) {
            if (!isset($r[0], $r[1], $r[2], $r[3], $r[4])) {
                continue;
            }
            $delta = (int) $r[3];
            if ($delta === 0) {
                continue;
            }
            $k = (string) $r[0] . "\x1e" . (string) $r[1] . "\x1e" . (string) $r[2] . "\x1e" . (string) $r[4];
            $sums[$k] = ($sums[$k] ?? 0) + $delta;
        }
        $out = [];
        foreach ($sums as $k => $v) {
            $parts = explode("\x1e", $k, 4);
            if (count($parts) !== 4) {
                continue;
            }
            $out[] = [$parts[0], $parts[1], $parts[2], $v, $parts[3]];
        }

        return $out;
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: string, 3: int, 4: string}> $merged
     */
    private static function executeMysqlBatchUpsertIncrement(array $merged, int $batchSize): void
    {
        $db = Yii::$app->db;
        $tableName = $db->schema->getRawTableName(self::tableName());
        $chunks = array_chunk($merged, $batchSize);
        foreach ($chunks as $chunk) {
            // Стабильный порядок строк — меньше шанс взаимоблокировки при разном порядке пачек у воркеров.
            usort($chunk, static function (array $a, array $b): int {
                foreach ([0, 1, 4, 2] as $idx) {
                    $cmp = strcmp((string) $a[$idx], (string) $b[$idx]);
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                }

                return 0;
            });
            $placeholders = [];
            $params = [];
            $i = 0;
            foreach ($chunk as $row) {
                $placeholders[] = "(:s{$i}, :t{$i}, :k{$i}, :v{$i}, :w{$i})";
                $params[":s{$i}"] = $row[0];
                $params[":t{$i}"] = $row[1];
                $params[":k{$i}"] = $row[2];
                $params[":v{$i}"] = $row[3];
                $params[":w{$i}"] = $row[4];
                $i++;
            }
            $sql = "INSERT INTO {$tableName} (steam_id, server_tag, `key`, value, wipe)\nVALUES "
                . implode(",\n", $placeholders)
                . "\nON DUPLICATE KEY UPDATE value = value + VALUES(value)";
            self::executeCommandWithDeadlockRetries($db, $sql, $params);
        }
    }

    /**
     * InnoDB: INSERT … ON DUPLICATE KEY UPDATE при конкурирующих воркерах иногда даёт 1213 — безопасно повторить.
     *
     * @param array<string, mixed> $params
     */
    private static function executeCommandWithDeadlockRetries(\yii\db\Connection $db, string $sql, array $params, int $maxAttempts = 5): void
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $db->createCommand($sql)->bindValues($params)->execute();

                return;
            } catch (DbException $e) {
                if ($attempt >= $maxAttempts || !self::isMysqlDeadlockException($e)) {
                    throw $e;
                }
                usleep(50000 * $attempt + random_int(0, 35000));
            }
        }
    }

    private static function isMysqlDeadlockException(DbException $e): bool
    {
        $info = $e->errorInfo ?? [];
        if (isset($info[1]) && (int) $info[1] === 1213) {
            return true;
        }
        $msg = $e->getMessage();

        return stripos($msg, 'Deadlock') !== false && stripos($msg, '1213') !== false;
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: string, 3: int, 4: string}> $merged
     */
    private static function executeFallbackBatchUpsertIncrement(array $merged): void
    {
        $groups = [];
        foreach ($merged as $row) {
            $g = $row[0] . "\x1e" . $row[1] . "\x1e" . $row[4];
            $groups[$g][] = [$row[2], $row[3]];
        }
        foreach ($groups as $g => $keyDeltas) {
            $parts = explode("\x1e", $g, 3);
            if (count($parts) !== 3) {
                continue;
            }
            [$steamId, $serverTag, $wipe] = $parts;
            $statistics = self::find()
                ->andWhere(['steam_id' => $steamId])
                ->andWhere(['server_tag' => $serverTag])
                ->andWhere(['wipe' => $wipe])
                ->indexBy('key')
                ->all();
            $transaction = Yii::$app->db->beginTransaction();
            try {
                foreach ($keyDeltas as [$key, $value]) {
                    if (!empty($statistics[$key])) {
                        $statistics[$key]->value += $value;
                        $statistics[$key]->save(false);
                    } else {
                        $m = new self();
                        $m->steam_id = $steamId;
                        $m->server_tag = $serverTag;
                        $m->key = $key;
                        $m->value = $value;
                        $m->wipe = $wipe;
                        $m->save(false);
                    }
                }
                $transaction->commit();
            } catch (\Exception $e) {
                $transaction->rollBack();
                throw $e;
            }
        }
    }
}
