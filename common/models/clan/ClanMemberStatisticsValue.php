<?php

namespace common\models\clan;

use common\components\base\ActiveRecord;

/**
 * Одна метрика вклада участника за вайп (key/value).
 *
 * @property int $id
 * @property int $clan_member_statistics_id
 * @property string $stat_key
 * @property float $value
 *
 * @property ClanMemberStatistics $memberStatistics
 */
class ClanMemberStatisticsValue extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'clan_member_statistics_values';
    }

    public function rules(): array
    {
        return [
            [['clan_member_statistics_id', 'stat_key'], 'required'],
            [['clan_member_statistics_id'], 'integer'],
            [['value'], 'number'],
            [['stat_key'], 'string', 'max' => 80],
            [
                ['clan_member_statistics_id', 'stat_key'],
                'unique',
                'targetAttribute' => ['clan_member_statistics_id', 'stat_key'],
            ],
            [
                ['clan_member_statistics_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => ClanMemberStatistics::class,
                'targetAttribute' => ['clan_member_statistics_id' => 'id'],
            ],
        ];
    }

    public function getMemberStatistics(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ClanMemberStatistics::class, ['id' => 'clan_member_statistics_id']);
    }
}
