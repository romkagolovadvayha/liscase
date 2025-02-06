<?php

namespace console\controllers;

use common\models\profit\Profit;
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
            try {
                $server->goDraw();
            } catch (\Exception $e) {
                \Yii::$app->telegramChats->sendMessage("actionGoDraw\nFile: {$e->getFile()}:{$e->getLine()}\nError: {$e->getMessage()}");
            }
        }

    }

    /**
     * skin-drops/rec-top
     * @throws \Exception
     */
    public function actionRecTop()
    {
        /** @var Profit[] $profits */
      $profits = Profit::find()
          ->andWhere(['>=', 'created_at', '2025-02-06 11:00:00'])
          ->andWhere(['type' => 12])
          ->andWhere(['amount' => 150])
          ->all();

      foreach ($profits as $profit) {
          $profit->amount = 300;
          $profit->save();
          $profit->userBalance->recalculateBalance();
      }

        /** @var Profit[] $profits */
      $profits = Profit::find()
          ->andWhere(['>=', 'created_at', '2025-02-06 11:00:00'])
          ->andWhere(['type' => 12])
          ->andWhere(['amount' => 50])
          ->all();

      foreach ($profits as $profit) {
          $profit->amount = 150;
          $profit->save();
          $profit->userBalance->recalculateBalance();
      }

    }

}
