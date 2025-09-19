<?php

namespace common\models\clan;

use common\models\servers\Servers;
use Yii;

/**
 * This is the model class for table "clan_stats".
 *
 * @property int         $id
 * @property int|null    $clan_id
 * @property int|null    $raid_score
 * @property int|null    $kill_score
 * @property int|null    $scrap
 * @property int|null    $sulfur_ore
 * @property int|null    $helicopter
 * @property int|null    $tugboat
 * @property int|null    $bradley
 * @property int|null    $server_id
 * @property string|null $wipe
 * @property string|null $updated_at
 * @property string|null $created_at
 *
 * @property Servers     $server
 * @property Clan    $clan
 */
class ClanStats extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'clan_stats';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['clan_id', 'raid_score', 'kill_score', 'server_id'], 'integer'],
            [['updated_at', 'created_at'], 'safe'],
            [['wipe'], 'string', 'max' => 30],

            // связи
            [['server_id'], 'exist', 'skipOnError' => true, 'targetClass' => Servers::class, 'targetAttribute' => ['server_id' => 'id']],
            [['clan_id'], 'exist', 'skipOnError' => true, 'targetClass' => Clan::class, 'targetAttribute' => ['clan_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id'         => 'ID',
            'clan_id'    => 'Клан',
            'raid_score' => 'Очки за рейды',
            'kill_score' => 'Очки за убийства',
            'server_id'  => 'Сервер',
            'wipe'       => 'Вайп',
            'updated_at' => 'Дата обновления',
            'created_at' => 'Дата создания',
        ];
    }

    /**
     * Gets query for [[Server]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServer()
    {
        return $this->hasOne(Servers::class, ['id' => 'server_id']);
    }

    /**
     * Gets query for [[Clan]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getClan()
    {
        return $this->hasOne(Clan::class, ['id' => 'clan_id']);
    }
}
