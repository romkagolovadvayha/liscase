<?php

use console\components\migration\Migration;

/**
 * Class m250528_164524_ServerSkin
 */
class m250528_164524_ServerSkin extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('server_skin', 'creator_user_id', self::INT_FIELD_NOT_NULL);
        $this->addForeignKey('server_skin_creator_user_id', 'server_skin', 'creator_user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250528_164524_ServerSkin cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250528_164524_ServerSkin cannot be reverted.\n";

        return false;
    }
    */
}
