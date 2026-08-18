<?php

declare(strict_types=1);

namespace consik\yii2websocket\events;

use yii\base\Event;

class WSClientEvent extends Event
{
    public $client;
}
