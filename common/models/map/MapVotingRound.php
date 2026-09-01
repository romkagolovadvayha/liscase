<?php

namespace common\models\map;

use common\models\servers\Servers;
use Yii;

/**
 * One isolated map vote for one server and one target wipe.
 *
 * @property int $id
 * @property int $server_id
 * @property string $target_wipe_at
 * @property bool $is_staging
 * @property int|null $save_version
 * @property string $status
 * @property int|null $winning_map_list_id
 * @property string $created_at
 * @property string|null $opened_at
 * @property string|null $fixed_at
 */
class MapVotingRound extends \yii\db\ActiveRecord
{
    public const STATUS_GENERATING = 'generating';
    public const STATUS_OPEN = 'open';
    public const STATUS_FIXED = 'fixed';
    public const STATUS_SUPERSEDED = 'superseded';
    public const STATUS_FAILED = 'failed';

    public static function tableName()
    {
        return 'map_voting_round';
    }

    public function rules()
    {
        return [
            [['server_id', 'target_wipe_at', 'status'], 'required'],
            [['server_id', 'save_version', 'winning_map_list_id'], 'integer'],
            [['is_staging'], 'boolean'],
            [['target_wipe_at', 'created_at', 'opened_at', 'fixed_at'], 'safe'],
            [['status'], 'in', 'range' => [
                self::STATUS_GENERATING,
                self::STATUS_OPEN,
                self::STATUS_FIXED,
                self::STATUS_SUPERSEDED,
                self::STATUS_FAILED,
            ]],
        ];
    }

    public function getServer()
    {
        return $this->hasOne(Servers::class, ['id' => 'server_id']);
    }

    public function getCandidates()
    {
        return $this->hasMany(MapList::class, ['id' => 'map_list_id'])
            ->viaTable(MapVotingRoundMap::tableName(), ['round_id' => 'id']);
    }

    public function getCandidateLinks()
    {
        return $this->hasMany(MapVotingRoundMap::class, ['round_id' => 'id']);
    }

    public function getVotes()
    {
        return $this->hasMany(MapListVote::class, ['round_id' => 'id']);
    }

    public static function targetWipeAt(Servers $server): string
    {
        $value = $server->getFactNextWipe() ?: $server->next_wipe;
        $timestamp = $value ? strtotime($value) : false;

        if ($timestamp === false) {
            throw new \RuntimeException("Не задана корректная дата следующего вайпа для сервера {$server->tag}");
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * RustMaps staging is the upcoming force-wipe protocol. A map vote for a
     * regular wipe must stay on stable; a vote targeting the configured global
     * wipe must use staging while that wipe is still in the future.
     */
    public static function shouldUseStaging(Servers $server): bool
    {
        $nextWipe = $server->getFactNextWipe() ?: $server->next_wipe;
        $globalWipe = $server->getFactGlobalWipe() ?: $server->global_wipe;
        $nextTimestamp = $nextWipe ? strtotime($nextWipe) : false;
        $globalTimestamp = $globalWipe ? strtotime($globalWipe) : false;

        if ($nextTimestamp === false || $globalTimestamp === false) {
            // Compatibility fallback for installations that have not configured
            // the wipe calendar yet. Once dates exist, the calendar is the
            // source of truth so a stale toggle cannot poison a weekly vote.
            return (bool)Yii::$app->settings->get('maps_staging');
        }

        if ($nextTimestamp <= time()) {
            return false;
        }

        return date('Y-m-d', $nextTimestamp) === date('Y-m-d', $globalTimestamp);
    }

    /**
     * Returns or creates the round that a generation job must populate.
     */
    public static function prepareForServer(Servers $server): self
    {
        $targetWipeAt = self::targetWipeAt($server);
        if (strtotime($targetWipeAt) <= time()) {
            throw new \RuntimeException(
                "Дата следующего вайпа сервера {$server->tag} устарела ({$targetWipeAt}); генерация остановлена"
            );
        }
        $isStaging = self::shouldUseStaging($server);

        $round = self::find()
            ->where([
                'server_id' => $server->id,
                'target_wipe_at' => $targetWipeAt,
                'is_staging' => $isStaging,
            ])
            ->andWhere(['status' => [self::STATUS_GENERATING, self::STATUS_OPEN]])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if ($round) {
            return $round;
        }

        self::updateAll(
            ['status' => self::STATUS_SUPERSEDED],
            [
                'server_id' => $server->id,
                'status' => [self::STATUS_GENERATING, self::STATUS_OPEN],
            ]
        );

        $round = new self([
            'server_id' => $server->id,
            'target_wipe_at' => $targetWipeAt,
            'is_staging' => $isStaging,
            'status' => self::STATUS_GENERATING,
        ]);

        if (!$round->save()) {
            throw new \RuntimeException('Не удалось создать тур голосования: ' . json_encode($round->errors, JSON_UNESCAPED_UNICODE));
        }

        return $round;
    }

    public static function getOpenForServer(int $serverId): ?self
    {
        return self::find()
            ->where(['server_id' => $serverId, 'status' => self::STATUS_OPEN])
            ->orderBy(['id' => SORT_DESC])
            ->one();
    }

    public static function getLatestForServer(int $serverId): ?self
    {
        return self::find()
            ->where(['server_id' => $serverId])
            ->orderBy(['id' => SORT_DESC])
            ->one();
    }

    public function addCandidate(MapList $map, int $position): bool
    {
        if ($this->save_version === null) {
            $this->save_version = $map->save_version;
            $this->save(false, ['save_version']);
        }

        if (
            $this->save_version !== null
            && $map->save_version !== null
            && (int)$this->save_version !== (int)$map->save_version
        ) {
            Yii::warning(
                "Map {$map->id} protocol {$map->save_version} rejected from round {$this->id} protocol {$this->save_version}",
                __METHOD__
            );
            return false;
        }

        if ((bool)$map->is_staging !== (bool)$this->is_staging) {
            Yii::warning(
                "Map {$map->id} staging flag does not match round {$this->id}",
                __METHOD__
            );
            return false;
        }

        $link = MapVotingRoundMap::findOne([
            'round_id' => $this->id,
            'map_list_id' => $map->id,
        ]);
        if ($link) {
            return true;
        }

        $link = new MapVotingRoundMap([
            'round_id' => $this->id,
            'map_list_id' => $map->id,
            'position' => $position,
        ]);

        return $link->save();
    }

    public function open(): bool
    {
        if (!$this->getCandidateLinks()->exists() || $this->save_version === null) {
            $this->status = self::STATUS_FAILED;
            return $this->save(false, ['status']);
        }

        $this->status = self::STATUS_OPEN;
        $this->opened_at = date('Y-m-d H:i:s');
        return $this->save(false, ['status', 'opened_at']);
    }

    public function containsMap(int $mapId): bool
    {
        return $this->getCandidateLinks()->andWhere(['map_list_id' => $mapId])->exists();
    }

    /**
     * @return array{status:string,map:?MapList,votes:int,tiedMapIds:array<int>}
     */
    public function result(): array
    {
        $rows = MapVotingRoundMap::find()
            ->alias('rm')
            ->select([
                'rm.map_list_id',
                'candidate_position' => 'MIN(rm.position)',
                'vote_count' => 'COUNT(v.id)',
            ])
            ->leftJoin(
                MapListVote::tableName() . ' v',
                'v.round_id = rm.round_id AND v.map_list_id = rm.map_list_id'
                . ' AND v.server_id = ' . (int)$this->server_id
            )
            ->where(['rm.round_id' => $this->id])
            ->groupBy('rm.map_list_id')
            ->orderBy(['vote_count' => SORT_DESC, 'candidate_position' => SORT_ASC, 'rm.map_list_id' => SORT_ASC])
            ->asArray()
            ->all();

        if (!$rows || (int)$rows[0]['vote_count'] === 0) {
            return ['status' => 'no_votes', 'map' => null, 'votes' => 0, 'tiedMapIds' => []];
        }

        $maxVotes = (int)$rows[0]['vote_count'];
        $topIds = [];
        foreach ($rows as $row) {
            if ((int)$row['vote_count'] !== $maxVotes) {
                break;
            }
            $topIds[] = (int)$row['map_list_id'];
        }

        if (count($topIds) !== 1) {
            return ['status' => 'tie', 'map' => null, 'votes' => $maxVotes, 'tiedMapIds' => $topIds];
        }

        return [
            'status' => 'winner',
            'map' => MapList::findOne($topIds[0]),
            'votes' => $maxVotes,
            'tiedMapIds' => [],
        ];
    }

    public function fixMap(MapList $map): bool
    {
        if (!$this->containsMap((int)$map->id)) {
            return false;
        }

        $server = Servers::findOne($this->server_id);
        if (!$server || !$map->isValidForServer($server)) {
            return false;
        }
        if (
            (int)$map->save_version !== (int)$this->save_version
            || (bool)$map->is_staging !== (bool)$this->is_staging
        ) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $server->map_list_id = $map->id;
            if (!$server->save(false, ['map_list_id'])) {
                throw new \RuntimeException('Не удалось назначить карту серверу');
            }

            $this->winning_map_list_id = $map->id;
            $this->status = self::STATUS_FIXED;
            $this->fixed_at = date('Y-m-d H:i:s');
            if (!$this->save(false, ['winning_map_list_id', 'status', 'fixed_at'])) {
                throw new \RuntimeException('Не удалось закрыть тур голосования');
            }

            $transaction->commit();
            return true;
        } catch (\Throwable $throwable) {
            $transaction->rollBack();
            Yii::error('Map round fixation failed: ' . $throwable->getMessage(), __METHOD__);
            return false;
        }
    }
}
