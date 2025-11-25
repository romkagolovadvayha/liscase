<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var \common\models\tasks_v2\TaskV2 $model */
/** @var \common\models\user\User $user */

// ListView передает модель как $model
$task = $model ?? $task ?? null;
if (!$task) {
    return;
}

// Получаем статус задания для пользователя
$userStatus = $task->getUserStatus($user);

// Для ежедневных наград добавляем информацию о текущей награде
if ($task->type === \common\models\tasks_v2\TaskV2::TYPE_DAILY_REWARD && $task->check_type === \common\models\tasks_v2\TaskV2::CHECK_TYPE_DAILY_REWARD) {
    $userStatus['currentReward'] = $task->getCurrentDailyReward($user);
}

$status = $userStatus['status'] ?? 'available';
$statusMessage = $userStatus['message'] ?? '';
$isCompleted = $status === 'completed';

// Определяем классы для карточки в зависимости от статуса
$cardClasses = ['tasksV2__card'];
if ($isCompleted) {
    $cardClasses[] = 'is-completed';
} elseif ($status === 'limit_reached') {
    $cardClasses[] = 'is-limit-reached';
} elseif ($status === 'unavailable') {
    $cardClasses[] = 'is-unavailable';
}

// Определяем тип награды для отображения
$rewardDisplay = '';
if ($task->type === \common\models\tasks_v2\TaskV2::TYPE_DAILY_REWARD && $task->check_type === \common\models\tasks_v2\TaskV2::CHECK_TYPE_DAILY_REWARD) {
    // Для ежедневных наград показываем текущую/следующую награду
    $currentReward = $userStatus['currentReward'] ?? null;
    if ($currentReward) {
        if (isset($currentReward['drop'])) {
            $itemImage = $currentReward['image'] ?? '';
            $itemName = $currentReward['name'] ?? '';
            $itemCount = ($currentReward['amount'] ?? 1) > 1 ? ' x' . (int)$currentReward['amount'] : '';
            $rewardDisplay = '<img src="' . Html::encode($itemImage) . '" alt="' . Html::encode($itemName) . '" class="tasksV2__card-reward-image"> <span class="tasksV2__card-reward-item-name">' . Html::encode($itemName) . '</span>' . ($itemCount ? '<span class="tasksV2__card-reward-item-count">' . $itemCount . '</span>' : '');
        } elseif (isset($currentReward['currency']) || (isset($currentReward['reward']['drop_id']) && (int)($currentReward['reward']['drop_id'] ?? 0) == 843)) {
            // Получаем amount из разных возможных мест
            $amount = 0;
            if (isset($currentReward['amount']) && $currentReward['amount'] > 0) {
                $amount = (int)$currentReward['amount'];
            } elseif (isset($currentReward['reward']['amount']) && $currentReward['reward']['amount'] > 0) {
                $amount = (int)$currentReward['reward']['amount'];
            }
            if ($amount > 0) {
                $rewardDisplay = '<span class="tasksV2__coin-icon" style="background: var(--icon-money) no-repeat center; background-size: contain; display: inline-block; width: 16px; height: 16px; vertical-align: middle;"></span> +' . number_format($amount, 0, '.', ' ') . ' ' . Yii::t('common', 'монет');
            }
        }
    }
} elseif ($task->reward_type === \common\models\tasks_v2\TaskV2::REWARD_TYPE_CURRENCY) {
    $rewardDisplay = '<span class="tasksV2__coin-icon" style="background: var(--icon-money) no-repeat center; background-size: contain; display: inline-block; width: 16px; height: 16px; vertical-align: middle;"></span> +' . number_format($task->reward_amount, 0, '.', ' ') . ' ' . Yii::t('common', 'монет');
} elseif ($task->reward_type === \common\models\tasks_v2\TaskV2::REWARD_TYPE_ITEM && $task->rewardItem) {
    $itemImage = $task->rewardItem->imageOrig ? $task->rewardItem->imageOrig->getImagePubUrl() : '';
    $itemName = Yii::t('database', $task->rewardItem->name ?? '');
    $itemCount = $task->reward_amount > 1 ? ' x' . (int)$task->reward_amount : '';
    $rewardDisplay = '<img src="' . Html::encode($itemImage) . '" alt="' . Html::encode($itemName) . '" class="tasksV2__card-reward-image"> <span class="tasksV2__card-reward-item-name">' . Html::encode($itemName) . '</span>' . ($itemCount ? '<span class="tasksV2__card-reward-item-count">' . $itemCount . '</span>' : '');
}

$imageUrl = $task->image_path ? '/' . ltrim($task->image_path, '/') : '/images/design/icons/128px/task-default.png';

// Определяем размер модального окна: для ежедневной награды - modal-lg, для остальных - modal
$isDailyReward = $task->type === \common\models\tasks_v2\TaskV2::TYPE_DAILY_REWARD && $task->check_type === \common\models\tasks_v2\TaskV2::CHECK_TYPE_DAILY_REWARD;
$modalSize = $isDailyReward ? 'modal-lg' : 'modal';

    ?>
    <article class="<?= implode(' ', $cardClasses) ?><?= !$isCompleted ? ' show-modal-link' : '' ?>" 
             data-task-id="<?= $task->id ?>"
             data-top-class="active"
             data-content-overflow="unset"
             data-top-image="<?= Html::encode($imageUrl) ?>"
             <?php if (!$isCompleted): ?>
             data-href="<?= Url::to(['tasks-v2/detail', 'id' => $task->id]) ?>"
             data-target="modal-dialog"
             data-size="<?= $modalSize ?>"
             data-pjax="0"
             aria-label="<?= Yii::t('common', 'Открыть подробную информацию') ?>"
             <?php endif; ?>>
    
    <div class="tasksV2__card-content">
        <div class="tasksV2__card-image-wrapper">
            <div class="tasksV2__card-badges">
                <?php if ($task->type === \common\models\tasks_v2\TaskV2::TYPE_REPEATABLE): ?>
                    <span class="tasksV2__card-badge tasksV2__card-badge--type" title="<?= Yii::t('common', 'Многоразовое') ?>">
                        <i class="fas fa-redo"></i>
                    </span>
                <?php endif; ?>
                <?php 
                // Таймер для ежедневных наград (показываем только если не available)
                if ($isDailyReward && $status !== 'available'): 
                    // Вычисляем время следующей полночи
                    $nextMidnight = new \DateTime('tomorrow');
                    $nextMidnight->setTime(0, 0, 0);
                    $nextMidnightTimestamp = $nextMidnight->getTimestamp();
                ?>
                    <span class="tasksV2__card-badge tasksV2__card-badge--timer" 
                          data-next-reset="<?= $nextMidnightTimestamp ?>"
                          title="<?= Yii::t('common', 'До следующей награды') ?>">
                        <i class="fas fa-clock"></i>
                        <span class="tasksV2__card-timer-text"></span>
                    </span>
                <?php endif; ?>
                <?php if ($status === 'completed'): ?>
                    <span class="tasksV2__card-badge tasksV2__card-badge--status is-completed" title="<?= Yii::t('common', 'Выполнено') ?>">
                        <i class="fas fa-check"></i>
                    </span>
                <?php elseif ($status === 'limit_reached'): ?>
                    <span class="tasksV2__card-badge tasksV2__card-badge--status is-limit-reached">
                        <i class="fas fa-ban"></i> <?= Yii::t('common', 'Лимит исчерпан') ?>
                    </span>
                <?php elseif ($status === 'unavailable'): ?>
                    <span class="tasksV2__card-badge tasksV2__card-badge--status is-unavailable">
                        <i class="fas fa-lock"></i> <?= Yii::t('common', 'Недоступно') ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="tasksV2__card-image">
                <img src="<?= Html::encode($imageUrl) ?>" alt="<?= Html::encode($task->title) ?>" loading="lazy">
            </div>
        </div>
        
        <div class="tasksV2__card-body">
            <h3 class="tasksV2__card-title">
                <?= Html::encode($task->title) ?>
            </h3>
            
            <?php if ($task->short_description): ?>
                <div class="tasksV2__card-description">
                    <p><?= Html::encode($task->short_description) ?></p>
                </div>
            <?php endif; ?>
            
            <div class="tasksV2__card-footer">
                <div class="tasksV2__card-reward">
                    <span class="tasksV2__card-reward-label"><?= Yii::t('common', 'Награда') ?>:</span>
                    <span class="tasksV2__card-reward-value"><?= $rewardDisplay ?></span>
                </div>
                
                <div class="tasksV2__card-meta">
                    <span class="tasksV2__card-meta-item">
                        <i class="fas fa-users"></i>
                        <?= Yii::t('common', 'Выполнили: {count} игроков', ['count' => $task->global_completed]) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</article>

