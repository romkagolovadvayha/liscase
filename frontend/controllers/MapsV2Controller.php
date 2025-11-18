<?php

namespace frontend\controllers;

use common\helpers\MapLocalization;
use common\models\map\MapList;
use common\models\map\MapListVote;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use frontend\assets\MapsV2Asset;
use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class MapsV2Controller extends Controller
{
    public function actionIndex($serverTag = null, $mapId = null)
    {
        MapsV2Asset::register($this->view);

        /** @var Servers[] $servers */
        $servers = Servers::find()
            ->cache(60)
            ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
            ->andWhere(['secret_map' => 0])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        if (empty($servers)) {
            throw new NotFoundHttpException(Yii::t('common', 'Серверы не найдены'));
        }

        $server = null;
        foreach ($servers as $item) {
            if ($serverTag !== null && $item->tag === $serverTag) {
                $server = $item;
                break;
            }
        }

        if ($server === null) {
            $server = $servers[0];
            if ($serverTag !== null) {
                throw new NotFoundHttpException(Yii::t('common', 'Сервер не найден!'));
            }
        }

        $this->view->params['page'] = 'maps-v2';
        $this->view->title = Yii::t('common', 'Голосование за карты v2') . ' — ' . Yii::t('database', $server->name);

        $this->registerSeo($server);

        $mapQuery = MapList::find()
            ->alias('ml')
            ->andWhere(['IS NOT', 'ml.size_int', null])
            ->andWhere(['>=', 'ml.size_int', (int)$server->min_map_size])
            ->andWhere(['<=', 'ml.size_int', (int)$server->max_map_size])
            ->orderBy(['ml.created_at' => SORT_DESC]);

        $maps = $mapQuery->all();
        if (empty($maps)) {
            $maps = [];
        }

        $totalMaps = count($maps);
        $displayLimit = 30;

        $mapIds = ArrayHelper::getColumn($maps, 'id');

        $voteCounts = [];
        $userVotes = [];
        $votedMapId = null;
        $userVotedMapIds = [];

        $maxVotes = 0;
        $totalVotes = 0;

        if ($mapIds) {
            $rawCounts = MapListVote::find()
                ->select(['map_list_id', 'cnt' => 'COUNT(*)'])
                ->andWhere(['map_list_id' => $mapIds, 'server_id' => $server->id])
                ->groupBy('map_list_id')
                ->asArray()
                ->all();
            foreach ($rawCounts as $row) {
                $voteCounts[(int)$row['map_list_id']] = (int)$row['cnt'];
                $totalVotes += (int)$row['cnt'];
                if ($voteCounts[(int)$row['map_list_id']] > $maxVotes) {
                    $maxVotes = $voteCounts[(int)$row['map_list_id']];
                }
            }

            $votes = MapListVote::find()
                ->where(['map_list_id' => $mapIds, 'server_id' => $server->id])
                ->with('user')
                ->orderBy(['created_at' => SORT_DESC])
                ->all();

            foreach ($votes as $vote) {
                if (!$vote->user) {
                    continue;
                }
                $mapIdKey = (int)$vote->map_list_id;
                if (!isset($userVotes[$mapIdKey])) {
                    $userVotes[$mapIdKey] = [];
                }
                $userVotes[$mapIdKey][] = [
                    'username' => $vote->user->username,
                    'avatar' => $vote->user->getAvatar(),
                    'created_at' => $vote->created_at,
                ];
                if (count($userVotes[$mapIdKey]) > 12) {
                    $userVotes[$mapIdKey] = array_slice($userVotes[$mapIdKey], 0, 12);
                }
            }

            if (!Yii::$app->user->isGuest) {
                $userVotedMapIds = MapListVote::find()
                    ->select('map_list_id')
                    ->andWhere([
                        'server_id' => $server->id,
                        'user_id' => Yii::$app->user->id,
                    ])
                    ->column();
                if ($userVotedMapIds) {
                    $userVotedMapIds = array_map('intval', $userVotedMapIds);
                    // Для обратной совместимости берем последний голос
                    $votedMapId = (int)end($userVotedMapIds);
                }
            }
        }

        if ($mapIds) {
            usort($maps, static function (MapList $a, MapList $b) use ($voteCounts) {
                $aVotes = $voteCounts[$a->id] ?? 0;
                $bVotes = $voteCounts[$b->id] ?? 0;

                if ($aVotes === $bVotes) {
                    $aTime = strtotime($a->created_at ?? 'now');
                    $bTime = strtotime($b->created_at ?? 'now');
                    return $bTime <=> $aTime;
                }

                return $bVotes <=> $aVotes;
            });
        }

        if ($totalMaps > $displayLimit) {
            $maps = array_slice($maps, 0, $displayLimit);
        }

        $mapDetails = [];
        $mapCardsData = [];
        $language = Yii::$app->language;

        foreach ($maps as $map) {
            $details = $map->data_json ? json_decode($map->data_json, true) : [];
            $mapDetails[$map->id] = $details;

            $monumentsRaw = $details['monuments'] ?? json_decode($map->monuments_json ?? '[]', true);
            if (!is_array($monumentsRaw)) {
                $monumentsRaw = [];
            }

            $monuments = [];
            foreach ($monumentsRaw as $monument) {
                $type = $monument['type'] ?? '';
                $monuments[] = [
                    'type' => $type,
                    'label' => MapLocalization::monument($type, $language),
                    'coordinates' => $monument['coordinates'] ?? null,
                ];
            }

            $mapCardsData[$map->id] = [
                'id' => (int)$map->id,
                'hash' => $map->hash,
                'type' => $map->map_type,
                'seed' => $map->seed,
                'size' => $map->size_int,
                'saveVersion' => $map->save_version,
                'downloadUrl' => $map->url,
                'rustMapsUrl' => $map->hash ? 'https://rustmaps.com/map/' . $map->hash : null,
                'image' => $map->image ?: ($details['imageUrl'] ?? $map->image_url),
                'imagePreview' => $map->image_preview ?: ($details['thumbnailUrl'] ?? $map->thumbnail_url),
                'rawImageUrl' => $map->raw_image_url ?: ($details['rawImageUrl'] ?? null),
                'imageIconUrl' => $map->image_icon_url ?: ($details['imageIconUrl'] ?? null),
                'isStaging' => (bool)$map->is_staging,
                'isCustomMap' => (bool)$map->is_custom_map,
                'canDownload' => (bool)$map->can_download,
                'totalMonuments' => $map->total_monuments,
                'monuments' => $monuments,
                'landPercentage' => $map->land_percentage,
                'biomePercentages' => $details['biomePercentages'] ?? json_decode($map->biome_percentages_json ?? '[]', true),
                'islands' => $map->islands,
                'mountains' => $map->mountains,
                'iceLakes' => $map->ice_lakes,
                'rivers' => $map->rivers,
                'lakes' => $map->lakes,
                'canyons' => $map->canyons,
                'oases' => $map->oases,
                'buildableRocks' => $map->buildable_rocks,
                'createdAt' => $map->created_at,
                'voteCount' => $voteCounts[$map->id] ?? 0,
                'voters' => $userVotes[$map->id] ?? [],
            ];
        }

        if ($mapId !== null) {
            foreach ($maps as $map) {
                if ((int)$map->id === (int)$mapId) {
                    $currentMap = $map;
                    break;
                }
            }
        }

        if (!isset($currentMap) && !empty($maps)) {
            if ($votedMapId) {
                foreach ($maps as $map) {
                    if ((int)$map->id === $votedMapId) {
                        $currentMap = $map;
                        break;
                    }
                }
            }

            if (!isset($currentMap)) {
                $currentMap = $maps[0];
            }
        }

        return $this->render('index.twig', [
            'servers' => $servers,
            'server' => $server,
            'maps' => $maps,
            'currentMap' => $currentMap ?? null,
            'voteCounts' => $voteCounts,
            'userVotes' => $userVotes,
            'userVotedMapId' => $votedMapId,
            'userVotedMapIds' => $userVotedMapIds,
            'mapDetails' => $mapDetails,
            'mapCardsData' => $mapCardsData,
            'maxVotes' => $maxVotes,
            'totalVotes' => $totalVotes,
            'mapsPayloadJson' => Json::encode(array_values($mapCardsData), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'biomeLabels' => MapLocalization::biomeLabels($language),
            'totalMaps' => $totalMaps,
            'displayLimit' => $displayLimit,
            'voteUrlTemplate' => '/maps-v2/vote/ID_PLACEHOLDER',
            'votersUrlTemplate' => '/maps-v2/voters/ID_PLACEHOLDER?server_id=' . $server->id,
        ]);
    }

    public function actionVote($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $map = MapList::findOne($id);
        if (!$map) {
            throw new NotFoundHttpException(Yii::t('common', 'Карта не найдена'));
        }

        $serverId = (int)Yii::$app->request->post('server_id');
        if (!$serverId) {
            throw new BadRequestHttpException('server_id is required');
        }

        $server = Servers::findOne($serverId);
        if (!$server) {
            throw new NotFoundHttpException(Yii::t('common', 'Сервер не найден'));
        }

        if (Yii::$app->user->isGuest) {
            return [
                'success' => false,
                'error' => 'auth_required',
                'message' => Yii::t('common', 'Чтобы голосовать за карту, необходимо авторизоваться'),
            ];
        }

        $user = Yii::$app->user->identity;
        $totalPlaytime = Statistics::find()
            ->where([
                'steam_id' => $user->steam_id,
                'key' => 'playtime',
            ])
            ->sum('value');

        if ((int)$totalPlaytime < 60) {
            return [
                'success' => false,
                'error' => 'not_enough_playtime',
                'message' => Yii::t('common', 'Чтобы голосовать, нужно отыграть минимум 1 час'),
            ];
        }

        if ($map->size_int !== null) {
            if ($map->size_int < (int)$server->min_map_size || $map->size_int > (int)$server->max_map_size) {
                return [
                    'success' => false,
                    'error' => 'size_mismatch',
                    'message' => Yii::t('common', 'Эта карта не подходит по размеру для выбранного сервера'),
                ];
            }
        }

        // Проверяем, есть ли уже голос за эту карту
        $existingVote = MapListVote::find()
            ->where([
                'map_list_id' => $map->id,
                'server_id' => $server->id,
                'user_id' => $user->id,
            ])
            ->one();

        $isVoted = false;
        if ($existingVote) {
            // Удаляем голос (отмена)
            if ($existingVote->delete()) {
                $isVoted = false;
            } else {
                return [
                    'success' => false,
                    'error' => 'delete_failed',
                    'message' => Yii::t('common', 'Не удалось отменить голос'),
                ];
            }
        } else {
            // Добавляем голос
            $vote = new MapListVote([
                'map_list_id' => $map->id,
                'server_id' => $server->id,
                'user_id' => $user->id,
            ]);

            if (!$vote->save()) {
                return [
                    'success' => false,
                    'error' => 'save_failed',
                    'message' => Yii::t('common', 'Не удалось сохранить голос'),
                    'details' => $vote->errors,
                ];
            }
            $isVoted = true;
        }

        $voteCount = MapListVote::find()
            ->where([
                'map_list_id' => $map->id,
                'server_id' => $server->id,
            ])
            ->count();

        return [
            'success' => true,
            'map_id' => $map->id,
            'votes' => (int)$voteCount,
            'is_voted' => $isVoted,
        ];
    }

    public function actionVoters($id, $server_id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $map = MapList::findOne($id);
        if (!$map) {
            throw new NotFoundHttpException(Yii::t('common', 'Карта не найдена'));
        }

        $server = Servers::findOne($server_id);
        if (!$server) {
            throw new NotFoundHttpException(Yii::t('common', 'Сервер не найден'));
        }

        $votes = MapListVote::find()
            ->where([
                'map_list_id' => $map->id,
                'server_id' => $server->id,
            ])
            ->with('user')
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(50)
            ->all();

        $users = [];
        foreach ($votes as $vote) {
            if (!$vote->user) {
                continue;
            }
            $users[] = [
                'username' => $vote->user->username,
                'avatar' => $vote->user->getAvatar(),
                'created_at' => $vote->created_at,
            ];
        }

        return [
            'success' => true,
            'users' => $users,
            'total' => MapListVote::find()
                ->where([
                    'map_list_id' => $map->id,
                    'server_id' => $server->id,
                ])
                ->count(),
        ];
    }

    private function registerSeo(Servers $server): void
    {
        $desc = Yii::t(
            'common',
            'Современное голосование за карту Rust на сервере {server}. Размеры карт: {min}-{max}. Следующий вайп {date}.',
            [
                'server' => Yii::t('database', $server->name),
                'min' => (int)$server->min_map_size,
                'max' => (int)$server->max_map_size,
                'date' => Yii::$app->formatter->asDatetime($server->next_wipe, 'php:d.m.Y H:i'),
            ]
        );

        $this->view->registerMetaTag([
            'name' => 'description',
            'content' => $desc,
        ], 'description');

        $canonical = Yii::$app->params['homePage'] . '/maps-v2/' . $server->tag;
        $this->view->registerLinkTag([
            'rel' => 'canonical',
            'href' => $canonical,
        ]);

        $this->view->registerMetaTag(['property' => 'og:title', 'content' => $this->view->title], 'og:title');
        $this->view->registerMetaTag(['property' => 'og:description', 'content' => $desc], 'og:description');
    }
}

