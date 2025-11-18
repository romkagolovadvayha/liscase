<?php

namespace frontend\controllers;

use common\helpers\MapLocalization;
use common\models\map\MapList;
use common\models\map\MapListVote;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\user\User;
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

        // Получаем ID карт, которые уже зафиксированы на любом из серверов
        $fixedMapIds = Servers::find()
            ->select('map_list_id')
            ->andWhere(['IS NOT', 'map_list_id', null])
            ->column();

        $mapQuery = MapList::find()
            ->alias('ml')
            ->andWhere(['IS NOT', 'ml.size_int', null])
            ->andWhere(['>=', 'ml.size_int', (int)$server->min_map_size])
            ->andWhere(['<=', 'ml.size_int', (int)$server->max_map_size])
            ->orderBy(['ml.created_at' => SORT_DESC]);
        
        // Исключаем зафиксированные карты из списка
        if (!empty($fixedMapIds)) {
            $mapQuery->andWhere(['NOT IN', 'ml.id', $fixedMapIds]);
        }

        $maps = $mapQuery->all();
        if (empty($maps)) {
            $maps = [];
        }

        // Проверяем, есть ли зафиксированная карта для текущего сервера
        $fixedMap = null;
        $fixedMapId = null;
        if (!empty($server->map_list_id)) {
            $fixedMapId = (int)$server->map_list_id;
            $fixedMap = MapList::findOne($fixedMapId);
            if ($fixedMap) {
                // Добавляем зафиксированную карту в начало списка
                array_unshift($maps, $fixedMap);
            }
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
                if (!empty($userVotedMapIds)) {
                    $userVotedMapIds = array_map('intval', $userVotedMapIds);
                    // Для обратной совместимости берем последний голос
                    $votedMapId = (int)end($userVotedMapIds);
                } else {
                    $userVotedMapIds = [];
                }
            } else {
                $userVotedMapIds = [];
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

            $isFixed = ($fixedMapId !== null && (int)$map->id === $fixedMapId);

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
                'isFixed' => $isFixed,
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

        return $this->render('index', [
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
            'voteUrlTemplate' => '/maps-v2/vote',
            'votersUrlTemplate' => '/maps-v2/voters/ID_PLACEHOLDER?server_id=' . $server->id,
        ]);
    }

    public function actionDetail($id, $serverId = null)
    {
        $map = MapList::findOne($id);
        if (!$map) {
            throw new NotFoundHttpException(Yii::t('common', 'Карта не найдена'));
        }

        // Получаем server_id из запроса или параметра
        if (!$serverId || $serverId <= 0) {
            $serverId = (int)Yii::$app->request->get('server_id');
        }
        
        // Также пробуем получить из POST (на случай AJAX запросов)
        if ((!$serverId || $serverId <= 0) && Yii::$app->request->isPost) {
            $serverId = (int)Yii::$app->request->post('server_id');
        }

        // Если serverId не передан или равен 0, пытаемся получить из сессии или найти активный сервер
        if (!$serverId || $serverId <= 0) {
            // Получаем первый активный сервер по умолчанию
            $server = Servers::find()
                ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                ->andWhere(['secret_map' => 0])
                ->orderBy(['sort' => SORT_ASC])
                ->one();
            
            if (!$server) {
                throw new NotFoundHttpException(Yii::t('common', 'Сервер не найден'));
            }
        } else {
            $server = Servers::findOne($serverId);
            if (!$server) {
                // Если сервер не найден по ID, пытаемся найти активный сервер
                $server = Servers::find()
                    ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                    ->andWhere(['secret_map' => 0])
                    ->orderBy(['sort' => SORT_ASC])
                    ->one();
                
                if (!$server) {
                    throw new NotFoundHttpException(Yii::t('common', 'Сервер не найден'));
                }
            }
        }

        $voteCounts = [];
        $userVotes = [];
        $userVotedMapIds = [];

        $rawCounts = MapListVote::find()
            ->select(['map_list_id', 'cnt' => 'COUNT(*)'])
            ->andWhere(['map_list_id' => $map->id, 'server_id' => $server->id])
            ->groupBy('map_list_id')
            ->asArray()
            ->all();
        
        foreach ($rawCounts as $row) {
            $voteCounts[(int)$row['map_list_id']] = (int)$row['cnt'];
        }

        $votes = MapListVote::find()
            ->where(['map_list_id' => $map->id, 'server_id' => $server->id])
            ->with('user')
            ->all();

        foreach ($votes as $vote) {
            $userId = (int)$vote->user_id;
            $mapListId = (int)$vote->map_list_id;
            
            if (!isset($userVotes[$mapListId])) {
                $userVotes[$mapListId] = [];
            }
            
            if ($vote->user) {
                $userVotes[$mapListId][] = [
                    'id' => $userId,
                    'username' => $vote->user->username ?? '',
                    'avatar' => $vote->user->getAvatar(),
                    'created_at' => $vote->created_at ?? null,
                ];
            }
            
            if (!Yii::$app->user->isGuest && $userId === (int)Yii::$app->user->id) {
                $userVotedMapIds[] = $mapListId;
            }
        }

        $language = Yii::$app->language;
        $details = $map->data_json ? json_decode($map->data_json, true) : [];
        
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

        $mapCardsData = [
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

        // Получаем все карты для навигации
        $allMapsQuery = MapList::find()
            ->alias('ml')
            ->andWhere(['IS NOT', 'ml.size_int', null])
            ->andWhere(['>=', 'ml.size_int', (int)$server->min_map_size])
            ->andWhere(['<=', 'ml.size_int', (int)$server->max_map_size])
            ->orderBy(['ml.created_at' => SORT_DESC]);

        $allMaps = $allMapsQuery->all();
        $prevMap = null;
        $nextMap = null;
        
        if (!empty($allMaps)) {
            $currentIndex = -1;
            foreach ($allMaps as $index => $m) {
                if ((int)$m->id === (int)$map->id) {
                    $currentIndex = $index;
                    break;
                }
            }
            
            if ($currentIndex >= 0) {
                // Предыдущая карта (более новая, так как сортировка по убыванию даты)
                if ($currentIndex > 0) {
                    $prevMap = $allMaps[$currentIndex - 1];
                }
                
                // Следующая карта (более старая)
                if ($currentIndex < count($allMaps) - 1) {
                    $nextMap = $allMaps[$currentIndex + 1];
                }
            }
        }

        return $this->renderPartial('detail', [
            'map' => $map,
            'server' => $server,
            'detail' => $mapCardsData,
            'userVotedMapId' => in_array($map->id, $userVotedMapIds) ? $map->id : null,
            'userVotedMapIds' => $userVotedMapIds,
            'biomeLabels' => MapLocalization::biomeLabels($language),
            'prevMap' => $prevMap,
            'nextMap' => $nextMap,
        ]);
    }

    public function actionVote()
    {
        $mapId = (int)Yii::$app->request->post('map_id');
        if (!$mapId) {
            throw new BadRequestHttpException('map_id is required');
        }

        $map = MapList::findOne($mapId);
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
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Чтобы голосовать, нужно авторизоваться на сайте!'));
        } else {
            /** @var User $user */
        $user = Yii::$app->user->identity;

            // Проверяем, есть ли уже голос за эту карту
            $existingVote = MapListVote::find()
            ->where([
                    'map_list_id' => $map->id,
                'server_id' => $server->id,
                'user_id' => $user->id,
            ])
                ->one();

            $voteAdded = false;
            $voteRemoved = false;

            if ($existingVote) {
                // Удаляем голос (отмена) - можно снять голос без проверки playtime
                if ($existingVote->delete()) {
                    $voteRemoved = true;
                    Yii::$app->session->addFlash('success', Yii::t('common', 'Ваш голос снят!'));
                }
            } else {
                // Проверяем playtime только при добавлении голоса
                $playtime = Statistics::find()
                    ->andWhere(['steam_id' => $user->steam_id])
                    ->andWhere(['key' => 'playtime'])
                    ->sum('value');

                if ((int)$playtime < 60) {
                    Yii::$app->session->addFlash('danger', Yii::t('common', 'Чтобы проголосовать, нужно отыграть на сервере минимум 1 час!'));
                } else {
                    // Добавляем голос
        $vote = new MapListVote([
            'map_list_id' => $map->id,
            'server_id' => $server->id,
            'user_id' => $user->id,
        ]);

                    if ($vote->save()) {
                        $voteAdded = true;
                        Yii::$app->session->addFlash('success', Yii::t('common', 'Ваш голос успешно учтен!'));
                    }
                }
            }
        }

        // Получаем ID карт, которые уже зафиксированы на любом из серверов
        $fixedMapIds = Servers::find()
            ->select('map_list_id')
            ->andWhere(['IS NOT', 'map_list_id', null])
            ->column();
        
        // Пересчитываем все данные для возврата обновленной карточки
        $allMapsQuery = MapList::find()
            ->alias('ml')
            ->andWhere(['IS NOT', 'ml.size_int', null])
            ->andWhere(['>=', 'ml.size_int', (int)$server->min_map_size])
            ->andWhere(['<=', 'ml.size_int', (int)$server->max_map_size])
            ->orderBy(['ml.created_at' => SORT_DESC]);
        
        // Исключаем зафиксированные карты из списка
        if (!empty($fixedMapIds)) {
            $allMapsQuery->andWhere(['NOT IN', 'ml.id', $fixedMapIds]);
        }
        
        $allMaps = $allMapsQuery->all();
        
        // Проверяем, есть ли зафиксированная карта для текущего сервера
        $fixedMapVote = null;
        $fixedMapIdVote = null;
        if (!empty($server->map_list_id)) {
            $fixedMapIdVote = (int)$server->map_list_id;
            $fixedMapVote = MapList::findOne($fixedMapIdVote);
            if ($fixedMapVote) {
                // Добавляем зафиксированную карту в начало списка
                array_unshift($allMaps, $fixedMapVote);
            }
        }

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
                if (!empty($userVotedMapIds)) {
                    $userVotedMapIds = array_map('intval', $userVotedMapIds);
                } else {
                    $userVotedMapIds = [];
                }
                
                // Гарантируем, что после голосования ID карты включен в список
                if ($voteAdded && !in_array($map->id, $userVotedMapIds)) {
                    $userVotedMapIds[] = (int)$map->id;
                }
                
                // Гарантируем, что после снятия голоса ID карты удален из списка
                if ($voteRemoved && in_array($map->id, $userVotedMapIds)) {
                    $userVotedMapIds = array_diff($userVotedMapIds, [(int)$map->id]);
                    $userVotedMapIds = array_values($userVotedMapIds); // Переиндексируем массив
                }
            } else {
                $userVotedMapIds = [];
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

        // Подготавливаем данные для всех карт (как в actionIndex)
        $mapDetails = [];
        $allMapCardsData = [];
        
        foreach ($allMaps as $mapItem) {
            $details = $mapItem->data_json ? json_decode($mapItem->data_json, true) : [];
            $mapDetails[$mapItem->id] = $details;

            $monumentsRaw = $details['monuments'] ?? json_decode($mapItem->monuments_json ?? '[]', true);
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

            $isFixedVote = ($fixedMapIdVote !== null && (int)$mapItem->id === $fixedMapIdVote);
            
            $allMapCardsData[$mapItem->id] = [
                'id' => (int)$mapItem->id,
                'hash' => $mapItem->hash,
                'type' => $mapItem->map_type,
                'seed' => $mapItem->seed,
                'size' => $mapItem->size_int,
                'saveVersion' => $mapItem->save_version,
                'downloadUrl' => $mapItem->url,
                'rustMapsUrl' => $mapItem->hash ? 'https://rustmaps.com/map/' . $mapItem->hash : null,
                'image' => $mapItem->image ?: ($details['imageUrl'] ?? $mapItem->image_url),
                'imagePreview' => $mapItem->image_preview ?: ($details['thumbnailUrl'] ?? $mapItem->thumbnail_url),
                'rawImageUrl' => $mapItem->raw_image_url ?: ($details['rawImageUrl'] ?? null),
                'imageIconUrl' => $mapItem->image_icon_url ?: ($details['imageIconUrl'] ?? null),
                'isStaging' => (bool)$mapItem->is_staging,
                'isCustomMap' => (bool)$mapItem->is_custom_map,
                'canDownload' => (bool)$mapItem->can_download,
                'totalMonuments' => $mapItem->total_monuments,
                'monuments' => $monuments,
                'landPercentage' => $mapItem->land_percentage,
                'biomePercentages' => $details['biomePercentages'] ?? json_decode($mapItem->biome_percentages_json ?? '[]', true),
                'islands' => $mapItem->islands,
                'mountains' => $mapItem->mountains,
                'iceLakes' => $mapItem->ice_lakes,
                'rivers' => $mapItem->rivers,
                'lakes' => $mapItem->lakes,
                'canyons' => $mapItem->canyons,
                'oases' => $mapItem->oases,
                'buildableRocks' => $mapItem->buildable_rocks,
                'createdAt' => $mapItem->created_at,
                'voteCount' => $voteCounts[$mapItem->id] ?? 0,
                'voters' => $userVotes[$mapItem->id] ?? [],
                'isFixed' => $isFixedVote,
            ];
        }

        // Сортируем карты так же, как в actionIndex (по голосам, затем по дате)
        if ($allMaps) {
            usort($allMaps, static function (MapList $a, MapList $b) use ($voteCounts) {
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

        // Определяем currentMap
        $currentMap = null;
        if (!Yii::$app->user->isGuest) {
            $votedMapId = null;
            if ($userVotedMapIds) {
                $votedMapId = (int)end($userVotedMapIds);
            }
            if ($votedMapId) {
                foreach ($allMaps as $mapItem) {
                    if ((int)$mapItem->id === $votedMapId) {
                        $currentMap = $mapItem;
                        break;
                    }
                }
            }
            if (!$currentMap && !empty($allMaps)) {
                $currentMap = $allMaps[0];
            }
        } elseif (!empty($allMaps)) {
            $currentMap = $allMaps[0];
        }

        // Рендерим все карточки
        $cardsHtml = [];
        foreach ($allMaps as $mapItem) {
            $cardsHtml[$mapItem->id] = $this->renderPartial('_card', [
                'map' => $mapItem,
                'mapCardsData' => $allMapCardsData,
                'voteCounts' => $voteCounts,
                'userVotes' => $userVotes,
                'userVotedMapIds' => $userVotedMapIds,
                'currentMap' => $currentMap,
                'maxVotes' => $maxVotes,
                'totalVotes' => $totalVotes,
                'server' => $server,
            ]);
        }

        // Возвращаем HTML всех карточек с Pjax и Alert
        return $this->renderPartial('_cards_list', [
            'maps' => $allMaps,
            'cardsHtml' => $cardsHtml,
            'server' => $server,
        ]);
    }

    public function actionVoters($id, $server_id)
    {
        // Проверяем, это Pjax запрос или обычный JSON
        if (Yii::$app->request->isPjax) {
            return $this->actionVotersPjax($id, $server_id);
        }

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

    public function actionVotersPjax($id, $server_id)
    {
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
            ->all();

        $voters = [];
        foreach ($votes as $vote) {
            if (!$vote->user) {
                continue;
            }
            $voters[] = [
                'id' => $vote->user->id,
                'username' => $vote->user->username,
                'avatar' => $vote->user->getAvatar(),
                'created_at' => $vote->created_at,
            ];
        }

        // Проверяем, проголосовал ли текущий пользователь
        $userVotedMapIds = [];
        if (!Yii::$app->user->isGuest) {
            $userVotes = MapListVote::find()
                ->where(['map_list_id' => $map->id, 'server_id' => $server->id])
                ->andWhere(['user_id' => Yii::$app->user->id])
                ->all();
            foreach ($userVotes as $vote) {
                $userVotedMapIds[] = $vote->map_list_id;
            }
        }

        $isVoted = in_array($map->id, $userVotedMapIds);

        return $this->renderPartial('_voters', [
            'voters' => $voters,
            'mapId' => $map->id,
            'serverId' => $server->id,
            'isVoted' => $isVoted,
        ]);
    }

    public function actionVoteDetail()
    {
        $mapId = (int)Yii::$app->request->post('map_id');
        if (!$mapId) {
            throw new BadRequestHttpException('map_id is required');
        }

        $map = MapList::findOne($mapId);
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
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Чтобы голосовать, нужно авторизоваться на сайте!'));
        } else {
            /** @var User $user */
            $user = Yii::$app->user->identity;

            // Проверяем, есть ли уже голос за эту карту
            $existingVote = MapListVote::find()
                ->where([
                    'map_list_id' => $map->id,
                    'server_id' => $server->id,
                    'user_id' => $user->id,
                ])
                ->one();

            if ($existingVote) {
                // Удаляем голос (отмена) - можно снять голос без проверки playtime
                if ($existingVote->delete()) {
                    Yii::$app->session->addFlash('success', Yii::t('common', 'Ваш голос снят!'));
                }
            } else {
                // Проверяем playtime только при добавлении голоса
                $playtime = Statistics::find()
                    ->andWhere(['steam_id' => $user->steam_id])
                    ->andWhere(['key' => 'playtime'])
                    ->sum('value');

                if ((int)$playtime < 60) {
                    Yii::$app->session->addFlash('danger', Yii::t('common', 'Чтобы проголосовать, нужно отыграть на сервере минимум 1 час!'));
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

        // Получаем обновленный список voters
        $votes = MapListVote::find()
            ->where([
                'map_list_id' => $map->id,
                'server_id' => $server->id,
            ])
            ->with('user')
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        $voters = [];
        foreach ($votes as $vote) {
            if (!$vote->user) {
                continue;
            }
            $voters[] = [
                'id' => $vote->user->id,
                'username' => $vote->user->username,
                'avatar' => $vote->user->getAvatar(),
                'created_at' => $vote->created_at,
            ];
        }

        // Проверяем, проголосовал ли текущий пользователь
        $userVotedMapIds = [];
        if (!Yii::$app->user->isGuest) {
            $userVotes = MapListVote::find()
                ->where(['map_list_id' => $map->id, 'server_id' => $server->id])
                ->andWhere(['user_id' => Yii::$app->user->id])
                ->all();
            foreach ($userVotes as $vote) {
                $userVotedMapIds[] = $vote->map_list_id;
            }
        }

        $isVoted = in_array($map->id, $userVotedMapIds);

        return $this->renderPartial('_voters', [
            'voters' => $voters,
            'mapId' => $map->id,
            'serverId' => $server->id,
            'isVoted' => $isVoted,
        ]);
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

