<?php

use console\components\migration\Migration;

/**
 * Handles the creation of table `audience_bonus`.
 */
class m260120_160000_create_audience_bonus_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('audience_bonus', [
            'id' => self::PRIMARY_KEY,
            'audience_type' => self::TINYINT_FIELD . ' NOT NULL COMMENT \'Тип аудитории: 1 - депозиты, 2 - вайпы\'',
            'parameters_json' => 'TEXT NULL COMMENT \'JSON с параметрами начисления\'',
            'message_template' => 'TEXT NULL COMMENT \'Шаблон сообщения для ТГ бота\'',
            'test_user_ids' => 'TEXT NULL COMMENT \'JSON массив ID пользователей для тестирования, NULL если не тестовое\'',
            'total_users' => self::INT_FIELD_NOT_NULL . ' DEFAULT 0 COMMENT \'Общее количество пользователей\'',
            'total_amount' => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT \'Общая сумма начисления\'',
            'created_at' => self::TIMESTAMP_FIELD,
            'created_by' => self::INT_FIELD . ' COMMENT \'ID пользователя, создавшего начисление\'',
        ], self::TABLE_OPTIONS);

        $this->createIndex('idx-audience_bonus-audience_type', 'audience_bonus', 'audience_type');
        $this->createIndex('idx-audience_bonus-created_at', 'audience_bonus', 'created_at');
        $this->createIndex('idx-audience_bonus-created_by', 'audience_bonus', 'created_by');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('audience_bonus');
    }
}

