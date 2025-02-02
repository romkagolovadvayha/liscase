<?php

namespace common\models\statistics;

use common\components\base\ActiveRecord;
use common\components\google\TranslateApi;
use common\models\servers\Servers;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;

/**
 * @property int    $id
 * @property string $team_author
 * @property string $steam_id
 * @property string $type
 * @property string $created_at
 * @property string $server_tag
 * @property string $wipe
 */
class Teams extends ActiveRecord
{
    const TYPE_KICKED = 'kicked';
    const TYPE_INVITE_ACCEPTED = 'invite_accepted';
    const TYPE_LEAVED = 'leaved';

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'statistics_teams';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'          => Yii::t('common', 'ID'),
            'team_author' => Yii::t('common', 'Steam ID автора команды'),
            'type'        => Yii::t('common', 'Тип события'),
            'steam_id'    => Yii::t('common', 'Steam ID'),
            'created_at'    => Yii::t('common', 'Дата'),
            'server_tag'    => Yii::t('common', 'Сервер'),
            'wipe'    => Yii::t('common', 'Wipe'),
        ];
    }

    public static function getTeam(Servers $server, $steamId) {
        $teams = Teams::getTeams($server);
        foreach ($teams as $_authorSteamId => $team) {
            foreach ($team as $_steamId => $item) {
                if ($steamId == $_steamId) {

                    /** @var User[] $users */
                    $users = User::find()->andWhere(['IN', 'steam_id', array_keys($teams[$_authorSteamId])])->all();
                    foreach ($users as $user) {
                        $teams[$_authorSteamId][$user->steam_id]['AVATAR'] = $user->getAvatar();
                        $teams[$_authorSteamId][$user->steam_id]['LINK'] = $user->getLink('stats');
                        $teams[$_authorSteamId][$user->steam_id]['USERNAME'] = $user->username;
                    }

                    return $teams[$_authorSteamId];
                }
            }
        }

        return [];
    }


    /**
     * @param Servers $server
     *
     * @return array
     */
    public static function getTeams(Servers $server, $update = false) {
        $cacheKey = 'Teams_getTeams__' . $server->tag;
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            return Yii::$app->cache->get($cacheKey);
        }

        /** @var Teams[] $models */
        $models = Teams::find()
                       ->andWhere(['server_tag' => $server->tag])
                       ->andWhere(['wipe' => $server->currentWipe()])
                       ->orderBy(['created_at' => SORT_ASC])
                       ->asArray()
                       ->all();

        $items = [];
        $playerTeam = [];
        foreach ($models as $item) {
            if (!isset($items[$item['team_author']])) {
                $items[$item['team_author']] = [];
                $items[$item['team_author']][$item['team_author']] = [
                    'IS_AUTHOR' => true,
                    'STEAM_ID' => $item['team_author'],
                    'DATE' => $item['created_at'],
                    'TIME' => strtotime($item['created_at']),
                ];
                $playerTeam[$item['team_author']] = $item['team_author'];
            }
            if ($item['type'] == 'invite_accepted') {
                if (isset($playerTeam[$item['steam_id']])) {
                    $_authorTeam = $playerTeam[$item['steam_id']];
                    if (isset($items[$_authorTeam])) {
                        foreach (array_keys($items[$_authorTeam]) as $playerSteamId) {
                            if (!isset($playerTeam[$playerSteamId])) {
                                continue;
                            }
                            unset($playerTeam[$playerSteamId]);
                        }
                    }
                    if (!empty($items[$_authorTeam])) {
                        unset($items[$_authorTeam]);
                    }
                }
                if (empty($items[$item['team_author']][$item['steam_id']])) {
                    $items[$item['team_author']][$item['steam_id']] = [
                        'STEAM_ID' => $item['team_author'],
                        'DATE' => $item['created_at'],
                        'TIME' => strtotime($item['created_at']),
                    ];
                    $playerTeam[$item['steam_id']] = $item['team_author'];
                }
            }
            if (in_array($item['type'], ['leaved', 'kicked'])) {
                if ($item['team_author'] === $item['steam_id']) {
                    foreach (array_keys($items[$item['team_author']]) as $playerSteamId) {
                        if (!isset($playerTeam[$playerSteamId])) {
                            continue;
                        }
                        unset($playerTeam[$playerSteamId]);
                    }
                    unset($items[$item['team_author']]);
                } else {
                    unset($playerTeam[$item['steam_id']]);
                    unset($items[$item['team_author']][$item['steam_id']]);
                }
            }
            if (empty($items[$item['team_author']])) {
                unset($playerTeam[$item['team_author']]);
                unset($items[$item['team_author']]);
            }
            if (in_array($item['type'], ['disband']) || empty($items[$item['team_author']])) {
                $_authorTeam = $item['team_author'];
                if (!empty($items[$_authorTeam])) {
                    foreach (array_keys($items[$_authorTeam]) as $playerSteamId) {
                        if (!isset($playerTeam[$playerSteamId])) {
                            continue;
                        }
                        unset($playerTeam[$playerSteamId]);
                    }
                }
                unset($items[$_authorTeam]);
            }
        }

        Yii::$app->cache->set($cacheKey, $items, 60*60);
        return $items;
    }

    public static function getAllInTeams($server, $steamId) {
        /** @var Teams[] $models */
        $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
        $models = Teams::find()
                       ->cache(61*5)
                       ->andWhere(['IN', 'type', ['invite_accepted', 'leaved', 'kicked']])
                       ->andWhere(['server_tag' => $server->tag])
                       ->andWhere(['wipe' => $wipeDate])
                       ->orderBy(['created_at' => SORT_ASC])
                       ->asArray()
                       ->all();

        $result = [];
        $teamAuthor = null;
        $teamAuthorItem = null;
        foreach ($models as $model) {
            if ($model['type'] == 'invite_accepted' && in_array($steamId, [$model['steam_id'], $model['team_author']])) {
                $teamAuthor = $model['team_author'];
                $teamAuthorItem = self::getItemTeams($model['team_author'], $model['created_at'], true);
            }
            if (in_array($model['type'], ['leaved', 'kicked']) && $model['steam_id'] === $teamAuthor) {
                $teamAuthor = null;
                $teamAuthorItem = null;
            }
        }
        foreach ($models as $model) {
            if (in_array($teamAuthor, [$model['team_author']])) {
                if ($model['type'] == 'invite_accepted') {
                    $result[$model['steam_id']] = self::getItemTeams($model['steam_id'], $model['created_at']);
                } elseif (in_array($model['type'], ['leaved', 'kicked'])) {
                    if ($model['steam_id'] === $model['team_author']) {
                        $result = [];
                    } elseif (!empty($result[$model['steam_id']])) {
                        unset($result[$model['steam_id']]);
                    }
                }
            }
        }
        $newResult = [];
        if (!empty($teamAuthorItem)) {
            $newResult[$teamAuthor] = $teamAuthorItem;
        }
        $newResult = array_merge($newResult, $result);

        return $newResult;
    }

    public static function getItemTeams($steamId, $createdAt, $isTeamAuthor = false) {
        $result = ['name' => null];
        $user = User::findBySteamId($steamId);
        $result['name'] = $user->username;
        $result['steam_id'] = $steamId;
        $result['created_at'] = $createdAt;
        $result['team_author'] = $isTeamAuthor;

        return $result;
    }
}
