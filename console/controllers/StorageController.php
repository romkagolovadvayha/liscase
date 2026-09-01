<?php

namespace console\controllers;

use common\models\box\Category;
use common\models\box\Drop;
use common\models\box\DropBlocked;
use common\models\box\Select;
use common\models\box\Sets;
use common\models\servers\Servers;
use common\models\statistics\Kills;
use common\models\statistics\Statistics;
use common\models\statistics\Teams;
use common\models\user\User;
use common\models\user\UserDrop;
use common\models\user\UserTop;
use common\helpers\BlogCacheHelper;
use common\helpers\ProductsCacheHelper;
use common\helpers\ServersCacheHelper;
use common\helpers\SettingsCacheHelper;
use common\helpers\StatsCacheHelper;
use common\models\servers\ServersRadioStation;
use Yii;
use common\models\box\Box;
use yii\base\BaseObject;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Exception as DbException;

class StorageController extends Controller
{
    private const PRICE_FILES = [
        'csmarket' => [
            'url' => 'https://market.csgo.com/api/v2/prices/class_instance/RUB.json',
            'filename' => 'csmarket.json',
        ],
        'rusttm' => [
            'url' => 'https://rust.tm/api/v2/prices/class_instance/RUB.json',
            'filename' => 'rusttm.json',
        ],
    ];

    /**
     * storage/test
     * @throws \Exception
     */
    public function actionTest()
    {
        $filePatch = Yii::getAlias('@frontend/web/images/logo.png');
        $filename = basename($filePatch);
        $response = Yii::$app->s3Api->uploadFile('support/' . time() . $filename, file_get_contents($filePatch));
        print_r($response);
    }

    /**
     * storage/update
     *
     * @throws \Exception
     */
    public function actionUpdate()
    {
        $cacheKey = "Storage_actionUpdate";
        if (Yii::$app->cache->get($cacheKey)) {
            echo 'BLOCKED';
            return null;
        }
        Yii::$app->cache->set($cacheKey, 1, 5*60);
        ini_set('memory_limit', '512M');
        try {
            Statistics::projectStats(true);
            // После длинной фазы projectStats соединение могло закрыться по wait_timeout.
            $this->reconnectMysql();

            /** @var Servers[] $servers */
            $servers = Servers::find()
                              ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                              ->orderBy(['sort' => SORT_ASC])
                              ->all();

            foreach ($servers as $server) {
                $this->warmServerTeamsAndUsers($server);
            }

            //UserTop::getUserTop($servers, true);
            $this->warmKillsLiveWithReconnect($servers);
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage('storage/update ' . $e->getMessage());
        }
        Yii::$app->cache->delete($cacheKey);
    }

    /**
     * Прогрев кэша команд и пользователей по серверу с одним переподключением при 2006.
     */
    protected function warmServerTeamsAndUsers(Servers $server): void
    {
        $attempt = 0;
        while ($attempt < 2) {
            try {
                Teams::getTeams($server, true);
                User::getUsers($server->id, true);

                return;
            } catch (\Throwable $e) {
                if ($attempt === 0 && $this->isMysqlConnectionLost($e)) {
                    Yii::warning(
                        'storage/update: MySQL connection lost, reconnect (server ' . $server->tag . '): ' . $e->getMessage(),
                        __METHOD__
                    );
                    $this->reconnectMysql();
                    $attempt++;

                    continue;
                }
                throw $e;
            }
        }
    }

    /**
     * @param Servers[] $servers
     */
    protected function warmKillsLiveWithReconnect(array $servers): void
    {
        $attempt = 0;
        while ($attempt < 2) {
            try {
                Kills::getLive($servers, true);

                return;
            } catch (\Throwable $e) {
                if ($attempt === 0 && $this->isMysqlConnectionLost($e)) {
                    Yii::warning('storage/update: MySQL connection lost before getLive, reconnect: ' . $e->getMessage(), __METHOD__);
                    $this->reconnectMysql();
                    $attempt++;

                    continue;
                }
                throw $e;
            }
        }
    }

    protected function reconnectMysql(): void
    {
        if (!Yii::$app->has('db')) {
            return;
        }
        try {
            Yii::$app->db->close();
        } catch (\Throwable $e) {
            // соединение уже недействительно
        }
        try {
            Yii::$app->db->open();
        } catch (\Throwable $e) {
            Yii::error('storage/update: DB reconnect failed: ' . $e->getMessage(), __METHOD__);
        }
    }

    protected function isMysqlConnectionLost(\Throwable $e): bool
    {
        $msg = $e->getMessage();
        if (stripos($msg, 'gone away') !== false || strpos($msg, '2006') !== false) {
            return true;
        }
        if ($e instanceof DbException && isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 2006) {
            return true;
        }
        $prev = $e->getPrevious();
        if ($prev instanceof \Throwable) {
            return $this->isMysqlConnectionLost($prev);
        }

        return false;
    }

    /**
     * storage/calculate-tops
     *
     * @throws \Exception
     */
    public function actionCalculateTops()
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        foreach ($servers as $server) {
            $server->calculateTop();
        }
    }

    /**
     * storage/update-tops
     *
     * @throws \Exception
     */
    public function actionUpdateTops()
    {
        $cacheKey = "Storage_actionUpdateTops";
        if (Yii::$app->cache->get($cacheKey)) {
            return 'BLOCKED';
        }
        Yii::$app->cache->set($cacheKey, 1, 5*60);
        ini_set('memory_limit', '512M');
        try {
            /** @var Servers[] $servers */
            $servers = Servers::find()
                              ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                              ->orderBy(['sort' => SORT_ASC])
                              ->all();

            foreach ($servers as $server) {
                UserTop::getUserTops($server, $server->currentWipe(), true);
                UserTop::getAllUserTops($server, $server->currentWipe(), true);
                DropBlocked::getBlockedList($server->id, true);
                // Прогрев кэша API /v1/stats — ответ сразу отдаётся из кэша без тяжёлого getTops
                $wipe = $server->currentWipe();
                $payload = StatsCacheHelper::buildPayload($server, $wipe);
                Yii::$app->cache->set(StatsCacheHelper::cacheKey($server->tag, $wipe), $payload, StatsCacheHelper::CACHE_TTL);
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage('storage/update ' . $e->getMessage());
        }
        Yii::$app->cache->delete($cacheKey);
    }

    /**
     * storage/update-market
     *
     * @throws \Exception
     */
    public function actionUpdateMarket()
    {
        $cacheKey = "Storage_actionUpdateMarket";
        if (Yii::$app->cache->get($cacheKey)) {
            return 'BLOCKED';
        }
        Yii::$app->cache->set($cacheKey, 1, 5*60);
        ini_set('memory_limit', '512M');
        Drop::updateCache();
        $this->updateUserDropStatus();
        $this->actionWarmCaches();
        Yii::$app->cache->delete($cacheKey);
    }

    /**
     * Обновление статуса user_drop с 4 (STATUS_WAIT) на 1 (STATUS_ACTIVE)
     */
    protected function updateUserDropStatus()
    {
        try {
            $count = UserDrop::updateAll(
                ['status' => UserDrop::STATUS_ACTIVE],
                ['status' => UserDrop::STATUS_WAIT]
            );

            if ($count > 0) {
                Yii::info("Updated {$count} UserDrop records from STATUS_WAIT to STATUS_ACTIVE", __METHOD__);
            }
        } catch (\Exception $e) {
            Yii::error("Error updating UserDrop status: " . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * storage/update-servers
     *
     * @throws \Exception
     */
    public function actionUpdateServers()
    {
        $cacheKey = "Storage_actionUpdateServers";
        if (Yii::$app->cache->get($cacheKey)) {
            return 'BLOCKED';
        }
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        Yii::$app->cache->set($cacheKey, 1, 5*60);
        ini_set('memory_limit', '512M');
        try {
            foreach ($servers as $server) {
                $server->getWipes(true);
            }
            // Прогрев кэша API /v1/servers — список серверов отдаётся из кэша для ru и en
            foreach (['ru', 'en'] as $lang) {
                $payload = ServersCacheHelper::buildIndexPayload($lang);
                Yii::$app->cache->set('api_servers_index_' . $lang, $payload, ServersCacheHelper::CACHE_TTL);
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage('storage/update-servers ' . $e->getMessage());
        }
        Yii::$app->cache->delete($cacheKey);
    }

    /**
     * storage/update-price-cs-go
     *
     * @throws \Exception
     */
    public function actionUpdatePriceCsGo() {
        Yii::$app->csGoMarket->items(true);
    }

    /**
     * Atomically refresh a marketplace price file so readers never observe a
     * partially downloaded JSON document.
     */
    public function actionRefreshPriceFile(string $market, int $rebuild = 0): int
    {
        if (!isset(self::PRICE_FILES[$market])) {
            $this->stderr("Unknown market: {$market}\n");
            return ExitCode::USAGE;
        }
        if (!extension_loaded('curl')) {
            $this->stderr("The cURL extension is required.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $uploadDir = Yii::getAlias('@frontend/web/uploads/prices');
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            $this->stderr("Cannot create price directory: {$uploadDir}\n");
            return ExitCode::CANTCREAT;
        }

        $source = self::PRICE_FILES[$market];
        $target = $uploadDir . DIRECTORY_SEPARATOR . $source['filename'];
        $lock = fopen(sys_get_temp_dir() . DIRECTORY_SEPARATOR . "liscase-price-{$market}.lock", 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            $this->stdout("Refresh already running for {$market}.\n");
            return ExitCode::OK;
        }

        $temporary = $target . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
        try {
            $output = fopen($temporary, 'wb');
            if ($output === false) {
                throw new \RuntimeException("Cannot create temporary file: {$temporary}");
            }

            $curl = curl_init($source['url']);
            curl_setopt_array($curl, [
                CURLOPT_FILE => $output,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 300,
                CURLOPT_FAILONERROR => true,
                CURLOPT_USERAGENT => 'Liscase price cache/1.0',
            ]);
            $downloaded = curl_exec($curl);
            $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);
            fflush($output);
            if (function_exists('fsync')) {
                fsync($output);
            }
            fclose($output);

            if ($downloaded !== true || $statusCode < 200 || $statusCode >= 300) {
                throw new \RuntimeException(
                    "Price download failed for {$market}: HTTP {$statusCode}" . ($curlError ? " ({$curlError})" : '')
                );
            }
            if (!$this->looksLikeCompleteJson($temporary)) {
                throw new \RuntimeException("Downloaded price file for {$market} is empty or incomplete JSON");
            }
            if (!rename($temporary, $target)) {
                throw new \RuntimeException("Cannot replace price file: {$target}");
            }
            @chmod($target, 0644);

            $this->stdout("Updated {$target} (" . filesize($target) . " bytes).\n");
            if ($market === 'csmarket' && $rebuild) {
                Yii::$app->csGoMarket->items(true);
                $this->stdout("Rebuilt CS market cache.\n");
            }

            return ExitCode::OK;
        } catch (\Throwable $e) {
            @unlink($temporary);
            Yii::error('Price refresh failed: ' . $e->getMessage(), __METHOD__);
            $this->stderr($e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function looksLikeCompleteJson(string $path): bool
    {
        $size = filesize($path);
        if ($size === false || $size < 2) {
            return false;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $head = fread($handle, (int) min(4096, $size));
        fseek($handle, max(0, $size - 4096));
        $tail = fread($handle, 4096);
        fclose($handle);

        $first = substr(ltrim((string) $head, "\xEF\xBB\xBF \t\r\n"), 0, 1);
        $last = substr(rtrim((string) $tail), -1);

        return ($first === '{' && $last === '}') || ($first === '[' && $last === ']');
    }

    /**
     * storage/warm-caches — прогрев кэшей API: настройки (метрики), категории продуктов, категории блога, радио.
     * Уменьшает постоянные запросы к БД при обращении к /v1/settings, /v1/products/categories, /v1/blog/categories, /v1/radio/list.
     */
    public function actionWarmCaches()
    {
        $cache = Yii::$app->cache;
        try {
            // Настройки (в т.ч. метрики сайта) — один ключ по умолчанию
            $categories = SettingsCacheHelper::DEFAULT_CATEGORIES;
            $payload = SettingsCacheHelper::buildPayload($categories);
            $cache->set(SettingsCacheHelper::cacheKey($categories), $payload, SettingsCacheHelper::CACHE_TTL);

            // Категории продуктов: ru, en × all, 0, 1
            foreach (['ru', 'en'] as $lang) {
                foreach ([null, 0, 1] as $showMainBlock) {
                    $payload = ProductsCacheHelper::buildCategoriesPayload($showMainBlock, $lang);
                    $cache->set(ProductsCacheHelper::categoriesCacheKey($showMainBlock, $lang), $payload, ProductsCacheHelper::CATEGORIES_CACHE_TTL);
                }
            }

            // Категории блога (ключи как у API: язык в суффиксе)
            foreach (['ru-RU', 'en-US'] as $lang) {
                $payload = BlogCacheHelper::buildCategoriesPayload($lang);
                $cache->set('api_blog_categories_' . $lang, $payload, BlogCacheHelper::CATEGORIES_CACHE_TTL);
            }

            if (Yii::$app->settings->get('section_radio')) {
                // Список радиостанций (как в RadioController::actionList)
                $stations = ServersRadioStation::find()
                    ->where(['status' => ServersRadioStation::STATUS_ACTIVE])
                    ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
                    ->all();
                $list = [];
                foreach ($stations as $station) {
                    $item = ['name' => $station->name, 'url' => $station->url];
                    if ($station->logo) {
                        $item['logo'] = $station->getLogoUrl();
                    }
                    $list[] = $item;
                }
                $cache->set('api_radio_list', $list, 600);
            } else {
                $cache->delete('api_radio_list');
            }

            echo "Warmed: settings, products categories, blog categories"
                . (Yii::$app->settings->get('section_radio') ? ", radio list.\n" : ".\n");
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage('storage/warm-caches ' . $e->getMessage());
            throw $e;
        }
    }
}
