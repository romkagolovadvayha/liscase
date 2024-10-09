<?php

namespace common\models\statistics;

use common\components\base\ActiveRecord;

/**
 * @property int    $id
 * @property string $steam_id
 * @property string $server
 * @property string $reason
 * @property string $data3
 *
 */
class JokerRust extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'jokerrust';
    }
}
