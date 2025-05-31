<?php

use console\components\migration\Migration;

/**
 * Class m250420_130405_drop_set
 */
class m250420_130405_drop_set extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('drop','drop_type', self::TINYINT_FIELD);

        /** @var \common\models\box\Drop[] $drops */
        $drops = \common\models\box\Drop::find()->all();
        foreach ($drops as $drop) {
            $drop->drop_type = \common\models\box\Drop::TYPE_DROP;
            if (!empty($drop->subDrops)) {
                $drop->drop_type = \common\models\box\Drop::TYPE_SET;
            }
            if (!empty($drop->command)) {
                $drop->drop_type = \common\models\box\Drop::TYPE_COMMAND;
            }
            $drop->save();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250420_130405_drop_set cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250420_130405_drop_set cannot be reverted.\n";

        return false;
    }
    */
}
