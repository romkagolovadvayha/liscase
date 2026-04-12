<?php

use yii\db\Migration;

/**
 * Связь строки корзины с инвойсом маркета — точный возврат без ошибок при нескольких покупках одного drop_id.
 */
class m260412_120000_add_invoice_id_to_user_drop extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user_drop', 'invoice_id', $this->integer()->null()->comment('invoice.id при покупке с маркета'));
        $this->createIndex('idx-user_drop-invoice_id', 'user_drop', 'invoice_id');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('idx-user_drop-invoice_id', 'user_drop');
        $this->dropColumn('user_drop', 'invoice_id');
    }
}
