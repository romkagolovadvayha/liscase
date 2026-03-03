<?php

use yii\db\Migration;

/**
 * Добавляет поле корневого каталога FTP в таблицу servers.
 */
class m260303_140000_add_ftp_root_path_to_servers_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $schema = $this->db->schema->getTableSchema('{{%servers}}');
        if ($schema && $schema->getColumn('ftp_root_path')) {
            return;
        }
        $this->addColumn('{{%servers}}', 'ftp_root_path', $this->string(500)->null()->comment('Корневой каталог FTP (если задан — в менеджере отображается как /)'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $schema = $this->db->schema->getTableSchema('{{%servers}}');
        if (!$schema || !$schema->getColumn('ftp_root_path')) {
            return;
        }
        $this->dropColumn('{{%servers}}', 'ftp_root_path');
    }
}
