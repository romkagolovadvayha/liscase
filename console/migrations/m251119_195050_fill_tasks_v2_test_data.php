<?php

use console\components\migration\Migration;
use common\models\tasks_v2\TaskV2;
use yii\helpers\Json;

/**
 * Class m251119_195050_fill_tasks_v2_test_data
 * Заполнение тестовыми данными таблицы tasks_v2
 */
class m251119_195050_fill_tasks_v2_test_data extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $now = date('Y-m-d H:i:s');
        
        // 1. Одноразовое задание: Подписка на Telegram-бота
        $this->insert('tasks_v2', [
            'title' => 'Подключи Telegram-бота',
            'short_description' => 'Подключите нашего Telegram-бота и получите награду',
            'full_description' => 'Для выполнения задания необходимо подключить Telegram-бота к вашему аккаунту.' . "\n\n" . 'Шаг 1: Найдите нашего бота @YourBotName' . "\n" . 'Шаг 2: Нажмите /start' . "\n" . 'Шаг 3: Авторизуйтесь через бота' . "\n" . 'Шаг 4: Нажмите кнопку "Проверить" ниже',
            'type' => TaskV2::TYPE_ONE_TIME,
            'check_type' => TaskV2::CHECK_TYPE_TELEGRAM_CONNECTED,
            'check_params' => Json::encode([]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 100.00,
            'per_user_limit' => null,
            'global_limit' => null,
            'global_completed' => 0,
            'image_path' => 'uploads/tasks-v2/default.png',
            'button_text' => 'Проверить подключение',
            'extra_buttons' => Json::encode([
                ['label' => 'Открыть Telegram-бота', 'url' => 'https://t.me/YourBotName']
            ]),
            'is_active' => 1,
            'is_visible_for_guests' => 0,
            'sort' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 2. Одноразовое задание: Подписка на VK группу
        $this->insert('tasks_v2', [
            'title' => 'Подпишись на группу VK',
            'short_description' => 'Подпишитесь на нашу официальную группу ВКонтакте',
            'full_description' => 'Оставайтесь на связи с нашим сообществом! Подпишитесь на нашу официальную группу ВКонтакте и получайте новости о серверах, вайпах и акциях.',
            'type' => TaskV2::TYPE_ONE_TIME,
            'check_type' => TaskV2::CHECK_TYPE_VK_SUBSCRIBE_GROUP,
            'check_params' => Json::encode(['group_id' => 123456789]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 150.00,
            'per_user_limit' => null,
            'global_limit' => null,
            'global_completed' => 0,
            'image_path' => 'uploads/tasks-v2/default.png',
            'button_text' => 'Проверить подписку',
            'extra_buttons' => Json::encode([
                ['label' => 'Подписаться в VK', 'url' => 'https://vk.com/your_group']
            ]),
            'is_active' => 1,
            'is_visible_for_guests' => 1,
            'sort' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 3. Одноразовое задание: Вступление в Discord
        $this->insert('tasks_v2', [
            'title' => 'Вступи в Discord',
            'short_description' => 'Присоединяйтесь к нашему Discord-серверу',
            'full_description' => 'Присоединяйтесь к нашему Discord-серверу для общения с игроками, получения поддержки и участия в розыгрышах!',
            'type' => TaskV2::TYPE_ONE_TIME,
            'check_type' => TaskV2::CHECK_TYPE_DISCORD_JOIN,
            'check_params' => Json::encode([]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'skins',
            'reward_amount' => 200.00,
            'per_user_limit' => null,
            'global_limit' => null,
            'global_completed' => 0,
            'image_path' => 'uploads/tasks-v2/default.png',
            'button_text' => 'Проверить участие',
            'extra_buttons' => Json::encode([
                ['label' => 'Вступить в Discord', 'url' => 'https://discord.gg/yourserver']
            ]),
            'is_active' => 1,
            'is_visible_for_guests' => 1,
            'sort' => 3,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 4. Многоразовое задание: Убить 10 ботов
        $this->insert('tasks_v2', [
            'title' => 'Убей 10 ботов',
            'short_description' => 'Уничтожьте 10 ботов на сервере',
            'full_description' => 'Проявите свои навыки в бою! Уничтожьте 10 ботов на любом из наших серверов.' . "\n\n" . 'Прогресс отображается в реальном времени. Каждое убийство бота засчитывается автоматически.',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_KILL_BOTS_COUNT,
            'check_params' => Json::encode(['count' => 10]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 50.00,
            'per_user_limit' => 5, // Можно выполнить 5 раз
            'global_limit' => null,
            'global_completed' => 0,
            'image_path' => 'uploads/tasks-v2/default.png',
            'button_text' => 'Проверить прогресс',
            'extra_buttons' => Json::encode([]),
            'is_active' => 1,
            'is_visible_for_guests' => 0,
            'sort' => 4,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 5. Многоразовое задание: Убить 50 ботов (больше награда)
        $this->insert('tasks_v2', [
            'title' => 'Убей 50 ботов',
            'short_description' => 'Уничтожьте 50 ботов и получите большую награду',
            'full_description' => 'Серьезный вызов для опытных игроков! Уничтожьте 50 ботов и получите щедрую награду.' . "\n\n" . 'Это задание можно выполнять неограниченное количество раз.',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_KILL_BOTS_COUNT,
            'check_params' => Json::encode(['count' => 50]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 300.00,
            'per_user_limit' => null, // Без ограничений
            'global_limit' => null,
            'global_completed' => 0,
            'image_path' => 'uploads/tasks-v2/default.png',
            'button_text' => 'Проверить прогресс',
            'extra_buttons' => Json::encode([]),
            'is_active' => 1,
            'is_visible_for_guests' => 0,
            'sort' => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 6. Многоразовое задание: Пригласи друга
        $this->insert('tasks_v2', [
            'title' => 'Пригласи друга',
            'short_description' => 'Пригласите друга присоединиться к проекту',
            'full_description' => 'Расширяйте наше сообщество! Пригласите друга, и вы оба получите награду.' . "\n\n" . 'Используйте вашу реферальную ссылку для приглашения друзей.' . "\n" . 'Задание засчитывается, когда ваш друг зарегистрируется и будет активен в течение 30 дней.',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_INVITE_FRIEND,
            'check_params' => Json::encode(['count' => 1]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'skins',
            'reward_amount' => 250.00,
            'per_user_limit' => 10, // Можно выполнить 10 раз
            'global_limit' => null,
            'global_completed' => 0,
            'image_path' => 'uploads/tasks-v2/default.png',
            'button_text' => 'Проверить приглашения',
            'extra_buttons' => Json::encode([]),
            'is_active' => 1,
            'is_visible_for_guests' => 0,
            'sort' => 6,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 7. Одноразовое задание с товаром: Получить предмет из магазина
        $this->insert('tasks_v2', [
            'title' => 'Получи бесплатный предмет',
            'short_description' => 'Выполните задание и получите предмет из магазина',
            'full_description' => 'Специальное предложение! Выполните простое задание и получите предмет из нашего магазина абсолютно бесплатно.' . "\n\n" . 'Предмет будет добавлен в ваш инвентарь автоматически после проверки.',
            'type' => TaskV2::TYPE_ONE_TIME,
            'check_type' => TaskV2::CHECK_TYPE_CUSTOM_MANUAL,
            'check_params' => Json::encode([]),
            'reward_type' => TaskV2::REWARD_TYPE_ITEM,
            'reward_item_id' => 1, // ID предмета из магазина (нужно заменить на реальный)
            'reward_currency' => null,
            'reward_amount' => null,
            'per_user_limit' => null,
            'global_limit' => 100, // Только первые 100 игроков
            'global_completed' => 0,
            'image_path' => 'uploads/tasks-v2/default.png',
            'button_text' => 'Забрать награду',
            'extra_buttons' => Json::encode([
                ['label' => 'Перейти в магазин', 'url' => '/store']
            ]),
            'is_active' => 1,
            'is_visible_for_guests' => 0,
            'sort' => 7,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 8. Одноразовое задание: Пригласи 3 друзей (большая награда)
        $this->insert('tasks_v2', [
            'title' => 'Пригласи 3 друзей',
            'short_description' => 'Пригласите сразу 3 друзей и получите щедрую награду',
            'full_description' => 'Специальное задание для активных участников сообщества!' . "\n\n" . 'Пригласите сразу 3 друзей, и вы получите значительную награду.' . "\n" . 'Каждый друг должен зарегистрироваться и быть активным в течение 30 дней.',
            'type' => TaskV2::TYPE_ONE_TIME,
            'check_type' => TaskV2::CHECK_TYPE_INVITE_FRIEND,
            'check_params' => Json::encode(['count' => 3]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'personal',
            'reward_amount' => 1000.00,
            'per_user_limit' => null,
            'global_limit' => null,
            'global_completed' => 0,
            'image_path' => 'uploads/tasks-v2/default.png',
            'button_text' => 'Проверить',
            'extra_buttons' => Json::encode([]),
            'is_active' => 1,
            'is_visible_for_guests' => 0,
            'sort' => 8,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 9. Многоразовое задание: Убить 100 ботов (элитное задание)
        $this->insert('tasks_v2', [
            'title' => 'Элитное: Убей 100 ботов',
            'short_description' => 'Покажи свое мастерство - уничтожь 100 ботов',
            'full_description' => 'Элитное задание для настоящих профессионалов!' . "\n\n" . 'Уничтожьте 100 ботов и докажите, что вы лучший.' . "\n" . 'Награда соответствующая - один из лучших предметов из магазина!' . "\n\n" . 'Это задание можно выполнять до 3 раз.',
            'type' => TaskV2::TYPE_REPEATABLE,
            'check_type' => TaskV2::CHECK_TYPE_KILL_BOTS_COUNT,
            'check_params' => Json::encode(['count' => 100]),
            'reward_type' => TaskV2::REWARD_TYPE_ITEM,
            'reward_item_id' => 2, // ID предмета из магазина (нужно заменить на реальный)
            'reward_currency' => null,
            'reward_amount' => null,
            'per_user_limit' => 3,
            'global_limit' => null,
            'global_completed' => 0,
            'image_path' => 'uploads/tasks-v2/default.png',
            'button_text' => 'Проверить прогресс',
            'extra_buttons' => Json::encode([]),
            'is_active' => 1,
            'is_visible_for_guests' => 0,
            'sort' => 9,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 10. Одноразовое задание с несколькими кнопками: Социальные сети
        $this->insert('tasks_v2', [
            'title' => 'Подпишись на все социальные сети',
            'short_description' => 'Следите за нами во всех соцсетях и получите бонус',
            'full_description' => 'Оставайтесь на связи с нами во всех социальных сетях!' . "\n\n" . 'Подпишитесь на наши группы и каналы, чтобы не пропустить важные новости, акции и розыгрыши.' . "\n\n" . 'После подписки нажмите кнопку "Проверить" ниже.',
            'type' => TaskV2::TYPE_ONE_TIME,
            'check_type' => TaskV2::CHECK_TYPE_CUSTOM_MANUAL,
            'check_params' => Json::encode([]),
            'reward_type' => TaskV2::REWARD_TYPE_CURRENCY,
            'reward_item_id' => null,
            'reward_currency' => 'skins',
            'reward_amount' => 500.00,
            'per_user_limit' => null,
            'global_limit' => null,
            'global_completed' => 0,
            'image_path' => 'uploads/tasks-v2/default.png',
            'button_text' => 'Проверить подписки',
            'extra_buttons' => Json::encode([
                ['label' => 'VK группа', 'url' => 'https://vk.com/your_group'],
                ['label' => 'Telegram канал', 'url' => 'https://t.me/your_channel'],
                ['label' => 'Discord сервер', 'url' => 'https://discord.gg/yourserver'],
                ['label' => 'YouTube канал', 'url' => 'https://youtube.com/your_channel']
            ]),
            'is_active' => 1,
            'is_visible_for_guests' => 1,
            'sort' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->delete('tasks_v2', ['>', 'id', 0]); // Удаляем все записи
    }
}
