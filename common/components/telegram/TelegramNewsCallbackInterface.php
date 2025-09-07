<?php
namespace common\components\telegram;

use common\models\telegram\TelegramNews;

interface TelegramNewsCallbackInterface
{
    /** Вызывается сразу после того, как запись с status=0 создана */
    public function onNewTelegramNews(TelegramNews $news): void;
}
