<?php

use console\components\migration\Migration;

/**
 * Class m251201_220605_add_is_hide_online_to_user_profile
 * 
 * Добавляет поле isHideOnline в таблицу user_profile для скрытия статуса онлайн/оффлайн
 */
class m251201_220605_add_is_hide_online_to_user_profile extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user_profile', 'is_hide_online', self::TINYINT_1_FIELD . ' COMMENT \'Скрывать статус онлайн/оффлайн (только для VIP)\'');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('user_profile', 'is_hide_online');
    }
}
