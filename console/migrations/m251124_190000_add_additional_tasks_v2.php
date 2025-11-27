<?php

use console\components\migration\Migration;
use common\models\tasks_v2\TaskV2;
use yii\helpers\Json;

/**
 * Class m251124_190000_add_additional_tasks_v2
 * Добавление дополнительных заданий: парашют, комментарии, постройки, радио
 */
class m251124_190000_add_additional_tasks_v2 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $now = date('Y-m-d H:i:s');
        $sort = 200; // Начинаем с 200, чтобы не конфликтовать с существующими
        
        // 1. Парашютист - Сделать 10 прыжков с парашютом
        $this->insert('tasks_v2', [
            'title' => 'Парашютист',
            'short_description' => 'Сделайте 10 прыжков с парашютом',
            'full_description' => 'Покорите небо! Сделайте 10 прыжков с парашютом на сервере.' . "\n\n" . 'Парашюты можно найти в различных местах на карте.',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM,
            'check_params' => Json::encode([
                'stat_key' => 'parachuteseat',
                'required_value' => 10,
                'sum_all_servers' => false,
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 150.00,
            'per_user_limit' => 5,
            'global_limit' => null,
            'global_completed' => 0,
            'image_path' => 'uploads/tasks-v2/default.png',
            'button_text' => 'Проверить',
            'extra_buttons' => Json::encode([]),
            'is_active' => 1,
            'is_visible_for_guests' => 0,
            'sort' => $sort++,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 2. Комментатор - Оставить более 3 комментариев в новостях
        $this->insert('tasks_v2', [
            'title' => 'Активный комментатор',
            'short_description' => 'Оставьте более 3 комментариев в разделе новости',
            'full_description' => 'Будьте активны в сообществе! Оставьте более 3 комментариев в разделе новостей.' . "\n\n" . 'Ваши комментарии помогают развивать сообщество!',
            'type' => TaskV2::TYPE_ONE_TIME,
            'check_type' => TaskV2::CHECK_TYPE_COMMENTS_COUNT,
            'check_params' => Json::encode([
                'required_count' => 3,
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 100.00,
            'per_user_limit' => null,
            'global_limit' => null,
            'global_completed' => 0,
            'image_path' => 'uploads/tasks-v2/default.png',
            'button_text' => 'Проверить',
            'extra_buttons' => Json::encode([]),
            'is_active' => 1,
            'is_visible_for_guests' => 0,
            'sort' => $sort++,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 3. Архитектор - Добавить свою постройку на сайт
        $this->insert('tasks_v2', [
            'title' => 'Архитектор',
            'short_description' => 'Добавьте свою постройку на сайт',
            'full_description' => 'Поделитесь своим творчеством! Добавьте свою постройку на сайт для других игроков.' . "\n\n" . 'Покажите всем, на что вы способны в строительстве!',
            'type' => TaskV2::TYPE_ONE_TIME,
            'check_type' => TaskV2::CHECK_TYPE_BUILDING_ADD,
            'check_params' => Json::encode([
                'required_count' => 1,
                'only_active' => true, // Только одобренные постройки
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 200.00,
            'per_user_limit' => null,
            'global_limit' => null,
            'global_completed' => 0,
            'image_path' => 'uploads/tasks-v2/default.png',
            'button_text' => 'Проверить',
            'extra_buttons' => Json::encode([]),
            'is_active' => 1,
            'is_visible_for_guests' => 0,
            'sort' => $sort++,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 4. Диджей - Добавить музыку в радио на сервер
        $this->insert('tasks_v2', [
            'title' => 'Диджей',
            'short_description' => 'Добавьте музыку в радио на сервер',
            'full_description' => 'Станьте диджеем! Добавьте свою музыку в радио на сервер.' . "\n\n" . 'Ваши треки будут играть на радиостанции для всех игроков!',
            'type' => TaskV2::TYPE_ONE_TIME,
            'check_type' => TaskV2::CHECK_TYPE_RADIO_TRACK_ADD,
            'check_params' => Json::encode([
                'required_count' => 1,
                'only_active' => false, // Засчитываем даже треки на модерации
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 150.00,
            'per_user_limit' => null,
            'global_limit' => null,
            'global_completed' => 0,
            'image_path' => 'uploads/tasks-v2/default.png',
            'button_text' => 'Проверить',
            'extra_buttons' => Json::encode([]),
            'is_active' => 1,
            'is_visible_for_guests' => 0,
            'sort' => $sort++,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Удаляем добавленные задания
        $this->delete('tasks_v2', ['check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM, 'title' => 'Парашютист']);
        $this->delete('tasks_v2', ['check_type' => TaskV2::CHECK_TYPE_COMMENTS_COUNT]);
        $this->delete('tasks_v2', ['check_type' => TaskV2::CHECK_TYPE_BUILDING_ADD]);
        $this->delete('tasks_v2', ['check_type' => TaskV2::CHECK_TYPE_RADIO_TRACK_ADD]);
    }
}






