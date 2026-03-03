<?php

use yii\db\Migration;

/**
 * Добавляет поля FTP (хост, порт, логин, пароль) в таблицу servers.
 */
class m260303_120000_add_ftp_fields_to_servers_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $schema = $this->db->schema->getTableSchema('{{%servers}}');
        if ($schema && $schema->getColumn('ftp_login')) {
            return;
        }
        $this->addColumn('{{%servers}}', 'ftp_host', $this->string(255)->null()->comment('FTP хост (если пусто — используется IP сервера)'));
        $this->addColumn('{{%servers}}', 'ftp_port', $this->smallInteger()->notNull()->defaultValue(21)->comment('FTP порт'));
        $this->addColumn('{{%servers}}', 'ftp_login', $this->string(255)->null()->comment('FTP логин'));
        $this->addColumn('{{%servers}}', 'ftp_password', $this->string(255)->null()->comment('FTP пароль'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $schema = $this->db->schema->getTableSchema('{{%servers}}');
        if (!$schema || !$schema->getColumn('ftp_login')) {
            return;
        }
        $this->dropColumn('{{%servers}}', 'ftp_host');
        $this->dropColumn('{{%servers}}', 'ftp_port');
        $this->dropColumn('{{%servers}}', 'ftp_login');
        $this->dropColumn('{{%servers}}', 'ftp_password');
    }
}
