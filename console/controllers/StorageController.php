<?php

namespace console\controllers;

use common\models\box\Category;
use common\models\box\Drop;
use common\models\box\Select;
use common\models\box\Sets;
use common\models\servers\Servers;
use common\models\statistics\Kills;
use common\models\statistics\Statistics;
use common\models\statistics\Teams;
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
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        foreach ($servers as $server) {
            Teams::getTeams($server, true);
            UserTop::getUserTops($server, $server->currentWipe(), true);
        }

        UserTop::getUserTop($servers, true);
        Kills::getLive($servers, true);
        Statistics::projectStats(true);
        Statistics::productsImages(true);
        Statistics::productsNames(true);
        Sets::getSetsForMarket(true, true);
        Sets::getSetsForMarket(false, true);
        Select::getForMarket(true, true);
        Select::getForMarket(false, true);
        Drop::getForMarket(true, true);
        Drop::getForMarket(false, true);
        Category::getCategories(true, true);
        Category::getCategories(false, true);
    }
}
