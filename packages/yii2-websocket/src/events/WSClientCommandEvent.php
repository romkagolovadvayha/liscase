<?php

declare(strict_types=1);

namespace consik\yii2websocket\events;

class WSClientCommandEvent extends WSClientEvent
{
    public $command;
    public $result;
}
