<?php

use console\components\migration\Migration;

/**
 * Class m250210_161912_secret_key
 */
class m250210_161912_secret_key extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('servers','secret_key', self::VARCHAR_FIELD);

        $array = [
            [
                'tag' => 'max3',
                'secret_key' => 'ZM0bHN3jc0AWNg2wpJtBRBnFUtGOULZe',
            ],
            [
                'tag' => 'classicx2',
                'secret_key' => 'j0Dqmg8rsgvpAx8wbfHuYqdOqhdBTX40',
            ],
            [
                'tag' => 'nolimit',
                'secret_key' => 'GwJYPuHlnV8ccr9XPsxMVnvDUiAwHhMe',
            ],
            [
                'tag' => 'max8',
                'secret_key' => 'FRING0HACwCo2IZjPQussxpS5JsToyLv',
            ],
            [
                'tag' => 'x10',
                'secret_key' => 'y1Kh9MkrXFo42lUMqgGPvvATAgba6Kmd',
            ],
            [
                'tag' => 'classic14x2',
                'secret_key' => 'q0XJMjQJLc6S11JiufZNYvPoHaANtZl0',
            ],
            [
                'tag' => 'primitive',
                'secret_key' => 'iZDkC9mMcAeXBs3TdYXZPHVvxlYjHSB9',
            ],
        ];

        foreach ($array as $item) {
            /** @var \common\models\servers\Servers $server */
            $server = \common\models\servers\Servers::find()
                ->andWhere(['tag' => $item['tag']])
                ->one();
            if (empty($server)) {
                continue;
            }
            $server->secret_key = $item['secret_key'];
            $server->save();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250210_161912_secret_key cannot be reverted.\n";

    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250210_161912_secret_key cannot be reverted.\n";

        return false;
    }
    */
}
