<?php

namespace frontend\modules\webhook\components;

use common\components\telegram\foreignSystem\AbstractSystemBots;
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
        if (empty($system) || (!($system instanceof AbstractSystem) && !($system instanceof AbstractSystemBots))) {
            return false;
        }

        /** @var TelegramApiHelper $bot */
        $bot = $system->getTelegramBot();
        if ($token != $system->getTelegramToken()) {
            return false;
        }

        $data        = file_get_contents('php://input');
        $inputParams = Json::decode($data);

        $callBack = ArrayHelper::getValue($inputParams, 'callback_query', []);

        if (!empty($callBack)) {
            Yii::$app->telegramChats->sendMessage(json_encode($callBack));
            $callbackData = ArrayHelper::getValue($callBack, 'callback_data');
            if (!empty($callbackData)) {
                $action = ArrayHelper::getValue($callbackData, 'action');
                if (!empty($action)) {
                    Yii::$app->telegramChats->sendMessage('test: ' . $action);
                    return '✅ Игрок успешно замучен!';
                }
            }
            $buttonValue = ArrayHelper::getValue($callBack, 'data');
            $message     = ArrayHelper::getValue($callBack, 'message');
            $chat        = ArrayHelper::getValue($message, 'chat');
            $textMessage = ArrayHelper::getValue($message, 'text');
            if (empty($buttonValue) || empty($chat) || empty($textMessage)) {
                return false;
            }

            $answerMessage = $system->executeCallBack($chat['id'], $buttonValue);

            if (!empty($answerMessage['message'])) {
                $bot->sendMessage($chat['id'], $answerMessage['message'], $answerMessage['buttons']);
            } elseif (!empty($answerMessage)) {
                $textMessage .= "\n\n" . $answerMessage;
                $bot->editMessageText($chat['id'], $message['message_id'], $textMessage);
            }
        } else {
            $message = ArrayHelper::getValue($inputParams, 'message');
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