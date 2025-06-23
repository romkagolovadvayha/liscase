<?php

namespace common\models\stats;

use common\components\base\ActiveRecord;
use common\components\google\TranslateApi;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;

/**
 * @property int                 $id
 * @property string              $team_author
 * @property string              $steam_id
 * @property string              $type
 * @property string              $created_at
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
        return 'main_stats_teams';
    }

    /**
     * @inheritdoc
     */
    public static function getDb()
    {
        return Yii::$app->db_server;
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
        ];
    }

    /**
     * @param $server
     * @param User $user
     * @param $statsModels
     *
     * @return Teams[]
     */
    public static function getTeams($server, $user, $statsModels) {
        $server->updateDbConfig();

        /** @var Teams[] $models */
        $models = Teams::find()
                      ->cache(61*5)
                      ->andWhere(['OR',
                                  ['team_author' => $user->steam_id],
                                  ['steam_id' => $user->steam_id]
                      ])
                      ->orderBy(['id' => SORT_DESC])
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
                foreach ($statsModels as $statsModel) {
                    if ($model['steam_id'] === $statsModel['steamid']) {
                        $model['name'] = $statsModel['name'];
                        if (!empty($model['team_author_name'])) {
                            break;
                        }
                    }
                    if ($model['team_author'] === $statsModel['steamid']) {
                        $model['team_author_name'] = $statsModel['name'];
                        if (!empty($model['name'])) {
                            break;
                        }
                    }
                }
            }
            $models[$i] = $model;
        }

        return $models;
    }

    public static function getAllInTeams($server, $steamId, $statsModels) {
        $server->updateDbConfig();

        /** @var Teams[] $models */
        $models = Teams::find()
                       ->cache(61*5)
                       ->andWhere(['IN', 'type', ['invite_accepted', 'leaved', 'kicked']])
                       ->orderBy(['id' => SORT_ASC])
                       ->asArray()
                       ->all();

        $result = [];
        $teamAuthor = null;
        $teamAuthorItem = null;
        foreach ($models as $model) {
            if ($model['type'] == 'invite_accepted' && in_array($steamId, [$model['steam_id'], $model['team_author']])) {
                $teamAuthor = $model['team_author'];
                $teamAuthorItem = self::getItemTeams($model['team_author'], $statsModels, $model['created_at'], true);
            }
        }
        foreach ($models as $model) {
            if (in_array($teamAuthor, [$model['team_author']])) {
                if ($model['type'] == 'invite_accepted') {
                    $result[$model['steam_id']] = self::getItemTeams($model['steam_id'], $statsModels, $model['created_at']);
                } elseif (in_array($model['type'], ['leaved', 'kicked'])) {
                    if ($model['type'] === 'leaved' && $model['steam_id'] === $model['team_author']) {
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

    public static function getItemTeams($steamId, $statsModels, $createdAt, $isTeamAuthor = false) {
        $result = ['name' => null];
        $user = User::findBySteamId($steamId, false, 'teams 5');
        $result['name'] = $user->username;
        $result['steam_id'] = $steamId;
        $result['created_at'] = $createdAt;
        $result['team_author'] = $isTeamAuthor;

        return $result;
    }
}
