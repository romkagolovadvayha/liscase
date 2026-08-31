<?php

use console\components\migration\Migration;
use yii\db\Expression;

/**
 * Adds DB-level provenance for deposit bonuses and stable payment timestamps.
 */
class m260831_060000_harden_deposit_processing extends Migration
{
    public function up()
    {
        $this->addColumn(
            'deposit',
            'completed_at',
            $this->dateTime()->null()->after('created_at')
        );
        $this->update(
            'deposit',
            ['completed_at' => new Expression('created_at')],
            ['status' => 3]
        );

        // created_at describes initiation and must not silently change on
        // payment_id, commission or status updates.
        $this->alterColumn(
            'deposit',
            'created_at',
            'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
        );

        $this->addColumn(
            'profit',
            'deposit_id',
            $this->integer()->null()->after('user_balance_id')
        );

        // Legacy bonuses, including duplicates, deliberately remain NULL and
        // are neither linked nor deleted. MySQL permits multiple NULL values
        // in a unique index, while every future bonus receives deposit_id and
        // is therefore limited to one row per deposit.
        $this->createIndex('uq_profit_deposit_id', 'profit', 'deposit_id', true);
        $this->addForeignKey(
            'fk_profit_deposit_id',
            'profit',
            'deposit_id',
            'deposit',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        // payment_id is TEXT for legacy/manual payment methods, therefore a
        // prefix index is used for webhook lookups.
        $this->execute(
            'CREATE INDEX idx_deposit_callback_lookup'
            . ' ON deposit (payment_type, status, payment_id(64))'
        );
    }

    public function down()
    {
        $this->dropIndex('idx_deposit_callback_lookup', 'deposit');
        $this->dropForeignKey('fk_profit_deposit_id', 'profit');
        $this->dropIndex('uq_profit_deposit_id', 'profit');
        $this->dropColumn('profit', 'deposit_id');
        $this->alterColumn(
            'deposit',
            'created_at',
            'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        );
        $this->dropColumn('deposit', 'completed_at');
    }
}
