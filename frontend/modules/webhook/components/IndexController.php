<?php

namespace frontend\modules\webhook\components;

use Yii;
use yii\web\Controller;
use yii\helpers\Json;
use yii\helpers\ArrayHelper;
use common\components\telegram\TelegramApiHelper;
use common\components\telegram\foreignSystem\AbstractSystem;

abstract class IndexController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * @return AbstractSystem
     */
    abstract protected function _getSystem();

    public function actionIndex($token)
    {
        $system = $this->_getSystem();
        if (empty($system) || !($system instanceof AbstractSystem)) {
            return false;
        }

        /** @var TelegramApiHelper $bot */
        $bot = $system->getTelegramBot();
        if ($token != $bot->botToken) {
            return false;
        }

        $data        = file_get_contents('php://input');
        $callBack = Json::decode($data);

        if (!empty($callBack)) {
            $buttonValue = ArrayHelper::getValue($callBack, 'data') ?? [];
            $message     = ArrayHelper::getValue($callBack, 'message');
            $chat        = ArrayHelper::getValue($message, 'chat');
            $textMessage = ArrayHelper::getValue($message, 'text');

            if (empty($chat) || empty($textMessage)) {
                return false;
            }

            $answerMessage = $system->executeCallBack($chat['id'], $buttonValue);

            if (!empty($answerMessage['message'])) {
                $bot->sendMessage($chat['id'], $answerMessage['message'], $answerMessage['buttons']);
            } elseif (!empty($answerMessage)) {
                $bot->sendMessage($chat['id'], $answerMessage);
                Yii::error('error', "id: " . $chat['id']);
                Yii::error('error', "answerMessage: " . $answerMessage);
            }
        } else {
            $message = ArrayHelper::getValue($callBack, 'message');
            $chat    = ArrayHelper::getValue($message, 'chat');
            if (empty($chat)) {
                return false;
            }

            $answerMessage = $system->executeCommand($message);
            if (!empty($answerMessage['message'])) {
                $bot->sendMessage($chat['id'], $answerMessage['message'], $answerMessage['buttons']);
            } elseif (!empty($answerMessage)) {
                $bot->sendMessage($chat['id'], $answerMessage);
            }
        }

        return true;
    }
}