<?php

namespace common\components\tasks_v2;

use common\models\building\Building;
use common\models\tasks_v2\TaskV2;
use common\models\user\User;
use Yii;
use yii\helpers\Json;

/**
 * Проверка добавления постройки на сайт
 */
class BuildingAddChecker implements TaskCheckerInterface
{
    /**
     * {@inheritdoc}
     */
    public function check(TaskV2 $task, User $user): CheckResult
    {
        $params = is_array($task->check_params) ? $task->check_params : Json::decode($task->check_params, true);
        
        $requiredCount = (int)($params['required_count'] ?? 1);
        $onlyActive = !empty($params['only_active']) && $params['only_active'] === true;

        if ($requiredCount <= 0) {
            return CheckResult::failure(
                Yii::t('common', 'Настройки задания неверны: не указано требуемое количество построек.')
            );
        }

        // Считаем постройки пользователя
        $query = Building::find()
            ->where(['user_id' => $user->id]);

        // Если нужно только одобренные постройки
        if ($onlyActive) {
            $query->andWhere(['status' => Building::STATUS_ACTIVE]);
        } else {
            // Засчитываем любые постройки (включая на модерации)
            $query->andWhere(['IN', 'status', [Building::STATUS_ACTIVE, Building::STATUS_WAIT]]);
        }

        $currentCount = $query->count();

        if ($currentCount >= $requiredCount) {
            return CheckResult::success(
                Yii::t('common', 'Задание выполнено! Добавлено построек: {current} из {required}', [
                    'current' => $currentCount,
                    'required' => $requiredCount,
                ]),
                $currentCount,
                $requiredCount
            );
        }

        $remaining = $requiredCount - $currentCount;
        return CheckResult::failure(
            Yii::t('common', 'Добавлено построек: {current} из {required}. Осталось: {remaining}', [
                'current' => $currentCount,
                'required' => $requiredCount,
                'remaining' => $remaining,
            ]),
            $currentCount,
            $requiredCount
        );
    }
}




























