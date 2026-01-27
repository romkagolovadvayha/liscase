<?php

use console\components\migration\Migration;

/**
 * Class m251201_203742_add_social_links_to_user_profile
 * 
 * Добавляет поля для социальных ссылок в таблицу user_profile
 */
class m251201_203742_add_social_links_to_user_profile extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user_profile', 'youtube_link', 'VARCHAR(500) DEFAULT NULL COMMENT \'Ссылка на YouTube\'');
        $this->addColumn('user_profile', 'twitch_link', 'VARCHAR(500) DEFAULT NULL COMMENT \'Ссылка на Twitch\'');
        $this->addColumn('user_profile', 'vk_link', 'VARCHAR(500) DEFAULT NULL COMMENT \'Ссылка на VK\'');
        $this->addColumn('user_profile', 'telegram_link', 'VARCHAR(500) DEFAULT NULL COMMENT \'Ссылка на Telegram\'');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('user_profile', 'youtube_link');
        $this->dropColumn('user_profile', 'twitch_link');
        $this->dropColumn('user_profile', 'vk_link');
        $this->dropColumn('user_profile', 'telegram_link');
    }
}
