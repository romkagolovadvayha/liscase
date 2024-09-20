<?php

namespace common\components\telegram\foreignSystem;

use common\components\telegram\TelegramApiHelper;
use Yii;
use yii\helpers\ArrayHelper;

abstract class AbstractSystem
{
    /**
     * @return TelegramApiHelper
     */
    abstract public function getTelegramBot();

    /**
     * @param array $message
     *
     * @return string
     */
    public function executeCommand($message)
    {
        try {
            $messageText = $this->_getMessageText($message);

            $answerMessage = null;
            switch (true) {
                case strpos($messageText, '/start') === 0 :
                    $chat = ArrayHelper::getValue($message, 'chat');

                    $name = '';
                    $lastName  = ArrayHelper::getValue($chat, 'last_name');
                    $firstName = ArrayHelper::getValue($chat, 'first_name');
                    if (!empty($firstName) && !empty($lastName)) {
                        $name = ', ' . trim($lastName . ' ' . $firstName);
                    }

                    $answerMessage = $this->_getStartMessageText($name);

                    break;

                default :
                    $answerMessage = $this->executeInnerCommand($message);

                    break;
            }

        } catch (\Exception $e) {
            $answerMessage = 'Что-то пошло не так!😱 Обратитесь в тех.поддержку.';
        }

        if (empty($answerMessage)) {
            return 'Введенная команда не найдена 😏';
        }

        return $answerMessage;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    abstract protected function _getStartMessageText($name);

    /**
     * @param array $message
     *
     * @return null|string
     */
    public function executeInnerCommand($message)
    {
        return 'Введенная команда не найдена 😏';
    }

    /**
     * @return string
     */
    abstract public function getSystemName();

    /**
     * @return int
     */
    abstract public function getSystemId();

    /**
     * @param int    $chatId
     * @param string $buttonValue
     *
     * @return string|null
     */
    public function executeCallBack($chatId, $buttonValue)
    {
        return null;
    }

    /**
     * @param $message
     *
     * @return string
     */
    protected function _getMessageText($message)
    {
        return trim(preg_replace('/^ +| +$|( ) +/m', '$1', ArrayHelper::getValue($message, 'text')));
    }
}