<?php

use console\components\migration\Migration;

/**
 * Кэш страны пользователя по IP, чтобы не делать GeoIP lookup на каждый запрос.
 */
class m260323_130000_add_ip_country_cache_to_user extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%user}}', 'ip_country_code', $this->string(2)->null()->comment('Код страны по IP (ISO-3166-1 alpha-2)'));
        $this->addColumn('{{%user}}', 'ip_country_source_ip', $this->string(45)->null()->comment('IP, для которого рассчитан ip_country_code'));
        $this->addColumn('{{%user}}', 'ip_country_updated_at', $this->integer()->null()->comment('UNIX-время обновления кэша страны'));

        $this->createIndex('idx_user_ip_country_code', '{{%user}}', 'ip_country_code');
    }

    public function safeDown()
    {
        $this->dropIndex('idx_user_ip_country_code', '{{%user}}');
        $this->dropColumn('{{%user}}', 'ip_country_updated_at');
        $this->dropColumn('{{%user}}', 'ip_country_source_ip');
        $this->dropColumn('{{%user}}', 'ip_country_code');
    }
}

