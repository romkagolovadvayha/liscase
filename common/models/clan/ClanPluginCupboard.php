<?php

namespace common\models\clan;

use common\components\base\ActiveRecord;
use common\models\servers\Servers;
use Yii;

/**
 * @property int $id
 * @property int $server_id
 * @property string $wipe
 * @property string $entity_id
 * @property string $map_square
 * @property string $placer_steam_id
 * @property int $protected_blocks
 * @property int $main_cupboard 0/1 — главный шкаф клана (max protected_blocks за вайп на сервере)
 * @property int|null $clan_id
 * @property string|null $clan_tag
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Servers $server
 * @property Clan|null $clan
 */
class ClanPluginCupboard extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%clan_plugin_cupboards}}';
    }

    public function rules(): array
    {
        return [
            [['server_id', 'wipe', 'entity_id', 'map_square', 'placer_steam_id', 'created_at', 'updated_at'], 'required'],
            [['server_id', 'protected_blocks', 'main_cupboard', 'created_at', 'updated_at'], 'integer'],
            [['clan_id'], 'integer', 'skipOnEmpty' => true],
            [['wipe'], 'string', 'max' => 64],
            [['entity_id'], 'string', 'max' => 32],
            [['map_square'], 'string', 'max' => 16],
            [['placer_steam_id'], 'string', 'max' => 24],
            [['clan_tag'], 'string', 'max' => 50],
            [['server_id'], 'exist', 'skipOnError' => true, 'targetClass' => Servers::class, 'targetAttribute' => ['server_id' => 'id']],
            [['clan_id'], 'exist', 'skipOnError' => true, 'targetClass' => Clan::class, 'targetAttribute' => ['clan_id' => 'id']],
            [['entity_id', 'server_id', 'wipe'], 'unique', 'targetAttribute' => ['entity_id', 'server_id', 'wipe']],
        ];
    }

    public function getServer()
    {
        return $this->hasOne(Servers::class, ['id' => 'server_id']);
    }

    public function getClan()
    {
        return $this->hasOne(Clan::class, ['id' => 'clan_id']);
    }
}
