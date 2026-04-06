<?php

namespace frontend\modules\webhook\components;

use common\components\telegram\foreignSystem\AbstractSystemBots;
use Yii;
use yii\web\Controller;
use common\components\telegram\foreignSystem\AbstractSystem;
use common\components\telegram\TelegramWebhookProcessor;

abstract class IndexController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * @return AbstractSystem|AbstractSystemBots
     */
    abstract protected function _getSystem();

    public function actionIndex($token)
    {
        $system = $this->_getSystem();
        if (!TelegramWebhookProcessor::tokenMatches($system, (string) $token)) {
            return false;
        }

        $data = file_get_contents('php://input');
        TelegramWebhookProcessor::process($system, (string) $data);

        return true;
    }
}