<?php

namespace common\components\tasks_v2;

use common\models\blog\Blog;
use common\models\comment\Comment;
use common\models\tasks_v2\TaskV2;
use common\models\user\User;
use Yii;
use yii\helpers\Json;

/**
 * Проверка количества комментариев в новостях
 */
class CommentsCountChecker implements TaskCheckerInterface
{
    /**
     * {@inheritdoc}
     */
    public function check(TaskV2 $task, User $user): CheckResult
    {
        $params = is_array($task->check_params) ? $task->check_params : Json::decode($task->check_params, true);
        
        $requiredCount = (int)($params['required_count'] ?? 3);

        if ($requiredCount <= 0) {
            return CheckResult::failure(
                Yii::t('common', 'Настройки задания неверны: не указано требуемое количество комментариев.')
            );
        }

        // Получаем хеш сущности для Blog
        $blogEntityHash = hash('crc32', Blog::class);
        
        // Считаем комментарии пользователя в новостях (blog)
        $currentCount = Comment::find()
            ->where(['createdBy' => $user->id])
            ->andWhere(['entity' => $blogEntityHash])
            ->andWhere(['status' => 1]) // Только активные комментарии
            ->count();

        if ($currentCount >= $requiredCount) {
            return CheckResult::success(
                Yii::t('common', 'Задание выполнено! Оставлено комментариев: {current} из {required}', [
                    'current' => $currentCount,
                    'required' => $requiredCount,
                ]),
                $currentCount,
                $requiredCount
            );
        }

        $remaining = $requiredCount - $currentCount;
        return CheckResult::failure(
            Yii::t('common', 'Оставлено комментариев: {current} из {required}. Осталось: {remaining}', [
                'current' => $currentCount,
                'required' => $requiredCount,
                'remaining' => $remaining,
            ]),
            $currentCount,
            $requiredCount
        );
    }
}






