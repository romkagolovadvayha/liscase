<?php

use console\components\migration\Migration;
use common\models\tasks_v2\TaskV2;
use yii\helpers\Json;

/**
 * Class m251123_120000_add_daily_reward_task_example
 * Добавление примера ежедневного задания
 */
class m251123_122101_add_daily_reward_task_example extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $now = date('Y-m-d H:i:s');
        
        // Ежедневное задание: Ежедневная награда
        // Список наград: каждый день пользователь получает следующую награду из списка
        // Если пропустил день - начинается сначала
        // Если дошел до последней - начинается сначала
        $this->insert('tasks_v2', [
            'title' => 'Ежедневная награда',
            'short_description' => 'Забирайте награду каждый день! Чем больше дней подряд, тем лучше награда.',
            'full_description' => 'Забирайте награду каждый день без пропусков!' . "\n\n" . 
                'Каждый день вы получаете следующую награду из списка. Чем больше дней подряд вы забираете награду, тем лучше становится награда.' . "\n\n" .
                '⚠️ Важно: если вы пропустите хотя бы один день, счетчик сбросится и награды начнутся сначала.' . "\n\n" .
                'После последней награды цикл начинается заново.',
            'type' => TaskV2::TYPE_DAILY_REWARD,
            'check_type' => TaskV2::CHECK_TYPE_DAILY_REWARD,
            'check_params' => Json::encode([
                'rewards' => [
                    // День 1
                    ['drop_id' => 843, 'amount' => 10], // 10 монет
                    // День 2
                    ['drop_id' => 843, 'amount' => 20], // 20 монет
                    // День 3
                    ['drop_id' => 843, 'amount' => 30], // 30 монет
                    // День 4
                    ['drop_id' => 843, 'amount' => 50], // 50 монет
                    // День 5
                    ['drop_id' => 843, 'amount' => 75], // 75 монет
                    // День 6
                    ['drop_id' => 843, 'amount' => 100], // 100 монет
                    // День 7
                    ['drop_id' => 843, 'amount' => 150], // 150 монет
                    // День 8
                    ['drop_id' => 843, 'amount' => 200], // 200 монет
                    // День 9
                    ['drop_id' => 843, 'amount' => 250], // 250 монет
                    // День 10
                    ['drop_id' => 843, 'amount' => 300], // 300 монет
                    // День 11
                    ['drop_id' => 843, 'amount' => 400], // 400 монет
                    // День 12
                    ['drop_id' => 843, 'amount' => 500], // 500 монет
                    // День 13
                    ['drop_id' => 843, 'amount' => 750], // 750 монет
                    // День 14 (последний день - большая награда)
                    ['drop_id' => 843, 'amount' => 1000], // 1000 монет
                ]
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY, // Не используется для ежедневных наград, но оставляем для совместимости
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => null, // Награда берется из check_params
            'per_user_limit' => null, // Без ограничений
            'global_limit' => null,
            'global_completed' => 0,
            'image_path' => 'uploads/tasks-v2/daily-reward.png',
            'button_text' => 'Забрать награду',
            'extra_buttons' => Json::encode([]),
            'is_active' => 1,
            'is_visible_for_guests' => 0,
            'sort' => 0, // Ставим в начало списка
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Удаляем задание по title
        $this->delete('tasks_v2', ['title' => 'Ежедневная награда']);
    }
}

