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
use yii\db\Exception as DbException;

class StorageController extends Controller
{
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

            // Категории блога: ru, en
            foreach (['ru', 'en'] as $lang) {
                $payload = BlogCacheHelper::buildCategoriesPayload($lang);
                $cache->set('api_blog_categories_' . $lang, $payload, BlogCacheHelper::CATEGORIES_CACHE_TTL);
            }

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

            echo "Warmed: settings, products categories, blog categories, radio list.\n";
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage('storage/warm-caches ' . $e->getMessage());
            throw $e;
        }
    }
}
