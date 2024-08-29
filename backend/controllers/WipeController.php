<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\models\box\Drop;
use common\models\profit\Profit;
use common\models\promocode\Promocode;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\stats\Wipe;
use common\models\user\User;
use common\models\user\UserPromocode;
use common\models\user\UserTask;
use yii\base\BaseObject;
use yii\web\Controller;
use Yii;

class WipeController extends Controller
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN],
                    ],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionBlock()
    {
        /** @var Drop[] $drops */
        $drops = Drop::find()
            ->all();

        foreach ($drops as $drop) {
            if (!empty($drop->blocked_hour)) {
                $date = new \DateTime();
                $date->modify("+{$drop->blocked_hour} hour");
                $drop->blocked_at = $date->format('Y-m-d H:i:s');
                $drop->save();
            }
        }
        Yii::$app->session->addFlash('success', 'Предметы успешно заблокированы!');
        return $this->redirect('index');
    }

    public function actionTop()
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->all();
        foreach ($servers as $server) {
            $stats = Statistics::getStats($server);
            if (empty($stats)) {
                continue;
            }
            foreach (['kills', 'scientists', 'hunter', 'fermer', 'farmer', 'fishing', 'playtime', 'reider'] as $type) {
                $userStats = Statistics::getTopWidgetItem($type, $stats);
                if (!empty($userStats) && !empty($userStats['user'])) {
                    $profit                  = new Profit();
                    $profit->status          = 1;
                    $profit->type            = Profit::TYPE_TOP;
                    $profit->amount          = 500;
                    $profit->user_balance_id = $userStats['user']->getPersonalBalance()->id;
                    $profit->comment         = 'Награда за топ сервера';
                    $profit->created_at      = date('Y-m-d H:i:s');
                    $profit->save(false);
                    $userStats['user']->getPersonalBalance()->recalculateBalance();
                }
            }
        }

        Yii::$app->session->addFlash('success', 'Награды распределены успешно!');
        return $this->redirect('index');
    }

    public function actionPromocode()
    {
        /** @var UserPromocode[] $uPromocodes */
        $uPromocodes = UserPromocode::find()
            ->andWhere(['promocode_id' => 2])
            ->all();

        foreach ($uPromocodes as $item) {
            $item->delete();
        }

        Yii::$app->session->addFlash('success', 'Промокод теперь можно ввести заного!');
        return $this->redirect('index');
    }

    public function actionTaskClear()
    {
        /** @var UserTask[] $items */
        $items = UserTask::find()
            ->all();

        foreach ($items as $item) {
            $item->delete();
        }

        Yii::$app->session->addFlash('success', 'Задания обнулены!');
        return $this->redirect('index');
    }

}