<?php

namespace common\models\statistics;

use common\components\base\ActiveRecord;

/**
 * @property int    $id
 * @property string $steamID
 * @property string $reason
 * @property string $banTime
 *
 */
class Moskov77 extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'moskov77';
    }
}
