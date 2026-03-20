<?php

namespace common\models\clan;

use common\components\base\ActiveRecord;
use Yii;

/**
 * Значение одной метрики статистики клана (key/value).
 *
 * @property int $id
 * @property int $clan_statistics_id
 * @property string $stat_key
 * @property float $value
 *
 * @property ClanStatistics $clanStatistics
 */
class ClanStatisticsValue extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'clan_statistics_values';
    }

    public function rules(): array
    {
        return [
            [['clan_statistics_id', 'stat_key'], 'required'],
            [['clan_statistics_id'], 'integer'],
            [['value'], 'number'],
            [['stat_key'], 'string', 'max' => 80],
            [
                ['clan_statistics_id', 'stat_key'],
                'unique',
                'targetAttribute' => ['clan_statistics_id', 'stat_key'],
            ],
            [
                ['clan_statistics_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => ClanStatistics::class,
                'targetAttribute' => ['clan_statistics_id' => 'id'],
            ],
        ];
    }

    public function getClanStatistics(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ClanStatistics::class, ['id' => 'clan_statistics_id']);
    }
}
