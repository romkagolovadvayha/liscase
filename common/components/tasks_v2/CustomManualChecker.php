<?php

namespace common\components\tasks_v2;

use common\models\tasks_v2\TaskV2;
use common\models\user\User;
use Yii;

/**
 * Ручная проверка задания (всегда успешна)
 * Используется для заданий, которые проверяются администратором вручную
 */
class CustomManualChecker implements TaskCheckerInterface
{
    /**
     * {@inheritdoc}
     */
    public function check(TaskV2 $task, User $user): CheckResult
    {
        // Для ручной проверки всегда возвращаем успех
        // Администратор должен вручную проверить выполнение и выдать награду
        return CheckResult::success(
            Yii::t('common', 'Задание выполнено!')
        );
    }
}











