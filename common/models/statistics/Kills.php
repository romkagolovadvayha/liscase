<?php

namespace common\models\statistics;

use common\components\base\ActiveRecord;
use common\components\google\TranslateApi;
use common\models\box\Drop;
use common\models\servers\Servers;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;
use yii\db\Expression;

/**
 * @property int    $id
 * @property string $steam_id
 * @property string $type
 * @property string $dead
 * @property string $wears
 * @property string $signs
 * @property string $weapon
 * @property string $distance
 * @property string $created_at
 * @property string $server_tag
 * @property string $wipe
 */
class Kills extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'statistics_kills';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'         => Yii::t('common', 'ID'),
            'steam_id'   => Yii::t('common', 'Steam ID'),
            'type'   => Yii::t('common', 'Тип'),
            'dead'       => Yii::t('common', 'Противник'),
            'weapon'      => Yii::t('common', 'Оружие'),
            'distance'     => Yii::t('common', 'Дистанция'),
            'created_at' => Yii::t('common', 'Дата'),
        ];
    }

    public static function getAnimalsList() {
        return [
            'bear' => 'медведь',
            'polarbear' => 'белый медведь',
            'boar' => 'кабан',
            'chicken' => 'курица',
            'horse' => 'лошадь',
            'wolf2' => 'волк',
            'wolf' => 'волк',
            'skull.wolf' => 'волк',
            'stag' => 'олень',
            'autoturret_deployed' => 'турель',
            'zombie' => 'зомби',
            'simpleshark' => 'акула',
            'panther' => 'пантера',
            'crocodile' => 'крокодил',
            'tiger' => 'тигр',
        ];
    }

    public static function getAnimals2List() {
        return [
            'bear' => 'медведя',
            'polarbear' => 'белого медведя',
            'boar' => 'кабана',
            'chicken' => 'курицу',
            'horse' => 'лошадь',
            'wolf' => 'волка',
            'wolf2' => 'волка',
            'skull.wolf' => 'волка',
            'stag' => 'оленя',
            'zombie' => 'зомби',
            'simpleshark' => 'акулу',
            'panther' => 'пантеру',
            'crocodile' => 'крокодила',
            'tiger' => 'тигра',
        ];
    }

    public static function getScientistsList() {
        return [
            'default' => '/images/weapons/hazmatsuit_scientist.128.webp',
            //    'npc_tunneldweller' => '/assets/images/live/npc_tunneldweller.png',
            //    'npc_underwaterdweller' => '/assets/images/live/npc_underwaterdweller.png',
            'scientistnpc_heavy' => '/images/weapons/hazmatsuit_scientist_nvgm.128.webp',
        ];
    }

    /**
     * @param Servers $server
     * @param User|null $user
     * @param int $limit
     * @param string|null $wipe вайп для фильтра; если не передан — используется текущий вайп сервера
     */
    public static function getKills($server, $user = null, $limit = 10, $wipe = null) {
        $wipeFilter = $wipe !== null && $wipe !== '' ? $wipe : $server->currentWipe();
        $query = Kills::find()
                       ->cache(60)
                       ->andWhere(['!=', 'dead', ''])
                       ->andWhere(['server_tag' => $server->tag])
                       ->andWhere(['wipe' => $wipeFilter]);

        if (!empty($user)) {
            $query->andWhere(['OR',
                            ['steam_id' => $user->steam_id],
                            ['dead' => $user->steam_id]
                           ]);
        }
        $models = $query->orderBy(['created_at' => SORT_DESC])
            ->asArray()
            ->limit($limit)
            ->all();

        $weapons = [];
        foreach ($models as $model) {
            if (empty($model['weapon'])) {
                continue;
            }
            $weapons[$model['weapon']] = null;
        }
        $weapons = array_keys($weapons);
        $drops = Drop::find()
                     ->andWhere(['IN', 'eng_name', $weapons])
                     ->indexBy('eng_name')
                     ->all();

        $scientists = Kills::getScientistsList();

        // Собираем все уникальные steam_id для предзагрузки пользователей
        $steamIds = [];
        foreach ($models as $model) {
            if (!empty($model['steam_id']) && strlen($model['steam_id']) === 17) {
                $steamIds[$model['steam_id']] = true;
            }
            if (!empty($model['dead']) && strlen($model['dead']) === 17) {
                $steamIds[$model['dead']] = true;
            }
        }
        
        // Предзагружаем всех пользователей одним запросом
        $usersMap = [];
        if (!empty($steamIds)) {
            $steamIdsList = array_keys($steamIds);
            $users = User::find()
                ->where(['IN', 'steam_id', $steamIdsList])
                ->with(['server', 'userProfile'])
                ->indexBy('steam_id')
                ->all();
            foreach ($users as $steamId => $userObj) {
                $usersMap[$steamId] = $userObj;
            }
        }

        for ($i = 0; $i < count($models); $i++) {
            $model = $models[$i];
            if (!empty($model['signs'])) {
                $model['signs'] = json_decode($model['signs'], 1);
            }
            $model['bot'] = false;
            if (!empty($user) && $model['steam_id'] === $user->steam_id) {
                $model['name'] = $user->username;
                $model['link'] = $user->getLink('stats');
                $model['avatar'] = $user->getAvatar() ?: ($scientists['default'] ?? '');
            }
            if (!empty($user) && $model['dead'] === $user->steam_id) {
                $model['dead_name'] = $user->username;
                $model['dead_link'] = $user->getLink('stats');
                $model['dead_avatar'] = $user->getAvatar() ?: ($scientists['default'] ?? '');
            }
            if (empty($model['name']) && strlen($model['steam_id']) === 17) {
                if (isset($usersMap[$model['steam_id']])) {
                    $_user = $usersMap[$model['steam_id']];
                    $model['name'] = $_user->username;
                    $model['link'] = $_user->getLink('stats');
                    $model['avatar'] = $_user->getAvatar() ?: ($scientists['default'] ?? '');
                } else {
                    $_user = User::findBySteamId($model['steam_id'], false, 'kills');
                    if ($_user) {
                        $model['name'] = $_user->username;
                        $model['link'] = $_user->getLink('stats');
                        $model['avatar'] = $_user->getAvatar() ?: ($scientists['default'] ?? '');
                    }
                }
            }
            if (empty($model['dead_name']) && strlen($model['dead']) === 17) {
                if (isset($usersMap[$model['dead']])) {
                    $_user = $usersMap[$model['dead']];
                    $model['dead_name'] = $_user->username;
                    $model['dead_link'] = $_user->getLink('stats');
                    $model['dead_avatar'] = $_user->getAvatar() ?: ($scientists['default'] ?? '');
                } else {
                    $_user = User::findBySteamId($model['dead'], false, 'kills 2');
                    if ($_user) {
                        $model['dead_name'] = $_user->username;
                        $model['dead_link'] = $_user->getLink('stats');
                        $model['dead_avatar'] = $_user->getAvatar() ?: ($scientists['default'] ?? '');
                    }
                }
            }
            if ($model['type'] !== 'deaths' && $model['type'] !== 'suicides') {
                if (!empty($drops[$model['weapon']])) {
                    $model['weapon_image'] = $drops[$model['weapon']]->imageOrig->getImagePubUrl();
                    $model['weapon_name'] = $drops[$model['weapon']]->name;
                }
            }
            if ($model['type'] === 'scientists') {
                if (!empty($scientists[$model['dead']])) {
                    $model['image'] = $scientists[$model['dead']];
                    $model['bot'] = true;
                    if (empty($model['dead_avatar'])) {
                        $model['dead_avatar'] = $model['image'];
                    }
                } elseif (!empty($scientists['default'])) {
                    if (empty($model['dead_avatar'])) {
                        $model['dead_avatar'] = $scientists['default'];
                    }
                }
            }
            if ($model['type'] === 'kill') {
                if (strlen($model['steam_id']) < 10) {
                    $model['image'] = $scientists['default'];
                    $model['bot'] = true;
                    if (empty($model['avatar'])) {
                        $model['avatar'] = $scientists['default'];
                    }
                }
            }
            // Дефолтные аватары для убийцы и жертвы, если не заданы (бот/неизвестный)
            if (empty($model['avatar']) && !empty($scientists['default'])) {
                $model['avatar'] = $scientists['default'];
            }
            if (empty($model['dead_avatar']) && !empty($scientists['default'])) {
                $model['dead_avatar'] = $scientists['default'];
            }
            $models[$i] = $model;
        }

        return $models;
    }

    /**
     * Дуэли: сводка по противникам (игрок vs игрок) по всем серверам.
     * Фильтр только по дате вайпа, без привязки к серверу.
     *
     * @param string $currentSteamId steam_id игрока, чья статистика просматривается
     * @param string|null $wipe если null — за все время; иначе за указанный вайп
     * @return array список [ ['opponent_steam_id' => ..., 'opponent_name' => ..., 'opponent_avatar' => ..., 'opponent_link' => ..., 'kills_by_me' => int, 'kills_by_them' => int, 'total' => int ], ... ]
     */
    public static function getDuels($currentSteamId, $wipe = null)
    {
        $currentSteamId = (string) $currentSteamId;
        $query = static::find()
            ->select([
                'opponent' => new Expression('CASE WHEN steam_id = :current THEN dead ELSE steam_id END'),
                'kills_by_me' => new Expression('SUM(CASE WHEN steam_id = :current THEN 1 ELSE 0 END)'),
                'kills_by_them' => new Expression('SUM(CASE WHEN dead = :current THEN 1 ELSE 0 END)'),
            ])
            ->andWhere(['type' => 'kill'])
            ->andWhere(['OR', ['steam_id' => $currentSteamId], ['dead' => $currentSteamId]])
            ->andWhere(['!=', 'dead', ''])
            ->addParams([':current' => $currentSteamId])
            ->groupBy('opponent');

        if ($wipe !== null && $wipe !== '') {
            $query->andWhere(['wipe' => $wipe]);
        }

        $rows = $query->orderBy('(kills_by_me + kills_by_them) DESC')->asArray()->all();

        $opponentIds = array_column($rows, 'opponent');
        $usersMap = [];
        if (!empty($opponentIds)) {
            $users = User::find()
                ->where(['IN', 'steam_id', $opponentIds])
                ->indexBy('steam_id')
                ->all();
            foreach ($users as $sid => $u) {
                $usersMap[$sid] = [
                    'name' => $u->username,
                    'link' => $u->getLink('stats'),
                    'avatar' => $u->getAvatar() ?: '',
                ];
            }
        }

        $scientists = static::getScientistsList();
        $defaultAvatar = $scientists['default'] ?? '';

        $result = [];
        foreach ($rows as $row) {
            $opponentId = $row['opponent'] ?? '';
            if ($opponentId === '') {
                continue;
            }
            $info = $usersMap[$opponentId] ?? null;
            $result[] = [
                'opponent_steam_id' => $opponentId,
                'opponent_name' => $info['name'] ?? \Yii::t('common', 'Не известный'),
                'opponent_avatar' => $info['avatar'] ?? $defaultAvatar,
                'opponent_link' => $info['link'] ?? null,
                'kills_by_me' => (int) ($row['kills_by_me'] ?? 0),
                'kills_by_them' => (int) ($row['kills_by_them'] ?? 0),
                'total' => (int) ($row['kills_by_me'] ?? 0) + (int) ($row['kills_by_them'] ?? 0),
            ];
        }

        return $result;
    }

    public static function getKillsLive($server, $user = null) {
        $kills = [];
        $animals = Kills::getAnimalsList();
        $animals2 = Kills::getAnimals2List();
        $models = Kills::getKills($server, $user);
        foreach ($models as $model) {
            if (empty($model['dead_name'])) {
                $model['deadLink'] = "<span class=\"stat-block__list__name\">".Yii::t('common', 'Не известный')."</span>";
            } else {
                $model['deadLink'] = "<a title=\"" . Yii::t('common', 'Открыть статистику игрока') . "\" rel=\"nofollow\" class=\"stat-block__list__name p3 link font-medium\" href=\"{$model['dead_link']}\">
                    {$model['dead_name']}
                </a>";
            }
            if (empty($model['name'])) {
                $model['link'] = "<span class=\"stats_player_kills_item_name\">".Yii::t('common', 'Не известный')."</span>";
            } else {
                $model['link'] = "<a title=\"" . Yii::t('common', 'Открыть статистику игрока') . "\" rel=\"nofollow\" class=\"stat-block__list__name p3 link font-medium\" href=\"{$model['link']}\">
                    {$model['name']}
                </a>";
            }
            if (!empty($animals[$model['dead']])) {
                $model['animal'] = $animals[$model['dead']];
            }
            if (!empty($animals2[$model['dead']])) {
                $model['animal2'] = $animals2[$model['dead']];
            }
            if (empty($model['weapon_name'])) {
                $model['weapon_name'] = $model['weapon'];
            }
            $kills[] = $model;
        }
        unset($models);

        return $kills;
    }

    public static function getLive($servers, $update = false) {
        $cacheKey = 'steam_getLive_' . count($servers);
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            return Yii::$app->cache->get($cacheKey);
        }

        $kills = [];
        $animals = Kills::getAnimalsList();
        $animals2 = Kills::getAnimals2List();
        foreach ($servers as $server) {
            $models = Kills::getKills($server);
            $kills[$server->id] = [];
            foreach ($models as $model) {
                if (empty($model['dead_name'])) {
                    $model['deadLink'] = "<span class=\"stat-block__list__name\">".Yii::t('common', 'Не известный')."</span>";
                } else {
                    $model['deadLink'] = "<a title=\"" . Yii::t('common', 'Открыть статистику игрока') . "\" rel=\"nofollow\" class=\"stat-block__list__name p3 link font-medium\" href=\"{$model['dead_link']}\">
                    {$model['dead_name']}
                </a>";
                }
                if (empty($model['name'])) {
                    $model['link'] = "<span class=\"stats_player_kills_item_name\">".Yii::t('common', 'Не известный')."</span>";
                } else {
                    $model['link'] = "<a title=\"" . Yii::t('common', 'Открыть статистику игрока') . "\" rel=\"nofollow\" class=\"stat-block__list__name p3 link font-medium\" href=\"{$model['link']}\">
                    {$model['name']}
                </a>";
                }
                if (!empty($animals[$model['dead']])) {
                    $model['animal'] = $animals[$model['dead']];
                }
                if (!empty($animals2[$model['dead']])) {
                    $model['animal2'] = $animals2[$model['dead']];
                }
                if (empty($model['weapon_name'])) {
                    $model['weapon_name'] = $model['weapon'];
                }
                $kills[$server->id][] = $model;
            }
            unset($models);
        }

        Yii::$app->cache->set($cacheKey, $kills, 7*24*60*60);
        return $kills;
    }
}
