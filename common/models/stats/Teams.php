<?php

namespace common\models\stats;

use common\components\base\ActiveRecord;
use common\components\google\TranslateApi;
use Yii;
use yii\base\BaseObject;

/**
 * @property int                 $id
 * @property string              $name
 * @property string              $steam_id
 * @property string              $type
 * @property string              $dead
 * @property string              $weapon
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
}
