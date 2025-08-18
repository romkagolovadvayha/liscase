<?php

use console\components\migration\Migration;

/**
 * Class m240912_053914_payout_refferal
 */
class m240912_053914_payout_refferal extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
                $this->createTable('user_payout_referral', [
                    'id'         => self::PRIMARY_KEY,
                    'user_id'    => self::INT_FIELD_NOT_NULL,
                    'amount'     => 'DECIMAL(14,2) UNSIGNED NOT NULL DEFAULT 0',
                    'created_at' => self::TIMESTAMP_FIELD,
                ], self::TABLE_OPTIONS);

                $this->addForeignKey('user_payout_referral_user_id', 'user_payout_referral', 'user_id',
                                     'user', 'id', 'CASCADE', 'CASCADE');


        $this->addColumn('user','parent_skin_send', 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 1');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m240912_053914_payout_refferal cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m240912_053914_payout_refferal cannot be reverted.\n";

        return false;
    }
    */
}
