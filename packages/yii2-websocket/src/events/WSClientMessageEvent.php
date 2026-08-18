<?php

declare(strict_types=1);

namespace consik\yii2websocket\events;

class WSClientMessageEvent extends WSClientEvent
{
    public $message;
}
