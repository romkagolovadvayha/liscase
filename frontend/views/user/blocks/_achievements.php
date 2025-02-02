<?php

use common\models\tasks\Task;

$user = Yii::$app->user->identity;
$dailyRewardList = Task::getDailyRewardList($user);
$userTasks = Task::getTasksByUser($user);
$received = true;
\frontend\assets\UserBoxAsset::register($this);

?>

<div class="achievements">
    <div class="achievements_buttons">
        <div id="achievements_daily_btn" class="achievements_buttons_btn achievements_buttons_btn_daily active">
            <?= Yii::t('common', 'Ежедневная награда'); ?>
        </div>
        <?php if (Yii::$app->settings->get('section_tasks')): ?>
            <div id="achievements_achievement_btn" class="achievements_buttons_btn achievements_buttons_btn_achievement">
                <?= Yii::t('common', 'Задания'); ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="achievements_daily_reward active" id="achievements_daily_body">
        <?= $this->render('daily.twig', [
            'ACHIEVMENTS_DAILY' => $dailyRewardList,
            'USER' => $user
        ]); ?>
    </div>
    <?php if (Yii::$app->settings->get('section_tasks')): ?>
        <div class="achievements_body" id="achievements_achievement_body">
            <?= $this->render('rewards.twig', [
                    'ITEMS' => $userTasks,
                    'USER' => $user
            ]); ?>
        </div>
    <?php endif; ?>
</div>