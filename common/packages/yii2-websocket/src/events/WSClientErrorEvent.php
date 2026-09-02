<?php

declare(strict_types=1);

namespace consik\yii2websocket\events;

class WSClientErrorEvent extends WSClientEvent
{
    public $exception;
}
