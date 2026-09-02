<?php

declare(strict_types=1);

namespace consik\yii2websocket\events;

use yii\base\Event;

class ExceptionEvent extends Event
{
    public $exception;
}
