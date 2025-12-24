<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var \common\models\tasks_v2\TaskV2 $task */
/** @var array $userStatus */
/** @var int|null $progress */
/** @var int|null $maxProgress */
/** @var array|null $dailyRewardList */
/** @var \common\models\user\UserConfirmCode|null $vkCode */
/** @var int|null $vkGroupId */

$status = $userStatus['status'] ?? 'available';
$canCheck = $status === 'available';
$imageUrl = $task->getImageUrl();

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
            $rewardDisplay = '<img src="' . Html::encode($itemImage) . '" alt="' . Html::encode($itemName) . '" class="tasksV2__detail-reward-image"> <strong>' . Html::encode($itemName) . $itemCount . '</strong>';
        } elseif (isset($currentReward['currency']) || (isset($currentReward['reward']['drop_id']) && (int)($currentReward['reward']['drop_id'] ?? 0) == 843)) {
            // Получаем amount из разных возможных мест
            $amount = 0;
            if (isset($currentReward['amount']) && $currentReward['amount'] > 0) {
                $amount = (int)$currentReward['amount'];
            } elseif (isset($currentReward['reward']['amount']) && $currentReward['reward']['amount'] > 0) {
                $amount = (int)$currentReward['reward']['amount'];
            }
            if ($amount > 0) {
                $rewardDisplay = '<span class="tasksV2__coin-icon" style="background: var(--icon-money) no-repeat center; background-size: contain; display: inline-block; width: 16px; height: 16px; vertical-align: middle;"></span> <strong>' . number_format($amount, 0, '.', ' ') . ' ' . Yii::t('common', 'монет') . '</strong>';
            }
        }
    }
} elseif ($task->reward_type === \common\models\tasks_v2\TaskV2::REWARD_TYPE_CURRENCY) {
    $rewardDisplay = '<span class="tasksV2__coin-icon" style="background: var(--icon-money) no-repeat center; background-size: contain; display: inline-block; width: 16px; height: 16px; vertical-align: middle;"></span> <strong>' . number_format($task->reward_amount, 0, '.', ' ') . ' ' . Yii::t('common', 'монет') . '</strong>';
} elseif ($task->reward_type === \common\models\tasks_v2\TaskV2::REWARD_TYPE_ITEM && $task->rewardItem) {
    $itemImage = $task->rewardItem->imageOrig ? $task->rewardItem->imageOrig->getImagePubUrl() : '';
    $itemName = Yii::t('database', $task->rewardItem->name ?? '');
    $itemCount = $task->reward_amount > 1 ? ' x' . (int)$task->reward_amount : '';
    $rewardDisplay = '<img src="' . Html::encode($itemImage) . '" alt="' . Html::encode($itemName) . '" class="tasksV2__detail-reward-image"> <strong>' . Html::encode($itemName) . $itemCount . '</strong>';
}

?>
<section class="tasksV2__detail" data-role="task-detail" data-task-id="<?= $task->id ?>">
    <div class="tasksV2__detail-header">
        <div class="tasksV2__detail-image">
            <img src="<?= Html::encode($imageUrl) ?>" alt="<?= Html::encode($task->title) ?>">
        </div>
        <h2 class="tasksV2__detail-title"><?= Html::encode($task->title) ?></h2>
        <?php if ($task->is_vip_only): ?>
            <div class="tasksV2__detail-vip-badge" style="margin-top: 12px;">
                <span class="tasksV2__card-badge tasksV2__card-badge--vip" style="display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fas fa-crown"></i> 
                    <span><?= Yii::t('common', 'Доступно только для VIP') ?></span>
                </span>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($task->is_vip_only && $status === 'unavailable' && $userStatus['message'] === Yii::t('common', 'Требуется VIP статус')): ?>
        <div class="tasksV2__detail-vip-message" style="background: var(--bg-secondary); padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid var(--primary);">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                <i class="fas fa-crown" style="color: var(--primary); font-size: 24px;"></i>
                <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: var(--primary);">
                    <?= Yii::t('common', 'Требуется VIP статус') ?>
                </h3>
            </div>
            <p style="margin: 0; color: var(--text-secondary); line-height: 1.6;">
                <?= Yii::t('common', 'Это задание доступно только для пользователей со статусом VIP. Получите VIP статус, чтобы получить доступ к эксклюзивным заданиям и наградам.') ?>
            </p>
        </div>
    <?php elseif (!empty($userStatus['available_from']) && $status === 'unavailable'): ?>
        <div class="tasksV2__detail-available-from-message" style="background: var(--bg-secondary); padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid var(--warning, #ffc107);">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                <i class="fas fa-clock" style="color: var(--warning, #ffc107); font-size: 24px;"></i>
                <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: var(--warning, #ffc107);">
                    <?= Yii::t('common', 'Задание станет доступно') ?>
                </h3>
            </div>
            <p style="margin: 0; color: var(--text-secondary); line-height: 1.6;">
                <?= Html::encode($userStatus['message']) ?>
            </p>
        </div>
    <?php endif; ?>
    
    <?php if ($task->full_description): ?>
        <div class="tasksV2__detail-description">
            <div class="tasksV2__detail-description-content"><?= trim(nl2br(Html::encode($task->full_description))) ?></div>
        </div>
    <?php endif; ?>
    
    <?php if ($task->check_type === \common\models\tasks_v2\TaskV2::CHECK_TYPE_VK_SUBSCRIBE_GROUP && $vkCode && $vkGroupId): ?>
        <div class="tasksV2__detail-vk-instruction" style="background: var(--bg-secondary); padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3 style="margin-top: 0; margin-bottom: 16px; font-size: 18px; font-weight: 600;">
                <?= Yii::t('common', 'Инструкция по выполнению задания') ?>
            </h3>
            <ol style="margin: 0; padding-left: 20px; line-height: 1.8;">
                <li>
                    <?= Yii::t('common', 'Перейдите в группу ВКонтакте:') ?>
                    <a href="https://vk.com/club<?= $vkGroupId ?>" target="_blank" rel="nofollow noopener" style="color: var(--primary); text-decoration: underline;">
                        https://vk.com/club<?= $vkGroupId ?>
                    </a>
                </li>
                <li><?= Yii::t('common', 'Откройте личные сообщения группы') ?></li>
                <li>
                    <?= Yii::t('common', 'Отправьте следующий код:') ?>
                    <div style="background: var(--bg-primary); padding: 12px; border-radius: 6px; margin-top: 8px; text-align: center;">
                        <strong style="font-size: 20px; letter-spacing: 3px; font-family: monospace; color: var(--primary);">
                            <?= Html::encode($vkCode->code) ?>
                        </strong>
                    </div>
                </li>
                <li><?= Yii::t('common', 'После отправки кода нажмите кнопку "Проверить" для подтверждения выполнения задания') ?></li>
            </ol>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($task->extra_buttons) && is_array($task->extra_buttons)): ?>
        <div class="tasksV2__detail-external-links">
            <div class="tasksV2__detail-external-links-list">
                <?php foreach ($task->extra_buttons as $button): ?>
                    <?php if (!empty($button['label']) && !empty($button['url'])): ?>
                        <a href="<?= Html::encode($button['url']) ?>"
                           target="_blank"
                           rel="nofollow noopener"
                           class="tasksV2__detail-external-link">
                            <?= Html::encode($button['label']) ?>
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php 
    // Показываем прогресс для многоразовых заданий или одноразовых с типом проверки "параметр статистики"
    $isRepeatable = $task->type === \common\models\tasks_v2\TaskV2::TYPE_REPEATABLE;
    $isOneTimeWithStats = $task->type === \common\models\tasks_v2\TaskV2::TYPE_ONE_TIME && 
                          $task->check_type === 'statistics_param';
    // Используем max_progress из БД, если задан, иначе из контроллера
    $maxProgressValue = $task->max_progress ?? $maxProgress;
    $hasProgress = ($isRepeatable || $isOneTimeWithStats) && 
                   $progress !== null && $maxProgressValue !== null && $maxProgressValue > 0;
    if ($hasProgress): ?>
        <div class="tasksV2__detail-progress">
            <div class="tasksV2__detail-progress-header">
                <span class="tasksV2__detail-progress-label"><?= Yii::t('common', 'Выполнено') ?></span>
                <span class="tasksV2__detail-progress-text">
                    <?= $progress ?> / <?= $maxProgressValue ?>
                </span>
            </div>
            <?php if ($maxProgressValue > 0): ?>
                <div class="tasksV2__detail-progress-bar">
                    <div class="tasksV2__detail-progress-bar-fill" style="width: <?= min(100, ($progress / $maxProgressValue) * 100) ?>%"></div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($task->type === \common\models\tasks_v2\TaskV2::TYPE_DAILY_REWARD && $task->check_type === \common\models\tasks_v2\TaskV2::CHECK_TYPE_DAILY_REWARD && $dailyRewardList && !empty($dailyRewardList['items'])): ?>
        <div class="tasksV2__detail-daily-rewards">
            <div class="page-stats__block-without-hover">
                <div class="page-stats__categories">
                    <?php foreach ($dailyRewardList['items'] as $item): ?>
                        <div class="page-stats__category category<?php if (!empty($item['status'])): ?> <?= $item['status'] ?><?php endif; ?>">
                            <h5 class="category__count-and-img">
                                <?php 
                                $isCurrency = isset($item['currency']) || (isset($item['reward']['drop_id']) && (int)($item['reward']['drop_id'] ?? 0) == 843);
                                $amount = $item['amount'] ?? ($item['reward']['amount'] ?? ($isCurrency ? 0 : 1));
                                ?>
                                <span><?= $isCurrency ? '+' : 'x' ?><?= number_format((int)$amount, 0, '.', ' ') ?></span>
                                <?php if (isset($item['drop']) && !empty($item['image'])): ?>
                                    <img src="<?= Html::encode($item['image']) ?>" alt="<?= Html::encode($item['name'] ?? '') ?>" class="w-64 h-64 object-contain">
                                <?php elseif ($isCurrency): ?>
                                    <span class="tasksV2__coin-icon" style="background: var(--icon-money) no-repeat center; background-size: contain; display: inline-block; width: 64px; height: 64px;"></span>
                                <?php endif; ?>
                            </h5>
                            <p class="category__title"><?= Html::encode($item['name'] ?? Yii::t('common', 'Монеты')) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="tasksV2__detail-reward">
            <h3><?= Yii::t('common', 'Награда за выполнение') ?></h3>
            <div class="tasksV2__detail-reward-content">
                <?= $rewardDisplay ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="tasksV2__detail-actions">
        <?php if ($canCheck): ?>
            <button type="button"
                    class="tasksV2__detail-check-button"
                    data-task-id="<?= $task->id ?>"
                    data-action="check-task">
                <i class="fas fa-check-circle"></i>
                <span><?= Html::encode($task->button_text ?: Yii::t('common', 'Проверить')) ?></span>
            </button>
        <?php else: ?>
            <button type="button"
                    class="tasksV2__detail-check-button is-disabled"
                    disabled>
                <i class="fas fa-ban"></i>
                <span><?= Html::encode($userStatus['message'] ?? Yii::t('common', 'Недоступно')) ?></span>
            </button>
        <?php endif; ?>
    </div>
    
    <div class="tasksV2__detail-message" data-role="task-message" style="display: none;"></div>
</section>

