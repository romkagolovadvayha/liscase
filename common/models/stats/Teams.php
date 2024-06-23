<?php

namespace common\models\stats;

use common\components\base\ActiveRecord;
use common\components\google\TranslateApi;
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

    public static function getTeams($server, $player, $statsModels) {
        $server->updateDbConfig();

        /** @var Teams[] $models */
        $models = Teams::find()
                      ->cache(61*5)
                      ->andWhere(['OR',
                                  ['team_author' => $player['steamid']],
                                  ['steam_id' => $player['steamid']]
                      ])
                      ->orderBy(['id' => SORT_DESC])
                      ->asArray()
                      ->all();

        for ($i = 0; $i < count($models); $i++) {
            $model = $models[$i];
            if ($model['steam_id'] === $player['steamid']) {
                $model['name'] = $player['name'];
            }
            if ($model['team_author'] === $player['steamid']) {
                $model['team_author_name'] = $player['name'];
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
                        ->andWhere(['IN', 'type', ['invite_accepted', 'leaved']])
                       ->orderBy(['id' => SORT_ASC])
                       ->asArray()
                       ->all();

        $result = [];
        $teamAuthor = null;
        foreach ($models as $model) {
            if ($model['type'] == 'invite_accepted' && in_array($steamId, [$model['steam_id'], $model['team_author']])) {
                $teamAuthor = $model['team_author'];
                break;
            }
        }
        foreach ($models as $model) {
            if (in_array($teamAuthor, [$model['team_author']])) {
                if ($model['type'] == 'invite_accepted') {
                    $result[$model['steam_id']] = self::getItemTeams($model['steam_id'], $statsModels, $model['created_at']);
                } elseif ($model['type'] == 'leaved') {
                    if (!empty($result[$model['steam_id']])) {
                        unset($result[$model['steam_id']]);
                    }
                }
            }
        }
        $newResult = [];
        foreach ($models as $model) {
            if ($model['type'] == 'invite_accepted' && in_array($steamId, [$model['steam_id'], $model['team_author']])) {
                $newResult[$model['team_author']] = self::getItemTeams($model['team_author'], $statsModels, $model['created_at'], true);
                break;
            }
        }
        $newResult = array_merge($newResult, $result);

        return $newResult;
    }

    public static function getItemTeams($steamId, $statsModels, $createdAt, $isTeamAuthor = false) {
        $result = ['name' => null];
        foreach ($statsModels as $statsModel) {
            if ($steamId === $statsModel['steamid']) {
                $result['name'] = $statsModel['name'];
                break;
            }
        }
        $result['steam_id'] = $steamId;
        $result['created_at'] = $createdAt;
        $result['team_author'] = $isTeamAuthor;

        return $result;
    }
}
