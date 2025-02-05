<?php

namespace console\controllers;

use common\models\servers\Servers;
use common\models\skindrops\SkindropsLink;
use common\models\stats\Info;
use common\models\user\UserPayoutSkins;
use yii\base\BaseObject;
use yii\console\Controller;
use yii\web\NotFoundHttpException;

class SkinDropsController extends Controller
{
    /**
     * Чекает статусы отправленых скинов
     * skin-drops/status-check
     *
     * @throws \Exception
     */
    public function actionStatusCheck()
    {
        UserPayoutSkins::check();
    }

    /**
     * Очистить пустые
     * skin-drops/clear
     *
     * @throws \Exception
     */
    public function actionClear()
    {
        UserPayoutSkins::clear();
    }

    /**
     * Провести розыгрыш
     * skin-drops/go-draw
     *
     * @throws \Exception
     */
    public function actionGoDraw()
    {
        if (!\Yii::$app->settings->get('section_skindrops')) {
            return;
        }

        /** @var Servers[] $servers */
        $servers = Servers::find()
            ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE]])
            ->andWhere(['skindrops' => 1])
            ->all();

        foreach ($servers as $server) {
            $server->goDraw();
        }

    }

}
