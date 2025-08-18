<?php

use console\components\migration\Migration;

/**
 * Class m240807_071916_statistics
 */
class m240807_071916_statistics extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%statistics}}', [
            'id'         => self::PRIMARY_KEY,
            'steam_id'   => self::VARCHAR_FIELD,
            'key'        => self::VARCHAR_FIELD,
            'value'      => self::INT_FIELD,
            'server_tag' => self::VARCHAR_FIELD,
            'wipe'       => self::VARCHAR_FIELD,
        ]);
        $this->createTable('{{%statistics_teams}}', [
            'id'          => self::PRIMARY_KEY,
            'steam_id'    => self::VARCHAR_FIELD,
            'type'        => self::VARCHAR_FIELD,
            'team_author' => self::VARCHAR_FIELD,
            'created_at'  => self::VARCHAR_FIELD,
            'server_tag'  => self::VARCHAR_FIELD,
            'wipe'        => self::VARCHAR_FIELD,
        ]);
        $this->createTable('{{%statistics_kills}}', [
            'id'         => self::PRIMARY_KEY,
            'steam_id'   => self::VARCHAR_FIELD,
            'type'       => self::VARCHAR_FIELD,
            'dead'       => self::VARCHAR_FIELD,
            'weapon'     => self::VARCHAR_FIELD,
            'distance'   => self::INT_FIELD,
            'created_at' => self::VARCHAR_FIELD,
            'server_tag' => self::VARCHAR_FIELD,
            'wipe'       => self::VARCHAR_FIELD,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m240807_071916_statistics cannot be reverted.\n";

        return false;
    }

}
