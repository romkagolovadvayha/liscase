<?php

namespace console\controllers;

use common\models\invoice\Deposit;
use common\models\servers\Servers;
use common\models\skindrops\SkindropsLink;
use common\models\stats\Info;
use yii\base\BaseObject;
use yii\console\Controller;

class DepositController extends Controller
{
    /**
     * Чекалка депозитов
     * deposit/sync
     *
     * @throws \Exception
     */
    public function actionSync()
    {
        /** @var Deposit[] $deposits */
        $deposits = Deposit::find()
            ->andWhere(['status' => Deposit::STATUS_WAIT_CONFIRM])
            ->andWhere('payment_id is not null')
            ->andWhere(['NOT IN', 'payment_type', Deposit::TYPE_PAYMENT_SBP, Deposit::TYPE_PAYMENT_CARD])
            ->orderBy(['id' => SORT_DESC])
            ->all();

        foreach ($deposits as $deposit) {
            $deposit->check();
        }
    }
}
