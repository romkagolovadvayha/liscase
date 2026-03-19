<?php

use yii\db\Migration;

/**
 * Добавляет день недели вайпа в таблицу servers (1=Пн..7=Вс, по умолчанию 5=Пятница).
 */
class m260319_120000_add_wipe_weekday_to_servers_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $schema = $this->db->schema->getTableSchema('{{%servers}}');
        if ($schema && $schema->getColumn('wipe_weekday')) {
            return;
        }
        $this->addColumn('{{%servers}}', 'wipe_weekday', $this->tinyInteger(1)->notNull()->defaultValue(5)->comment('День недели вайпа: 1=Пн..7=Вс'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $schema = $this->db->schema->getTableSchema('{{%servers}}');
        if (!$schema || !$schema->getColumn('wipe_weekday')) {
            return;
        }
        $this->dropColumn('{{%servers}}', 'wipe_weekday');
    }
}
