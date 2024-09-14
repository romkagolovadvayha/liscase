<?php

use common\models\tasks\Task;

$user = Yii::$app->user->identity;
$dailyRewardList = Task::getDailyRewardList($user);
$received = true;
\frontend\assets\UserBoxAsset::register($this);

?>

<div class="achievements">
    <div class="achievements_buttons">
        <div id="achievements_daily_btn" class="achievements_buttons_btn achievements_buttons_btn_daily active">
            <?= Yii::t('common', 'Ежедневная награда'); ?>
        </div>
        <div id="achievements_achievement_btn" class="achievements_buttons_btn achievements_buttons_btn_achievement">
            <?= Yii::t('common', 'Задания'); ?>
        </div>
    </div>
    <div class="achievements_body" id="achievements_achievement_body">
        <div class="achievements_body_list">
            <div class="achievements_body_list_item_wrap">
                <div class="achievements_body_list_items">
                    <div class="achievements_body_list_items_item">
                        <div class="achievements_body_list_items_item_info">
                            <div class="achievements_body_list_items_item_info_stext"><?=Yii::t('common', 'За каждого приглашенного скин от 20 до 120 RUB')?></div>
                            <div title="Чистый чай на металлолом" class="achievements_body_list_items_item_info_simage">
                                <img src="/images/skindrops/skindrops.png">
                            </div>
                            <a href="/user/partner" class="achievements_body_list_items_item_info_btn">
                                <?=Yii::t('common', 'Подробнее')?>
                            </a>
                        </div>
                    </div>
                    <div class="achievements_body_list_items_item">
                        <div class="achievements_body_list_items_item_info">
                            <div class="achievements_body_list_items_item_info_stext"><?=Yii::t('common', 'За каждого приглашенного 30 RUB на игровой баланс')?></div>
                            <div title="Чистый чай на металлолом" class="achievements_body_list_items_item_info_simage">
                                <img src="/uploads/drop/843_5e35e97a12e34fb4a96f5bafce7f8906.png">
                            </div>
                            <a href="/user/partner" class="achievements_body_list_items_item_info_btn">
                                <?=Yii::t('common', 'Подробнее')?>
                            </a>
                        </div>
                    </div>
                    <?php if (empty($user->telegram_chat_id)): ?>
                    <div class="achievements_body_list_items_item">
                        <div class="achievements_body_list_items_item_info">
                            <div class="achievements_body_list_items_item_info_stext"><?=Yii::t('common', 'Подключи телеграм бота и получи 50 RUB')?></div>
                            <div title="Чистый чай на металлолом" class="achievements_body_list_items_item_info_simage">
                                <img src="/images/tg.png">
                            </div>
                            <a href="https://t.me/ProstojServerBot" class="achievements_body_list_items_item_info_btn">
                                <?=Yii::t('common', 'Подключить')?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php foreach (Task::getTypeList() as $type => $title): ?>
            <div class="achievements_body_list_item_wrap">
                <div class="achievements_body_list_item_title">
                    <?=$title?>
                </div>
                <div class="achievements_body_list_items">
                    <?php $k = 1; ?>
                    <?php foreach (Task::getTasksByUser($user, $type) as $task): ?>
                        <div class="achievements_body_list_items_item <?=($task['status'] === 2) ? ' disabled' : ''?>">
                            <div class="achievements_body_list_items_item_number"><?=$k?></div>
                            <?php if ($k > 1): ?>
                            <div class="achievements_body_list_items_item_arrow"><i class="fas fa-angle-right"></i></div>
                            <?php endif; ?>
                            <div class="achievements_body_list_items_item_info">
                                <div class="achievements_body_list_items_item_info_stitle">
                                    <?=Yii::t('common', 'Задание')?>
                                </div>
                                <div class="achievements_body_list_items_item_info_stext">
                                    <?=Yii::t('database', $task['description'])?>
                                </div>
                                <div class="achievements_body_list_items_item_info_stitle">
                                    <?=Yii::t('common', 'Награда')?>
                                </div>
                                <div title="<?=$task['dropName']?>" class="achievements_body_list_items_item_info_simage">
                                    <img src="<?=$task['dropImage']?>"/>
                                    <div class="achievements_body_list_items_item_info_simage_count">x<?=$task['count']?></div>
                                </div>
                                <?php if ($task['status'] === 0): ?>
                                    <div class="achievements_body_list_items_item_info_btn disabled">
                                        <?= Yii::t('common', 'Получено'); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($task['status'] === 2): ?>
                                    <div class="achievements_body_list_items_item_info_btn disabled">
                                        <?= Yii::t('common', 'Не доступно'); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($task['status'] === 1): ?>
                                    <a href="/user/get-achievement?type=<?=$type?>" class="achievements_body_list_items_item_info_btn">
                                        <?= Yii::t('common', 'Получить'); ?>
                                    </a>
                                <?php endif; ?>
                                <div class="achievements_body_list_items_item_image"><img src="<?=$task['taskImage']?>"/></div>
                            </div>
                        </div>
                        <?php $k++; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="achievements_daily_reward active" id="achievements_daily_body">
        <div class="achievements_daily_reward_title">
            <?= Yii::t('common', 'Ежедневная награда'); ?>
        </div>
        <div class="achievements_daily_reward_list">
            <?php foreach ($dailyRewardList as $item): ?>
                <?php
                    if (!empty($item['status']) && $item['status'] === 'available') {
                        $received = false;
                    }
                ?>
                <div class="achievements_daily_reward_list_item <?=!empty($item['type']) ? 'achievements_daily_reward_list_item_' . $item['type'] : '' ?> <?=!empty($item['status']) ? $item['status'] : '' ?>">
                    <div class="achievements_daily_reward_list_item_wrap">
                        <div class="achievements_daily_reward_list_item_title"><?=$item['name']?></div>
                        <?php if (!empty($item['drop_id'])): ?>
                            <div class="achievements_daily_reward_list_item_image">
                                <img src="<?=$item['image']?>"/>
                            </div>
                            <div class="achievements_daily_reward_list_item_money">x<?=$item['amount']?></div>
                        <?php else: ?>
                            <div class="achievements_daily_reward_list_item_money"><?=$item['amount']?> RUB</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($received): ?>
            <div class="achievements_daily_reward_btn disabled">
                <?= Yii::t('common', 'Возвращайся завтра'); ?>
            </div>
        <?php else: ?>
            <a href="/user/get-daily-reward" class="achievements_daily_reward_btn">
                <?= Yii::t('common', 'Забрать'); ?>
            </a>
        <?php endif; ?>
        <div class="achievements_daily_reward_p_title">
            <?= Yii::t('common', 'Получи 150 RUB и другие бонусы'); ?>
        </div>
        <div class="achievements_daily_reward_p_text">
            <?= Yii::t('common', 'Забирайте бонусы за ежедневный вход в личный кабинет без пропусков. Кнопку “Забрать” нужно нажимать ежедневно, иначе счетчик дней начнется заново. Начисление производится на ваш игровой счет.'); ?>
        </div>
    </div>
</div>