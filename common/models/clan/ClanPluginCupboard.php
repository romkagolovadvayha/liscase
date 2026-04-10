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
 * @property int $blocks_twigs
 * @property int $blocks_wood
 * @property int $blocks_stone
 * @property int $blocks_metal
 * @property int $blocks_hqm
 * @property int $score Очки за одну базу: ceil((МВК×15 + железо×4 + камень×2 + дерево×0.2) / 100)
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
            [['server_id', 'protected_blocks', 'blocks_twigs', 'blocks_wood', 'blocks_stone', 'blocks_metal', 'blocks_hqm', 'score', 'main_cupboard', 'created_at', 'updated_at'], 'integer'],
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

    /**
     * Очки за одну базу (солома в формулу не входит).
     *
     * ceil((МВК × 15 + железо × 4 + камень × 2 + дерево × 0.2) / 100)
     */
    public static function computeBaseScore(int $blocksHqm, int $blocksMetal, int $blocksStone, int $blocksWood): int
    {
        $blocksHqm = max(0, $blocksHqm);
        $blocksMetal = max(0, $blocksMetal);
        $blocksStone = max(0, $blocksStone);
        $blocksWood = max(0, $blocksWood);
        $numerator = $blocksHqm * 15 + $blocksMetal * 4 + $blocksStone * 2 + $blocksWood * 0.2;

        return (int) ceil($numerator / 100.0);
    }
}
