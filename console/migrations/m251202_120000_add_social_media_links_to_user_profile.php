<?php

use yii\db\Migration;

/**
 * Class m251202_120000_add_social_media_links_to_user_profile
 */
class m251202_120000_add_social_media_links_to_user_profile extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Добавляем поля для социальных сетей в таблицу user_profile
        $this->addColumn('{{%user_profile}}', 'youtube_link', $this->string(255)->null()->comment('Ссылка на YouTube канал'));
        $this->addColumn('{{%user_profile}}', 'tiktok_link', $this->string(255)->null()->comment('Ссылка на TikTok'));
        $this->addColumn('{{%user_profile}}', 'twitch_link', $this->string(255)->null()->comment('Ссылка на Twitch канал'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Удаляем добавленные поля
        $this->dropColumn('{{%user_profile}}', 'youtube_link');
        $this->dropColumn('{{%user_profile}}', 'tiktok_link');
        $this->dropColumn('{{%user_profile}}', 'twitch_link');
    }
}


