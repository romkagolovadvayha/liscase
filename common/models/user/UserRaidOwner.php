<?php

namespace common\models\user;

/**
 * Нормализованные владельцы рейда (Steam ID) для быстрых выборок по индексу.
 * Колонка [[UserRaid::owners]] (JSON) дублируется при записи для совместимости с API.
 *
 * @property int $id
 * @property int $user_raid_id
 * @property string $steam_id Steam64, только цифры (длина ≥ 17)
 *
 * @property-read UserRaid $raid
 */
class UserRaidOwner extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_raid_owner';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_raid_id', 'steam_id'], 'required'],
            [['user_raid_id'], 'integer'],
            [['steam_id'], 'string', 'max' => 32],
            [['user_raid_id'], 'exist', 'skipOnError' => true, 'targetClass' => UserRaid::class, 'targetAttribute' => ['user_raid_id' => 'id']],
        ];
    }

    public function getRaid()
    {
        return $this->hasOne(UserRaid::class, ['id' => 'user_raid_id']);
    }

    /**
     * Steam64: только цифры, минимум 17 знаков.
     */
    public static function normalizeSteamId($raw): ?string
    {
        $digits = preg_replace('/\D/', '', (string)$raw);

        return strlen($digits) >= 17 ? $digits : null;
    }

    /**
     * Извлекает список нормализованных steam_id из колонки owners (JSON или «ломаный» текст).
     *
     * @return string[]
     */
    public static function steamIdsFromOwnersColumn(?string $owners): array
    {
        if ($owners === null || $owners === '') {
            return [];
        }
        $decoded = json_decode($owners, true);
        if (is_array($decoded)) {
            $out = [];
            foreach ($decoded as $v) {
                $n = static::normalizeSteamId((string)$v);
                if ($n !== null) {
                    $out[$n] = true;
                }
            }

            return array_keys($out);
        }
        if (preg_match_all('/\d{17,}/', $owners, $m)) {
            $out = [];
            foreach ($m[0] as $hit) {
                $n = static::normalizeSteamId($hit);
                if ($n !== null) {
                    $out[$n] = true;
                }
            }

            return array_keys($out);
        }

        return [];
    }

    /**
     * Заменяет строки владельцев для рейда (после сохранения [[UserRaid]]).
     *
     * @param array<int|string|float> $rawSteamIds как приходит из плагина / JSON
     */
    public static function replaceForRaid(int $userRaidId, array $rawSteamIds): void
    {
        static::deleteAll(['user_raid_id' => $userRaidId]);
        $seen = [];
        $rows = [];
        foreach ($rawSteamIds as $raw) {
            $sid = static::normalizeSteamId((string)$raw);
            if ($sid === null || isset($seen[$sid])) {
                continue;
            }
            $seen[$sid] = true;
            $rows[] = [$userRaidId, $sid];
        }
        if ($rows === []) {
            return;
        }
        $db = static::getDb();
        $table = static::tableName();
        foreach (array_chunk($rows, 250) as $chunk) {
            $db->createCommand()->batchInsert($table, ['user_raid_id', 'steam_id'], $chunk)->execute();
        }
    }
}
