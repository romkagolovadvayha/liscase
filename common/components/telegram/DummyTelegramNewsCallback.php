<?php
namespace common\components\telegram;

use Yii;
use common\models\telegram\TelegramNews;

class DummyTelegramNewsCallback implements TelegramNewsCallbackInterface
{
    public function onNewTelegramNews(TelegramNews $news): void
    {
        Yii::info("New TelegramNews #{$news->id}", __METHOD__);
        // Здесь твоя логика обработки: правишь processed_text / processed_caption и т.п.
    }
}
