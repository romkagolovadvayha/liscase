<?php

use yii\db\Migration;

/**
 * Class m251203_110130_add_only_with_user_to_telegram_constructor
 * Добавляет поле only_with_user для фильтрации рассылок только для пользователей с привязанным user
 */
class m251203_110130_add_only_with_user_to_telegram_constructor extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('telegram_constructor', 'only_with_user', 'TINYINT(1) DEFAULT 0 COMMENT \'Отправлять только пользователям с привязанным user (для VK)\'');
        $this->createIndex('idx-telegram_constructor-only_with_user', 'telegram_constructor', 'only_with_user');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('idx-telegram_constructor-only_with_user', 'telegram_constructor');
        $this->dropColumn('telegram_constructor', 'only_with_user');
    }
}

