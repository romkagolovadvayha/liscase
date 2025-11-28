<?php

namespace common\components\tasks_v2;

use common\models\tasks_v2\TaskV2;
use common\models\user\User;

/**
 * Интерфейс для проверки выполнения заданий
 */
interface TaskCheckerInterface
{
    /**
     * Проверить выполнение задания пользователем
     * @param TaskV2 $task Задание
     * @param User $user Пользователь
     * @return CheckResult Результат проверки
     */
    public function check(TaskV2 $task, User $user): CheckResult;
}













