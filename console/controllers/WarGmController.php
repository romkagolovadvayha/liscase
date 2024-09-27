<?php

namespace console\controllers;

use common\models\box\Box;
use common\models\profit\Profit;
use common\models\servers\Servers;
use common\models\user\User;
use common\models\user\UserBox;
use common\models\user\UserDrop;
use yii\base\BaseObject;
use yii\console\Controller;

class WarGmController extends Controller
{
    /**
     * Начисление бонусов за голосование на сайте WarGm
     * war-gm/sync
     *
     * @throws \Exception
     */
    public function actionSync()
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->andWhere('wargm_id IS NOT NULL')
                          ->andWhere(['status' => Servers::STATUS_ACTIVE])
                          ->all();
        foreach ($servers as $server) {
            $votes = \Yii::$app->wargm->getVotes($server->wargm_id);
            if (empty($votes['responce'])) {
                continue;
            }
            foreach ($votes['responce']['data'] as $vote) {
                $user = User::findBySteamId($vote['user_steam_id']);
                if (empty($user)) {
                    continue;
                }
                $userBalance = $user->getPersonalBalance();
                $exists = Profit::find()
                                ->andWhere(['user_balance_id' => $userBalance->id])
                                ->andWhere(['type' => Profit::TYPE_WARGM_BONUS])
                                ->andWhere(['service_id' => $vote['id']])
                                ->exists();
                if ($exists) {
                    continue;
                }
                $profit                  = new Profit();
                $profit->status          = 1;
                $profit->type            = Profit::TYPE_WARGM_BONUS;
                $profit->amount          = $vote['points'] * 10;
                $profit->service_id      = $vote['id'];
                $profit->user_balance_id = $userBalance->id;
                $profit->comment         = 'Бонус за голос на WarGM.ru';
                $profit->created_at      = date('Y-m-d H:i:s');
                $profit->save(false);
                $userBalance->recalculateBalance();
            }

        }
    }

}
