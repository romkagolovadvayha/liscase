<?php

use yii\db\Migration;

/**
 * Добавляет индексы для оптимизации поиска пользователей
 */
class m260128_000000_add_user_search_indexes extends Migration
{
    public function safeUp()
    {
        // Индекс для поиска по username (LIKE запросы)
        $this->createIndex(
            'idx-user-username',
            'user',
            'username'
        );

        // Индекс для поиска по steam_id (точное совпадение)
        $this->createIndex(
            'idx-user-steam_id',
            'user',
            'steam_id'
        );

        // Индекс для сортировки по дате последнего визита
        $this->createIndex(
            'idx-user-last_visit_server_at',
            'user',
            'last_visit_server_at'
        );

        // Составной индекс для оптимизации поиска с сортировкой
        $this->createIndex(
            'idx-user-username-last_visit',
            'user',
            ['username', 'last_visit_server_at']
        );
    }

    public function safeDown()
    {
        $this->dropIndex('idx-user-username-last_visit', 'user');
        $this->dropIndex('idx-user-last_visit_server_at', 'user');
        $this->dropIndex('idx-user-steam_id', 'user');
        $this->dropIndex('idx-user-username', 'user');
    }
}




