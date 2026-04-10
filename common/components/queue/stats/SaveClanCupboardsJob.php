<?php

namespace common\components\queue\stats;

use common\helpers\RustMapGridHelper;
use common\models\clan\Clan;
use common\models\clan\ClanPluginCupboard;
use common\models\servers\Servers;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

/**
 * Ingest ClanCupboardReporter: upsert по entity_id + server_id + wipe;
 * флаг main_cupboard пересчитывается в очереди по max(protected_blocks) на клан за вайп.
 */
class SaveClanCupboardsJob extends BaseObject implements JobInterface
{
    public $data;
    public $ip;

    /**
     * @param \yii\queue\Queue $queue
     */
    public function execute($queue)
    {
        try {
            $request = json_decode($this->data, true);
            if (!is_array($request)) {
                return;
            }
            $reqIp = $request['ip'] ?? '';
            if ($this->ip !== $reqIp) {
                return;
            }

            $server = Servers::find()
                ->cache(60)
                ->andWhere(['ip' => $request['ip']])
                ->andWhere(['port' => $request['port']])
                ->one();
            if (empty($server)) {
                return;
            }

            $wipe = isset($request['wipe']) && is_string($request['wipe']) && $request['wipe'] !== ''
                ? $request['wipe']
                : $server->currentWipe();

            $worldSize = isset($request['world_size']) ? (int) $request['world_size'] : 0;
            if ($worldSize <= 0) {
                $worldSize = (int) $server->max_map_size;
            }
            if ($worldSize <= 0) {
                $worldSize = 4500;
            }

            $clanIdByTag = [];
            $resolveClanId = static function (string $tag) use ($server, &$clanIdByTag): ?int {
                if ($tag === '') {
                    return null;
                }
                if (!array_key_exists($tag, $clanIdByTag)) {
                    $row = Clan::find()
                        ->select(['id'])
                        ->where(['server_id' => $server->id, 'tag' => $tag])
                        ->asArray()
                        ->one();
                    $clanIdByTag[$tag] = $row ? (int) $row['id'] : null;
                }

                return $clanIdByTag[$tag];
            };

            $flat = [];
            foreach ($request['clans'] ?? [] as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $tag = isset($c['tag']) ? (string) $c['tag'] : '';
                $clanId = $resolveClanId($tag);

                if (!empty($c['cupboards']) && is_array($c['cupboards'])) {
                    foreach ($c['cupboards'] as $cup) {
                        if (!is_array($cup)) {
                            continue;
                        }
                        $flat[] = [
                            'cup' => $cup,
                            'clan_id' => $clanId,
                            'clan_tag' => $tag !== '' ? $tag : null,
                        ];
                    }
                } elseif (!empty($c['main_cupboard']) && is_array($c['main_cupboard'])) {
                    $flat[] = [
                        'cup' => $c['main_cupboard'],
                        'clan_id' => $clanId,
                        'clan_tag' => $tag !== '' ? $tag : null,
                    ];
                }
            }

            foreach ($request['unassigned_cupboards'] ?? [] as $cup) {
                if (!is_array($cup)) {
                    continue;
                }
                $flat[] = [
                    'cup' => $cup,
                    'clan_id' => null,
                    'clan_tag' => null,
                ];
            }

            $now = time();
            $trx = Yii::$app->db->beginTransaction();
            try {
                foreach ($flat as $item) {
                    $this->upsertOne(
                        $server->id,
                        $wipe,
                        $item['cup'],
                        $item['clan_id'],
                        $item['clan_tag'],
                        $worldSize,
                        $now
                    );
                }
                $this->recomputeMainCupboards($server->id, $wipe, $now);
                $trx->commit();
            } catch (\Throwable $e) {
                $trx->rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Yii::error('SaveClanCupboardsJob: ' . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * Главный шкаф клана: максимум protected_blocks среди всех строк с тем же server_id, wipe и clan_id
     * (или тем же clan_tag при clan_id IS NULL). Остальные main_cupboard = 0.
     */
    private function recomputeMainCupboards(int $serverId, string $wipe, int $now): void
    {
        ClanPluginCupboard::updateAll(
            ['main_cupboard' => 0, 'updated_at' => $now],
            ['server_id' => $serverId, 'wipe' => $wipe]
        );

        $clanIds = ClanPluginCupboard::find()
            ->select('clan_id')
            ->where(['server_id' => $serverId, 'wipe' => $wipe])
            ->andWhere(['not', ['clan_id' => null]])
            ->distinct()
            ->column();

        foreach ($clanIds as $cid) {
            $cid = (int) $cid;
            if ($cid <= 0) {
                continue;
            }
            $winner = ClanPluginCupboard::find()
                ->select(['id'])
                ->where(['server_id' => $serverId, 'wipe' => $wipe, 'clan_id' => $cid])
                ->orderBy(['protected_blocks' => SORT_DESC, 'entity_id' => SORT_ASC])
                ->asArray()
                ->one();
            if ($winner !== null) {
                ClanPluginCupboard::updateAll(
                    ['main_cupboard' => 1, 'updated_at' => $now],
                    ['id' => (int) $winner['id']]
                );
            }
        }

        $tags = ClanPluginCupboard::find()
            ->select('clan_tag')
            ->where(['server_id' => $serverId, 'wipe' => $wipe])
            ->andWhere(['clan_id' => null])
            ->andWhere(['not', ['clan_tag' => null]])
            ->andWhere(['!=', 'clan_tag', ''])
            ->distinct()
            ->column();

        foreach ($tags as $tag) {
            $winner = ClanPluginCupboard::find()
                ->select(['id'])
                ->where([
                    'server_id' => $serverId,
                    'wipe' => $wipe,
                    'clan_id' => null,
                    'clan_tag' => $tag,
                ])
                ->orderBy(['protected_blocks' => SORT_DESC, 'entity_id' => SORT_ASC])
                ->asArray()
                ->one();
            if ($winner !== null) {
                ClanPluginCupboard::updateAll(
                    ['main_cupboard' => 1, 'updated_at' => $now],
                    ['id' => (int) $winner['id']]
                );
            }
        }
    }

    private function upsertOne(
        int $serverId,
        string $wipe,
        array $cup,
        ?int $clanId,
        ?string $clanTag,
        int $worldSize,
        int $now
    ): void {
        $entityId = isset($cup['entity_id']) ? trim((string) $cup['entity_id']) : '';
        if ($entityId === '' || strlen($entityId) > 32) {
            return;
        }

        $mapSquare = isset($cup['map_square']) ? trim((string) $cup['map_square']) : '';
        if ($mapSquare === '' && isset($cup['x'], $cup['z']) && $worldSize > 0) {
            $mapSquare = RustMapGridHelper::positionToSquare((float) $cup['x'], (float) $cup['z'], $worldSize);
        }
        if ($mapSquare === '' || strlen($mapSquare) > 16) {
            return;
        }

        $placer = isset($cup['placer_steam_id']) ? preg_replace('/\D/', '', (string) $cup['placer_steam_id']) : '';
        if ($placer === '' || strlen($placer) > 24) {
            return;
        }

        $protected = isset($cup['protected_blocks']) ? (int) $cup['protected_blocks'] : 0;
        if ($protected < 0) {
            $protected = 0;
        }

        $g = isset($cup['protected_blocks_by_grade']) && is_array($cup['protected_blocks_by_grade'])
            ? $cup['protected_blocks_by_grade']
            : [];
        $blocksTwigs = max(0, (int) ($g['twigs'] ?? 0));
        $blocksWood = max(0, (int) ($g['wood'] ?? 0));
        $blocksStone = max(0, (int) ($g['stone'] ?? 0));
        $blocksMetal = max(0, (int) ($g['metal'] ?? 0));
        $blocksHqm = max(0, (int) ($g['hqm'] ?? 0));
        $sumGrades = $blocksTwigs + $blocksWood + $blocksStone + $blocksMetal + $blocksHqm;
        if ($sumGrades > 0) {
            $protected = $sumGrades;
        }

        $model = ClanPluginCupboard::find()
            ->where([
                'entity_id' => $entityId,
                'server_id' => $serverId,
                'wipe' => $wipe,
            ])
            ->one();

        if ($model === null) {
            $model = new ClanPluginCupboard();
            $model->server_id = $serverId;
            $model->wipe = $wipe;
            $model->entity_id = $entityId;
            $model->created_at = $now;
        }

        $model->map_square = $mapSquare;
        $model->placer_steam_id = $placer;
        $model->protected_blocks = $protected;
        $model->blocks_twigs = $blocksTwigs;
        $model->blocks_wood = $blocksWood;
        $model->blocks_stone = $blocksStone;
        $model->blocks_metal = $blocksMetal;
        $model->blocks_hqm = $blocksHqm;
        $model->score = ClanPluginCupboard::computeBaseScore($blocksHqm, $blocksMetal, $blocksStone, $blocksWood);
        $model->main_cupboard = 0;
        $model->clan_id = $clanId;
        $model->clan_tag = $clanTag;
        $model->updated_at = $now;

        if (!$model->save(false)) {
            Yii::warning('SaveClanCupboardsJob save failed entity ' . $entityId, __METHOD__);
        }
    }
}
