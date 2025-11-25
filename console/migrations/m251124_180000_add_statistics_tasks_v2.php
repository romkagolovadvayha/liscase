<?php

use console\components\migration\Migration;
use common\models\tasks_v2\TaskV2;
use yii\helpers\Json;

/**
 * Class m251124_180000_add_statistics_tasks_v2
 * Добавление 20 заданий на основе статистики игроков
 */
class m251124_180000_add_statistics_tasks_v2 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $now = date('Y-m-d H:i:s');
        $sort = 100; // Начинаем с 100, чтобы не конфликтовать с существующими
        
        // 1. Первая кровь - Убить 1 игрока
        $this->insert('tasks_v2', [
            'title' => 'Первая кровь',
            'short_description' => 'Убейте своего первого игрока',
            'full_description' => 'Докажите, что вы настоящий боец! Убейте хотя бы одного игрока на сервере.' . "\n\n" . 'Это задание можно выполнить только один раз.',
            'type' => TaskV2::TYPE_ONE_TIME,
            'check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM,
            'check_params' => Json::encode([
                'stat_key' => 'kills',
                'required_value' => 1,
                'sum_all_servers' => false,
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

        // 2. Охотник - Убить 10 животных
        $this->insert('tasks_v2', [
            'title' => 'Охотник',
            'short_description' => 'Убейте 10 животных',
            'full_description' => 'Станьте настоящим охотником! Убейте 10 животных (кабаны, олени, волки и т.д.).' . "\n\n" . 'Задание можно выполнять многократно.',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM,
            'check_params' => Json::encode([
                'stat_key' => 'boar',
                'required_value' => 10,
                'sum_all_servers' => false,
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 75.00,
            'per_user_limit' => 10,
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

        // 3. Рыбак - Поймать 5 рыб
        $this->insert('tasks_v2', [
            'title' => 'Рыбак',
            'short_description' => 'Поймайте 5 рыб',
            'full_description' => 'Проведите время на рыбалке! Поймайте 5 рыб любого вида.' . "\n\n" . 'Рыбалка - это не только ресурсы, но и отличный способ расслабиться.',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM,
            'check_params' => Json::encode([
                'stat_key' => 'f_fish.anchovy',
                'required_value' => 5,
                'sum_all_servers' => false,
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 50.00,
            'per_user_limit' => 20,
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

        // 4. Фармер - Нафармить 1000 серной руды
        $this->insert('tasks_v2', [
            'title' => 'Фармер серы',
            'short_description' => 'Нафармите 1000 серной руды',
            'full_description' => 'Серная руда - основа для взрывчатки! Нафармьте 1000 единиц серной руды.' . "\n\n" . 'Это задание можно выполнять многократно.',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM,
            'check_params' => Json::encode([
                'stat_key' => 'sulfur.ore',
                'required_value' => 1000,
                'sum_all_servers' => false,
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 200.00,
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

        // 5. Снайпер - Убить 5 игроков из винтовки
        $this->insert('tasks_v2', [
            'title' => 'Снайпер',
            'short_description' => 'Убейте 5 игроков из Bolt Action Rifle',
            'full_description' => 'Покажите свою меткость! Убейте 5 игроков используя Bolt Action Rifle.' . "\n\n" . 'Точность важнее скорости.',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM,
            'check_params' => Json::encode([
                'stat_key' => 'bolt',
                'required_value' => 5,
                'sum_all_servers' => false,
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 150.00,
            'per_user_limit' => 10,
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

        // 6. Рейдер - Уничтожить 3 TC
        $this->insert('tasks_v2', [
            'title' => 'Рейдер',
            'short_description' => 'Уничтожьте 3 Tool Cupboard',
            'full_description' => 'Настоящий рейдер не останавливается! Уничтожьте 3 Tool Cupboard (шкафчики) у других игроков.' . "\n\n" . 'Осторожно: это может вызвать конфликты!',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM,
            'check_params' => Json::encode([
                'stat_key' => 'tcsdestroyed',
                'required_value' => 3,
                'sum_all_servers' => false,
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 250.00,
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

        // 7. Медик - Использовать 20 аптечек
        $this->insert('tasks_v2', [
            'title' => 'Медик',
            'short_description' => 'Используйте 20 больших аптечек',
            'full_description' => 'Заботьтесь о своем здоровье! Используйте 20 больших аптечек (Large Medkit).' . "\n\n" . 'Здоровье - это главное в Rust!',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM,
            'check_params' => Json::encode([
                'stat_key' => 'mod_largemedkit',
                'required_value' => 20,
                'sum_all_servers' => false,
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 100.00,
            'per_user_limit' => 10,
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

        // 8. Взрывник - Использовать 5 C4
        $this->insert('tasks_v2', [
            'title' => 'Взрывник',
            'short_description' => 'Используйте 5 C4',
            'full_description' => 'Взрывы - это искусство! Используйте 5 C4 для рейдов.' . "\n\n" . 'C4 - самый мощный инструмент рейдера.',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM,
            'check_params' => Json::encode([
                'stat_key' => 'c4thrown',
                'required_value' => 5,
                'sum_all_servers' => false,
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 300.00,
            'per_user_limit' => 3,
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

        // 9. Убийца ученых - Убить 10 ученых
        $this->insert('tasks_v2', [
            'title' => 'Охотник на ученых',
            'short_description' => 'Убейте 10 ученых',
            'full_description' => 'Ученые - опасные противники! Убейте 10 ученых на монументах.' . "\n\n" . 'Они хорошо вооружены, будьте осторожны!',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM,
            'check_params' => Json::encode([
                'stat_key' => 'scientists',
                'required_value' => 10,
                'sum_all_servers' => false,
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 150.00,
            'per_user_limit' => 10,
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

        // 10. Голый воин - Убить 3 голых игроков
        $this->insert('tasks_v2', [
            'title' => 'Голый воин',
            'short_description' => 'Убейте 3 голых игроков',
            'full_description' => 'Иногда самые опасные враги - это голые новички! Убейте 3 игроков без брони.' . "\n\n" . 'Не стоит недооценивать их!',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM,
            'check_params' => Json::encode([
                'stat_key' => 'nude_kills',
                'required_value' => 3,
                'sum_all_servers' => false,
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 75.00,
            'per_user_limit' => 10,
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

        // 11. Лесоруб - Нафармить 5000 дерева
        $this->insert('tasks_v2', [
            'title' => 'Лесоруб',
            'short_description' => 'Нафармите 5000 дерева',
            'full_description' => 'Дерево - основа строительства! Нафармьте 5000 единиц дерева.' . "\n\n" . 'Без дерева не построишь базу.',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM,
            'check_params' => Json::encode([
                'stat_key' => 'wood',
                'required_value' => 5000,
                'sum_all_servers' => false,
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 100.00,
            'per_user_limit' => 10,
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

        // 12. Каменщик - Нафармить 2000 камней
        $this->insert('tasks_v2', [
            'title' => 'Каменщик',
            'short_description' => 'Нафармите 2000 камней',
            'full_description' => 'Камни нужны для прочных стен! Нафармьте 2000 единиц камней.' . "\n\n" . 'Каменные стены выдержат любой рейд.',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM,
            'check_params' => Json::encode([
                'stat_key' => 'stones',
                'required_value' => 2000,
                'sum_all_servers' => false,
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 120.00,
            'per_user_limit' => 10,
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

        // 13. Охотник на медведей - Убить 5 медведей
        $this->insert('tasks_v2', [
            'title' => 'Охотник на медведей',
            'short_description' => 'Убейте 5 медведей',
            'full_description' => 'Медведи - опасные противники! Убейте 5 медведей (обычных или полярных).' . "\n\n" . 'Они дают много ресурсов, но очень опасны!',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM,
            'check_params' => Json::encode([
                'stat_key' => 'bear',
                'required_value' => 5,
                'sum_all_servers' => false,
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 200.00,
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

        // 14. Мастер голов - Попасть в голову 20 раз
        $this->insert('tasks_v2', [
            'title' => 'Мастер голов',
            'short_description' => 'Попадите в голову 20 раз',
            'full_description' => 'Точность - залог успеха! Попадите в голову противников 20 раз.' . "\n\n" . 'Хедшоты наносят максимальный урон!',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM,
            'check_params' => Json::encode([
                'stat_key' => 'hits_head',
                'required_value' => 20,
                'sum_all_servers' => false,
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 150.00,
            'per_user_limit' => 10,
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

        // 15. Открыватель ящиков - Открыть 50 ящиков
        $this->insert('tasks_v2', [
            'title' => 'Кладоискатель',
            'short_description' => 'Откройте 50 ящиков',
            'full_description' => 'Исследуйте мир! Откройте 50 ящиков (crate) на монументах или в других местах.' . "\n\n" . 'В ящиках можно найти ценные ресурсы!',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM,
            'check_params' => Json::encode([
                'stat_key' => 'crate_open',
                'required_value' => 50,
                'sum_all_servers' => false,
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 125.00,
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

        // 16. Разрушитель бочек - Разбить 100 бочек
        $this->insert('tasks_v2', [
            'title' => 'Разрушитель',
            'short_description' => 'Разбейте 100 бочек',
            'full_description' => 'Бочки - источник ресурсов! Разбейте 100 бочек (barrel) на карте.' . "\n\n" . 'В бочках можно найти компоненты и ресурсы.',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM,
            'check_params' => Json::encode([
                'stat_key' => 'barrel',
                'required_value' => 100,
                'sum_all_servers' => false,
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 80.00,
            'per_user_limit' => 10,
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

        // 17. Ветеран - Убить 50 игроков
        $this->insert('tasks_v2', [
            'title' => 'Ветеран',
            'short_description' => 'Убейте 50 игроков',
            'full_description' => 'Настоящий ветеран боев! Убейте 50 игроков на сервере.' . "\n\n" . 'Это задание для опытных PvP игроков.',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM,
            'check_params' => Json::encode([
                'stat_key' => 'kills',
                'required_value' => 50,
                'sum_all_servers' => false,
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 500.00,
            'per_user_limit' => 3,
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

        // 18. Рыбак-профи - Поймать акулу
        $this->insert('tasks_v2', [
            'title' => 'Охотник на акул',
            'short_description' => 'Поймайте 3 акулы',
            'full_description' => 'Акула - редкая и ценная добыча! Поймайте 3 акулы (Small Shark).' . "\n\n" . 'Акулы дают много ресурсов, но их сложно поймать!',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM,
            'check_params' => Json::encode([
                'stat_key' => 'f_fish.smallshark',
                'required_value' => 3,
                'sum_all_servers' => false,
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 250.00,
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

        // 19. Металлург - Нафармить 2000 железной руды
        $this->insert('tasks_v2', [
            'title' => 'Металлург',
            'short_description' => 'Нафармите 2000 железной руды',
            'full_description' => 'Железная руда нужна для металла! Нафармьте 2000 единиц железной руды.' . "\n\n" . 'Металл - основа для оружия и брони.',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM,
            'check_params' => Json::encode([
                'stat_key' => 'metal.ore',
                'required_value' => 2000,
                'sum_all_servers' => false,
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 180.00,
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

        // 20. Мастер выживания - Провести 10 часов в игре
        $this->insert('tasks_v2', [
            'title' => 'Мастер выживания',
            'short_description' => 'Проведите 10 часов в игре',
            'full_description' => 'Выживание требует времени! Проведите 10 часов (600 минут) в игре на сервере.' . "\n\n" . 'Это задание можно выполнить только один раз.',
            'type' => TaskV2::TYPE_ONE_TIME,
            'check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM,
            'check_params' => Json::encode([
                'stat_key' => 'playtime',
                'required_value' => 600, // 10 часов = 600 минут
                'sum_all_servers' => false,
            ]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 300.00,
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
        // Удаляем все задания со статистикой
        $this->delete('tasks_v2', ['check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM]);
    }
}





