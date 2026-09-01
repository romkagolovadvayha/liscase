<?php

namespace common\components\queue\process;

use common\components\openAi\OpenAiSettings;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class QuizGetListJob extends BaseObject implements JobInterface
{
    public $count;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        if (!OpenAiSettings::isEnabled(OpenAiSettings::QUIZ)) {
            return;
        }

        try {
            $cacheKey = 'quiz_list';
            $questions = Yii::$app->openAiQuiz->questions($this->count);
            Yii::$app->cache->set($cacheKey, $questions, 24 * 60 * 60);
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('TranslateJob: ' . PHP_EOL . $ex->getFile() . ": " . $ex->getLine() . PHP_EOL . $ex->getMessage());
        }
    }
}
