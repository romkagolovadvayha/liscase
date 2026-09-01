<?php

namespace common\components\queue\telegram;

use backend\models\TelegramConstructor;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

/**
 * Job для отправки рассылки через Telegram Constructor
 */
class TelegramConstructorSendJob extends BaseObject implements JobInterface
{
    public $constructorId;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            /** @var TelegramConstructor $model */
            $model = TelegramConstructor::findOne($this->constructorId);
            
            if (empty($model)) {
                Yii::error("TelegramConstructorSendJob: Constructor not found with id {$this->constructorId}", __METHOD__);
                return;
            }

            // Устанавливаем статус "В процессе"
            $model->status = TelegramConstructor::STATUS_IN_PROGRESS;
            $model->save(false);

            // Выполняем рассылку
            // Метод send() внутри уже использует очереди для отправки отдельных сообщений
            $result = $model->send();

            // Обновляем статус в зависимости от результата
            if ($result) {
                $model->status = TelegramConstructor::STATUS_SUCCESS;
            } else {
                $model->status = TelegramConstructor::STATUS_ERROR;
            }
            
            $model->save(false);
            
            Yii::info("TelegramConstructorSendJob: Completed for constructor id {$this->constructorId}, status: {$model->status}", __METHOD__);
        } catch (\Throwable $e) {
            Yii::error("TelegramConstructorSendJob: Exception for constructor id {$this->constructorId} - " . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            
            // Обновляем статус на ошибку
            try {
                $model = TelegramConstructor::findOne($this->constructorId);
                if ($model) {
                    $model->status = TelegramConstructor::STATUS_ERROR;
                    $model->save(false);
                }
            } catch (\Throwable $saveException) {
                Yii::error("TelegramConstructorSendJob: Failed to save error status - " . $saveException->getMessage(), __METHOD__);
            }
        }
    }
}

