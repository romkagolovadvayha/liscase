<?php

namespace common\components\tasks_v2;

/**
 * Результат проверки задания
 */
class CheckResult
{
    /** @var bool Успешно ли выполнено задание */
    public $success;

    /** @var string Сообщение для пользователя */
    public $message;

    /** @var int|null Текущий прогресс (если применимо) */
    public $progress;

    /** @var int|null Максимальный прогресс (если применимо) */
    public $maxProgress;

    /**
     * @param bool $success
     * @param string $message
     * @param int|null $progress
     * @param int|null $maxProgress
     */
    public function __construct($success, $message, $progress = null, $maxProgress = null)
    {
        $this->success = $success;
        $this->message = $message;
        $this->progress = $progress;
        $this->maxProgress = $maxProgress;
    }

    /**
     * Создать успешный результат
     * @param string $message
     * @param int|null $progress
     * @param int|null $maxProgress
     * @return CheckResult
     */
    public static function success($message, $progress = null, $maxProgress = null)
    {
        return new self(true, $message, $progress, $maxProgress);
    }

    /**
     * Создать неуспешный результат
     * @param string $message
     * @param int|null $progress
     * @param int|null $maxProgress
     * @return CheckResult
     */
    public static function failure($message, $progress = null, $maxProgress = null)
    {
        return new self(false, $message, $progress, $maxProgress);
    }
}











