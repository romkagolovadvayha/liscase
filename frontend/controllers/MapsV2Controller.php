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

        // Рендерим карточки через PHP
        $cardsHtml = [];
        foreach ($maps as $map) {
            $cardsHtml[$map->id] = $this->renderPartial('_card', [
                'map' => $map,
                'mapCardsData' => $mapCardsData,
                'voteCounts' => $voteCounts,
                'userVotes' => $userVotes,
                'userVotedMapIds' => $userVotedMapIds,
                'currentMap' => $currentMap ?? null,
                'maxVotes' => $maxVotes,
                'totalVotes' => $totalVotes,
                'server' => $server,
            ]);
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
            'cardsHtml' => $cardsHtml,
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
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Чтобы голосовать за карту, необходимо авторизоваться'));
        } else {
            $user = Yii::$app->user->identity;
            $totalPlaytime = Statistics::find()
                ->where([
                    'steam_id' => $user->steam_id,
                    'key' => 'playtime',
                ])
                ->sum('value');

            if ((int)$totalPlaytime < 60) {
                Yii::$app->session->addFlash('danger', Yii::t('common', 'Чтобы голосовать, нужно отыграть минимум 1 час'));
            } elseif ($map->size_int !== null && 
                      ($map->size_int < (int)$server->min_map_size || $map->size_int > (int)$server->max_map_size)) {
                Yii::$app->session->addFlash('danger', Yii::t('common', 'Эта карта не подходит по размеру для выбранного сервера'));
            } else {
                // Проверяем, есть ли уже голос за эту карту
                $existingVote = MapListVote::find()
                    ->where([
                        'map_list_id' => $map->id,
                        'server_id' => $server->id,
                        'user_id' => $user->id,
                    ])
                    ->one();

                if ($existingVote) {
                    // Удаляем голос (отмена)
                    if ($existingVote->delete()) {
                        Yii::$app->session->addFlash('success', Yii::t('common', 'Ваш голос снят!'));
                    }
                } else {
                    // Добавляем голос
                    $vote = new MapListVote([
                        'map_list_id' => $map->id,
                        'server_id' => $server->id,
                        'user_id' => $user->id,
                    ]);

                    if ($vote->save()) {
                        Yii::$app->session->addFlash('success', Yii::t('common', 'Ваш голос успешно учтен!'));
                    }
                }
            }
        }

        // Пересчитываем все данные для возврата обновленной карточки
        $allMaps = MapList::find()
            ->alias('ml')
            ->andWhere(['IS NOT', 'ml.size_int', null])
            ->andWhere(['>=', 'ml.size_int', (int)$server->min_map_size])
            ->andWhere(['<=', 'ml.size_int', (int)$server->max_map_size])
            ->orderBy(['ml.created_at' => SORT_DESC])
            ->all();

        $mapIds = ArrayHelper::getColumn($allMaps, 'id');
        $voteCounts = [];
        $userVotes = [];
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
                }
            }
        }

        // Находим карту в списке
        $currentMapForCard = null;
        foreach ($allMaps as $m) {
            if ($m->id === $map->id) {
                $currentMapForCard = $m;
                break;
            }
        }

        if (!$currentMapForCard) {
            throw new NotFoundHttpException(Yii::t('common', 'Карта не найдена в списке'));
        }

        // Подготавливаем данные карты
        $language = Yii::$app->language;
        $details = $currentMapForCard->data_json ? json_decode($currentMapForCard->data_json, true) : [];
        $monumentsRaw = $details['monuments'] ?? json_decode($currentMapForCard->monuments_json ?? '[]', true);
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

        $mapCardsData = [
            $currentMapForCard->id => [
                'id' => (int)$currentMapForCard->id,
                'hash' => $currentMapForCard->hash,
                'type' => $currentMapForCard->map_type,
                'seed' => $currentMapForCard->seed,
                'size' => $currentMapForCard->size_int,
                'saveVersion' => $currentMapForCard->save_version,
                'downloadUrl' => $currentMapForCard->url,
                'rustMapsUrl' => $currentMapForCard->hash ? 'https://rustmaps.com/map/' . $currentMapForCard->hash : null,
                'image' => $currentMapForCard->image ?: ($details['imageUrl'] ?? $currentMapForCard->image_url),
                'imagePreview' => $currentMapForCard->image_preview ?: ($details['thumbnailUrl'] ?? $currentMapForCard->thumbnail_url),
                'rawImageUrl' => $currentMapForCard->raw_image_url ?: ($details['rawImageUrl'] ?? null),
                'imageIconUrl' => $currentMapForCard->image_icon_url ?: ($details['imageIconUrl'] ?? null),
                'isStaging' => (bool)$currentMapForCard->is_staging,
                'isCustomMap' => (bool)$currentMapForCard->is_custom_map,
                'canDownload' => (bool)$currentMapForCard->can_download,
                'totalMonuments' => $currentMapForCard->total_monuments,
                'monuments' => $monuments,
                'landPercentage' => $currentMapForCard->land_percentage,
                'biomePercentages' => $details['biomePercentages'] ?? json_decode($currentMapForCard->biome_percentages_json ?? '[]', true),
            ],
        ];

        // Определяем currentMap для карточки
        $currentMap = null;
        if (!Yii::$app->user->isGuest) {
            $votedMapId = null;
            if ($userVotedMapIds) {
                $votedMapId = (int)end($userVotedMapIds);
            }
            if ($votedMapId && $votedMapId === $map->id) {
                $currentMap = $currentMapForCard;
            }
        }

        return $this->renderAjax('_card.php', [
            'map' => $currentMapForCard,
            'mapCardsData' => $mapCardsData,
            'voteCounts' => $voteCounts,
            'userVotes' => $userVotes,
            'userVotedMapIds' => $userVotedMapIds,
            'currentMap' => $currentMap,
            'maxVotes' => $maxVotes,
            'totalVotes' => $totalVotes,
            'server' => $server,
        ]);
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

