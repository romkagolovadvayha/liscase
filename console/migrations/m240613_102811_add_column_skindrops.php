<?php

use yii\db\Migration;

/**
 * Class m240613_102811_add_column_skindrops
 */
class m240613_102811_add_column_skindrops extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user_profile','skindrops_error', 'VARCHAR(255) DEFAULT NULL AFTER trade_link');
        $this->addColumn('user_profile', 'skindrops', 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER trade_link');
    }

}
