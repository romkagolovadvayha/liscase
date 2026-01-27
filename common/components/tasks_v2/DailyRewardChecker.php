<?php

namespace common\components\tasks_v2;

use common\models\tasks_v2\TaskV2;
use common\models\tasks_v2\TaskV2UserCompletion;
use common\models\user\User;
use Yii;

/**
 * Проверка ежедневных наград
 * Логика: каждый день пользователь получает следующую награду из списка
 * Если пропущен день - сброс на первую награду
 * Если дошли до последней - сброс на первую
 */
class DailyRewardChecker implements TaskCheckerInterface
{
    /**
     * {@inheritdoc}
     */
    public function check(TaskV2 $task, User $user): CheckResult
    {
        // Получаем список наград из check_params
        $rewards = $this->getRewardsList($task);
        if (empty($rewards)) {
            return CheckResult::failure(
                Yii::t('common', 'Список наград не настроен')
            );
        }

        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $todayStr = $today->format('Y-m-d');

        // Получаем запись о выполнении из TaskV2UserCompletion
        $completion = TaskV2UserCompletion::find()
            ->where(['task_id' => $task->id, 'user_id' => $user->id])
            ->one();

        // Если сегодня уже получили награду
        if ($completion && $completion->last_completed) {
            $lastCompletedDate = new \DateTime($completion->last_completed);
            $lastCompletedDate->setTime(0, 0, 0);
            $lastCompletedDateStr = $lastCompletedDate->format('Y-m-d');

            if ($lastCompletedDateStr === $todayStr) {
                return CheckResult::failure(
                    Yii::t('common', 'Вы уже получили награду сегодня. Возвращайтесь завтра!')
                );
            }
        }

        // Определяем текущий индекс награды
        $currentIndex = $this->getCurrentRewardIndex($task, $user, $rewards, $completion, $today);

        // Если дошли до последней награды - сброс на первую
        if ($currentIndex >= count($rewards)) {
            $currentIndex = 0;
        }

        // Возвращаем успех - награда будет выдана в контроллере
        return CheckResult::success(
            Yii::t('common', 'Награда готова к получению!'),
            $currentIndex + 1, // progress (текущий день)
            count($rewards) // maxProgress (всего дней)
        );
    }

    /**
     * Получить список наград из check_params
     * @param TaskV2 $task
     * @return array
     */
    protected function getRewardsList(TaskV2 $task)
    {
        if (empty($task->check_params)) {
            return [];
        }

        // check_params может быть уже массивом или JSON-строкой
        if (is_array($task->check_params)) {
            $params = $task->check_params;
        } else {
            $params = json_decode($task->check_params, true);
        }
        
        if (!is_array($params) || empty($params['rewards'])) {
            return [];
        }

        return $params['rewards'];
    }

    /**
     * Получить текущий индекс награды
     * @param TaskV2 $task
     * @param User $user
     * @param array $rewards
     * @param TaskV2UserCompletion|null $completion
     * @param \DateTime $today
     * @return int
     */
    protected function getCurrentRewardIndex(TaskV2 $task, User $user, array $rewards, $completion, \DateTime $today)
    {
        if (!$completion || !$completion->last_completed) {
            // Первая награда
            return 0;
        }

        $lastCompletedDate = new \DateTime($completion->last_completed);
        $lastCompletedDate->setTime(0, 0, 0);
        $lastCompletedDateStr = $lastCompletedDate->format('Y-m-d');

        // Проверяем, была ли последняя награда вчера
        $yesterday = clone $today;
        $yesterday->modify('-1 day');
        $yesterdayStr = $yesterday->format('Y-m-d');

        if ($lastCompletedDateStr === $yesterdayStr) {
            // Последовательность продолжается - следующая награда
            // count_completed уже включает все предыдущие выполнения
            // Если выполнил 2 раза, то сегодня получит награду с индексом 2 (третий день)
            return ($completion->count_completed) % count($rewards);
        } else {
            // Пропущен день - сброс на первую награду
            return 0;
        }
    }
}

