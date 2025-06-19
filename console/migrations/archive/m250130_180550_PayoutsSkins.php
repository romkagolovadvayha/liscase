<?php

use console\components\migration\Migration;

/**
 * Class m250130_180550_PayoutsSkins
 */
class m250130_180550_PayoutsSkins extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('user_payout_skins', [
            'id'         => self::PRIMARY_KEY,
            'user_id'    => self::INT_FIELD_NOT_NULL,
            'amount'     => 'DECIMAL(14,2) UNSIGNED NOT NULL DEFAULT 0',
            'skin_id'     => self::VARCHAR_FIELD,
            'status'     => self::TINYINT_1_FIELD,
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->addForeignKey('user_payout_skins_user_id', 'user_payout_skins', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');

        $this->addColumn('user_payout_skins', 'name', self::VARCHAR_FIELD);
        $this->addColumn('user_payout_skins', 'image', self::VARCHAR_FIELD);
        $this->addColumn('user_payout_skins', 'image300', self::VARCHAR_FIELD);
        $this->addColumn('user_payout_skins', 'price', 'DECIMAL(14,2) UNSIGNED NOT NULL DEFAULT 0');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250130_180550_PayoutsSkins cannot be reverted.\n";

//        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250130_180550_PayoutsSkins cannot be reverted.\n";

        return false;
    }
    */
}
