<?php

use console\components\migration\Migration;

/**
 * Class m250128_215241_achievmentrs_daily_table
 */
class m250128_215241_achievmentrs_daily_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%achievements_daily}}', [
            'id' => $this->primaryKey(),
            'daily' => self::INT_FIELD_NOT_NULL,
            'drop_id' => self::INT_FIELD_NOT_NULL,
            'amount' => self::INT_FIELD_NOT_NULL,
        ]);

        $this->addForeignKey('achievements_daily_drop_id', 'achievements_daily', 'drop_id',
                             'drop', 'id', 'CASCADE', 'CASCADE');

        $items = [
            [
                'daily' => 1,
                'amount' => 10,
                'drop_id' => 570,
            ],
            [
                'daily' => 2,
                'amount' => 3000,
                'drop_id' => 295
            ],
            [
                'daily' => 3,
                'amount' => 3000,
                'drop_id' => 300
            ],
            [
                'daily' => 4,
                'amount' => 100,
                'drop_id' => 316
            ],
            [
                'daily' => 5,
                'amount' => 1,
                'drop_id' => 868
            ],
            [
                'daily' => 6,
                'amount' => 100,
                'drop_id' => 305
            ],
            [
                'daily' => 7,
                'amount' => 50,
                'drop_id' => 843
            ],
            [
                'daily' => 8,
                'amount' => 2,
                'drop_id' => 203
            ],
            [
                'daily' => 9,
                'amount' => 2,
                'drop_id' => 626
            ],
            [
                'daily' => 10,
                'amount' => 1,
                'drop_id' => 869
            ],
            [
                'daily' => 11,
                'amount' => 1,
                'drop_id' => 867
            ],
            [
                'daily' => 12,
                'amount' => 1,
                'drop_id' => 864
            ],
            [
                'daily' => 13,
                'amount' => 1000,
                'drop_id' => 320
            ],
            [
                'daily' => 14,
                'amount' => 100.00,
                'drop_id' => 843
            ],
        ];

        foreach ($items as $item) {
            $this->insert('achievements_daily', $item);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250128_215241_achievmentrs_daily_table cannot be reverted.\n";
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250128_215241_achievmentrs_daily_table cannot be reverted.\n";

        return false;
    }
    */
}
