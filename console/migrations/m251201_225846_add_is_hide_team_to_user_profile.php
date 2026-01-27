<?php

use console\components\migration\Migration;

/**
 * Class m251201_225846_add_is_hide_team_to_user_profile
 * 
 * Добавляет поле is_hide_team в таблицу user_profile для скрытия списка команды
 */
class m251201_225846_add_is_hide_team_to_user_profile extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user_profile', 'is_hide_team', self::TINYINT_1_FIELD . ' COMMENT \'Скрывать список команды (только для VIP)\'');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('user_profile', 'is_hide_team');
    }
}

