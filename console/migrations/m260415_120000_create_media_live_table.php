<?php

use console\components\migration\Migration;

/**
 * Эфиры стримеров (Twitch/Kick): сессии для учёта длительности и бонуса монет.
 */
class m260415_120000_create_media_live_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%media_live}}', [
            'id' => $this->primaryKey()->unsigned(),
            'user_id' => $this->integer()->unsigned()->notNull()->comment('Пользователь'),
            'started_at' => $this->dateTime()->notNull()->comment('Начало эфира'),
            'ended_at' => $this->dateTime()->null()->comment('Окончание эфира'),
            'duration_minutes' => $this->integer()->unsigned()->null()->comment('Длительность в минутах'),
            'status' => $this->tinyInteger()->unsigned()->notNull()->defaultValue(1)->comment('1 — идёт, 2 — закончился'),
            'bonus_coins' => $this->integer()->unsigned()->notNull()->defaultValue(0)->comment('Монеты за эфир (30 за каждые 30 мин)'),
            'platform' => $this->string(8)->notNull()->comment('twitch | kick'),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->null(),
        ], self::TABLE_OPTIONS_MB4);

        $this->createIndex('idx_media_live_user_status', '{{%media_live}}', ['user_id', 'status']);
        $this->createIndex('idx_media_live_started_at', '{{%media_live}}', 'started_at');

        $this->addForeignKey(
            'fk_media_live_user',
            '{{%media_live}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_media_live_user', '{{%media_live}}');
        $this->dropTable('{{%media_live}}');
    }
}
