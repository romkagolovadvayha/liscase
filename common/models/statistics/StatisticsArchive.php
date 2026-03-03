<?php

namespace common\models\statistics;

/**
 * Архив статистики (прошедшие вайпы).
 * Та же структура, что statistics; запись только через перенос из statistics при смене вайпа.
 *
 * @property int    $id
 * @property string $steam_id
 * @property string $key
 * @property int    $value
 * @property string $server_tag
 * @property string $wipe
 */
class StatisticsArchive extends Statistics
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'statistics_archive';
    }
}
