<?php

namespace console\controllers;

use common\components\google\TranslateApi;
use common\models\profit\Profit;
use common\models\rcon\RconTasks;
use common\models\servers\Servers;
use common\models\serverskin\ServerSkin;
use common\models\serverskin\ServerSkinCategory;
use common\models\skindrops\SkindropsLink;
use common\models\stats\Info;
use common\models\user\UserPayoutSkins;
use yii\base\BaseObject;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\web\NotFoundHttpException;

class SkinDropsController extends Controller
{
    /**
     * Чекает статусы отправленых скинов
     * skin-drops/status-check
     *
     * @throws \Exception
     */
    public function actionStatusCheck($date = null)
    {
        try {
            UserPayoutSkins::check($date, UserPayoutSkins::STATUS_WAIT);
            sleep(20);
            UserPayoutSkins::check($date,UserPayoutSkins::STATUS_NEW);
        } catch (\Throwable $e) { // перехватит TypeError, Error, Exception — всё
            // можно тихо проглотить, но лучше записать в лог:
            \Yii::warning('skin-drops/status-check ignored: '.$e->getMessage(), __METHOD__);
            return ExitCode::OK; // 0, чтобы крон не ругался
        }

        return ExitCode::OK;
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

    /**
     * skin-drops/get-approved
     * @throws \Exception
     */
    public function actionGetApproved()
    {
        RconTasks::execute("skins.approved.send");
    }

    /**
     * skin-drops/get-category
     * @throws \Exception
     */
    public function actionGetCategory()
    {
        /** @var ServerSkin[] $skins */
        $skins = ServerSkin::find()
            ->andWhere(['status' => ServerSkin::STATUS_ACTIVE])
            ->andWhere('server_skin_category_id IS NULL')
            ->all();

        foreach ($skins as $skin) {
            $info = ServerSkin::getInfoSkin($skin->skin_id);
            if (empty($info)) {
                sleep(60 * 20);
                continue;
            }
            if (empty($info['tags']) && empty($info['result'])) {
                print_r($info);
                print_r($skin->skin_id);
                continue;
            }
            if (empty($info['tags']) && $info['result'] == 9) {
                print_r($info);
                $skin->status = ServerSkin::STATUS_REJECT;
                $skin->save(false);
                RconTasks::execute("skinbox.removeskin {$skin->skin_id}");
                sleep(3);
                continue;
            }
            $tag = $info['tags'][0]['tag'];
            if ($tag == 'Version3') {
                $tag = $info['tags'][1]['tag'];
                if ($tag == 'Skin') {
                    $tag = $info['tags'][2]['tag'];
                }
            } elseif ($tag == 'Skin') {
                $tag = $info['tags'][1]['tag'];
                if ($tag == 'Version3') {
                    $tag = $info['tags'][2]['tag'];
                }
            }
            $category = ServerSkinCategory::getCategory($tag);

            $skin->server_skin_category_id = $category->id;
            $skin->save(false);
            sleep(3);
        }

    }
}
