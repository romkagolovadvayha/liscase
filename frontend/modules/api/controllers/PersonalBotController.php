<?php

namespace frontend\modules\api\controllers;

use common\components\telegram\foreignSystem\PersonalBotSystem;
use Yii;
use yii\helpers\ArrayHelper;
use frontend\modules\api\components\IndexController;
use common\models\user\User;

class PersonalBotController extends IndexController
{
    /**
     * @var PersonalBotSystem
     */
    protected $_system;

    public function init()
    {
        parent::init();

        $key = Yii::$app->request->post('key');
        if (empty($key) || $key != PersonalBotSystem::API_KEY) {
            $this->_answer(false);
        }

        $this->_setSystem();
    }

    protected function _setSystem()
    {
        $this->_system = new PersonalBotSystem();
    }

    public function actionSystemAction()
    {
        $this->_sendMessage();
    }

    public function actionCheckIsChatMember()
    {
        $userId = Yii::$app->request->post('userId');
        $chatId = Yii::$app->request->post('chatId');
        if (empty($userId) || empty($chatId)) {
            $this->_answer(false);
        }

        $user = User::findOne([
            'system_id'      => $this->_system->getSystemId(),
            'system_user_id' => $userId,
        ]);

        if (empty($user)) {
            $this->_answer(false);
        }

        $response = $this->_system->getTelegramBot()->getChatMember($chatId, $user->telegram_chat_id);
        $isSigned = ArrayHelper::getValue($response, 'ok');

        $this->_answer($isSigned);
    }

    public function actionGetChatId()
    {
        $userId = Yii::$app->request->post('userId');
        if (empty($userId)) {
            $this->_answer(false);
        }

        $user = User::findOne([
            'system_id'      => $this->_system->getSystemId(),
            'system_user_id' => $userId,
        ]);

        if (empty($user)) {
            $this->_answer(false);
        }

        $this->_answer($user->telegram_chat_id);
    }

    private function _sendMessage()
    {
        $userId  = Yii::$app->request->post('userId');
        $icon    = Yii::$app->request->post('icon');
        $title   = Yii::$app->request->post('title');
        $message = Yii::$app->request->post('message');
        $inlineKeyboard = Yii::$app->request->post('inline_keyboard') ?? null;
        if (empty($inlineKeyboard)) {
            $inlineKeyboard = null;
        }
        if (empty($userId)) {
            $this->_answer(false);
        }

        $user = User::findOne([
            'system_id'      => $this->_system->getSystemId(),
            'system_user_id' => $userId,
        ]);

        if (empty($user)) {
            $this->_answer(false);
        }

        $this->_system->getTelegramBot()
            ->sendMessage($user->telegram_chat_id,
                ($icon ? "{$icon} " : "") . ($title ? "<b>{$title}:</b>\r\n" : "") . $message, $inlineKeyboard);

        $this->_answer(true);
    }
}