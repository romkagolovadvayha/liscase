<?php

use yii\db\Migration;

/**
 * Добавляет тип настройки password (секретное значение, не отдаётся в публичном API).
 */
class m260330_120000_add_password_site_setting_type extends Migration
{
    public function safeUp()
    {
        $this->execute("ALTER TABLE {{%site_settings}} CHANGE COLUMN `type` `type` ENUM('text', 'color', 'video', 'file', 'image', 'number', 'checkbox', 'radio', 'longtext', 'password') NOT NULL");
    }

    public function safeDown()
    {
        echo "m260330_120000_add_password_site_setting_type: откат невозможен, если есть записи с type=password.\n";

        return false;
    }
}
