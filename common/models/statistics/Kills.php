<?php

namespace common\models\statistics;

use common\components\base\ActiveRecord;
use common\components\google\TranslateApi;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;

/**
 * @property int    $id
 * @property string $steam_id
 * @property string $type
 * @property string $dead
 * @property string $weapon
 * @property string $distance
 * @property string $created_at
 * @property string $server_tag
 * @property string $wipe
 */
class Kills extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'statistics_kills';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'         => Yii::t('common', 'ID'),
            'steam_id'   => Yii::t('common', 'Steam ID'),
            'type'   => Yii::t('common', 'Тип'),
            'dead'       => Yii::t('common', 'Противник'),
            'weapon'      => Yii::t('common', 'Оружие'),
            'distance'     => Yii::t('common', 'Дистанция'),
            'created_at' => Yii::t('common', 'Дата'),
        ];
    }

}
