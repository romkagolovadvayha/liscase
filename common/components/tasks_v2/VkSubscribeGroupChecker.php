<?php

namespace common\components\tasks_v2;

use common\models\tasks_v2\TaskV2;
use common\models\user\User;
use common\models\user\UserConfirmCode;
use Yii;

/**
 * Проверка подписки на группу VK через уникальный код
 */
class VkSubscribeGroupChecker implements TaskCheckerInterface
{
    /**
     * {@inheritdoc}
     */
    public function check(TaskV2 $task, User $user): CheckResult
    {
        $params = $task->check_params ?? [];
        $groupId = $params['group_id'] ?? null;

        if (!$groupId) {
            return CheckResult::failure(
                Yii::t('common', 'Настройки задания неверны: не указан ID группы VK.')
            );
        }

        // Проверяем, есть ли у пользователя код для привязки VK
        $vkCode = UserConfirmCode::find()
            ->andWhere(['user_id' => $user->id])
            ->andWhere(['type' => UserConfirmCode::TYPE_VK_GROUP])
            ->one();

        // Если кода нет или он неактивен, создаем новый
        if (empty($vkCode) || $vkCode->status != UserConfirmCode::STATUS_ACTIVE) {
            $vkCode = UserConfirmCode::createTypeVkGroup($user->id);
        }

        if (empty($vkCode)) {
            return CheckResult::failure(
                Yii::t('common', 'Ошибка при создании кода для привязки VK. Попробуйте позже.')
            );
        }

        // Проверяем, был ли код использован (status = 0 означает, что код был использован)
        if ($vkCode->status == UserConfirmCode::STATUS_DISABLED) {
            // Код был использован, задание выполнено
            return CheckResult::success(
                Yii::t('common', 'Вы успешно подписались на группу ВКонтакте!')
            );
        }

        // Код еще не использован, показываем инструкцию
        $groupUrl = "https://vk.com/club{$groupId}";
        $message = Yii::t('common', 'Для выполнения задания:') . "\n";
        $message .= "1. " . Yii::t('common', 'Перейдите в группу ВКонтакте: {url}', ['url' => $groupUrl]) . "\n";
        $message .= "2. " . Yii::t('common', 'Откройте личные сообщения группы') . "\n";
        $message .= "3. " . Yii::t('common', 'Отправьте следующий код:') . "\n\n";
        $message .= "<strong style='font-size: 18px; letter-spacing: 2px;'>{$vkCode->code}</strong>\n\n";
        $message .= Yii::t('common', 'После отправки кода задание будет автоматически выполнено.');

        return CheckResult::failure($message);
    }
}











