<?php

use yii\db\Migration;

/**
 * Добавляет поддержку одноразовых промокодов: is_single_use, бессрочные (finished_at nullable).
 */
class m260220_150000_add_promocode_single_use extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('promocode', 'is_single_use', $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('1 = одноразовый, после ввода неактивен'));
        $this->alterColumn('promocode', 'finished_at', $this->dateTime()->null()->comment('NULL = бессрочный'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->alterColumn('promocode', 'finished_at', $this->dateTime()->notNull());
        $this->dropColumn('promocode', 'is_single_use');
    }
}
