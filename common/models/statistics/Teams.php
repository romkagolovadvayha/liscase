<?php

namespace common\models\statistics;

use common\components\base\ActiveRecord;
use common\components\google\TranslateApi;
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
    /**
     * @param $server
     * @param User $user
     *
     * @return Teams[]
     */
    public static function getTeams($server, $user) {
        /** @var Teams[] $models */
        $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
        $models = Teams::find()
                       ->cache(61*5)
                       ->andWhere(['OR',
                                   ['team_author' => $user->steam_id],
                                   ['steam_id' => $user->steam_id]
                                  ])
                       ->andWhere(['server_tag' => $server->tag])
                       ->andWhere(['wipe' => $wipeDate])
                       ->orderBy(['created_at' => SORT_DESC])
                       ->asArray()
                       ->all();

        for ($i = 0; $i < count($models); $i++) {
            $model = $models[$i];
            if ($model['steam_id'] === $user->steam_id) {
                $model['name'] = $user->username;
            }
            if ($model['team_author'] === $user->steam_id) {
                $model['team_author_name'] = $user->username;
            }
            if (empty($model['team_author_name']) || empty($model['name'])) {
                $user = User::findBySteamId($model['steam_id']);
                $model['name'] = $user->username;
                $user = User::findBySteamId($model['team_author']);
                $model['team_author_name'] = $user->username;
            }
            $models[$i] = $model;
        }

        return $models;
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
