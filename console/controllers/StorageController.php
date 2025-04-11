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
use common\models\user\UserTop;
use Yii;
use common\models\box\Box;
use yii\base\BaseObject;
use yii\console\Controller;

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

            /** @var Servers[] $servers */
            $servers = Servers::find()
                              ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                              ->orderBy(['sort' => SORT_ASC])
                              ->all();

            foreach ($servers as $server) {
                Teams::getTeams($server, true);
                User::getUsers($server->id, true);
            }

            //UserTop::getUserTop($servers, true);
            Kills::getLive($servers, true);
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage('storage/update ' . $e->getMessage());
        }
        Yii::$app->cache->delete($cacheKey);
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
        Yii::$app->cache->delete($cacheKey);
    }
}
