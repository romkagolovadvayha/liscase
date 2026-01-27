<?php

use yii\db\Migration;

/**
 * Добавляет индексы для оптимизации WebSocket авторизации
 */
class m251031_000000_add_jwt_steam_id_index_to_user extends Migration
{
    public function safeUp()
    {
        // Добавляем индекс для быстрого поиска по JWT токену
        $this->createIndex(
            'idx-user-jwt',
            'user',
            'jwt'
        );

        // Добавляем составной индекс для поиска по JWT + steam_id (используется в WebSocket авторизации)
        $this->createIndex(
            'idx-user-jwt-steam_id',
            'user',
            ['jwt', 'steam_id']
        );
    }

    public function safeDown()
    {
        $this->dropIndex('idx-user-jwt-steam_id', 'user');
        $this->dropIndex('idx-user-jwt', 'user');
    }
}

