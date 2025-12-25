<?php

use yii\db\Migration;

/**
 * Class m260120_150000_add_steam_avatar_url_to_user_profile
 */
class m260120_150000_add_steam_avatar_url_to_user_profile extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Добавляем поле для хранения URL Steam аватара
        if (!$this->getDb()->getSchema()->getTableSchema('user_profile')->getColumn('steam_avatar_url')) {
            $this->addColumn('user_profile', 'steam_avatar_url', $this->string(500)->null()->comment('URL аватара из Steam'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        if ($this->getDb()->getSchema()->getTableSchema('user_profile')->getColumn('steam_avatar_url')) {
            $this->dropColumn('user_profile', 'steam_avatar_url');
        }
    }
}


