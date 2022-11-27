<?php

namespace common\controllers;

use Yii;
use common\forms\finance\AbstractFinanceConfirmForm;
use wealth\forms\PayoutRequestForm;

class FinanceConfirmController extends WebController
{
    public function actionGenerateConfirmCode()
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }

        $formId = Yii::$app->request->post('form');

        if ($formId == 'payout-form') {
            $model = new \cabinet\forms\finance\PayoutForm();

        } elseif ($formId == 'transfer-form') {
            $model = new \cabinet\forms\finance\TransferForm();

        } elseif ($formId == 'wealth-payout-form') {
            $model = new PayoutRequestForm();
            $model->setScenario(PayoutRequestForm::SCENARIO_PAYOUT);

        } elseif ($formId == 'wealth-transfer-form') {
            $model = new \wealth\forms\PersonalAccountTransferForm();
            $model->setScenario(PayoutRequestForm::SCENARIO_TRANSFER);

        } elseif ($formId == 'wealth-payout-wallet-form') {
            $model = new \wealth\forms\PayoutWalletForm();

        } elseif ($formId == 'convert-form') {
            $model = new \wealth\forms\ConvertRequestForm();

        } elseif ($formId == 'translate-payout-form') {
            $model = new \translate\forms\PayoutForm();

        } elseif ($formId == 'translate-payout-wallet-form') {
            $model = new \translate\forms\PayoutWalletForm();

        }   else {
            return false;
        }

        $sessionKey = 'confirmCode_' . $formId;

        $model->setUser(Yii::$app->user->identity);
        $model->generateConfirmCode($sessionKey);

        $timer = Yii::$app->session->get($sessionKey);
        $timer = $timer + AbstractFinanceConfirmForm::CONFIRM_TIMER - time();

        return $this->renderAjax('@common/views/finance-confirm/_confirm-code', [
            'model' => $model,
            'timer' => $timer,
        ]);
    }
}
