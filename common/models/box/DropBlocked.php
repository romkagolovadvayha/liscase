<?php

namespace common\models\box;

use common\components\base\ActiveRecord;
use Yii;

/**
 * @property int                 $id
 * @property int                 $drop_id
 * @property int                 $server_id
 * @property string              $blocked_at
 */
class DropBlocked extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'drop_blocked';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'                  => Yii::t('common', 'ID'),
            'drop_id'               => Yii::t('common', 'Дроп'),
            'server_id'               => Yii::t('common', 'Тип'),
            'blocked_at'          => Yii::t('common', 'Заблокирован до'),
        ];
    }

    public function rules(): array
    {
        return [
            [['drop_id', 'server_id', 'blocked_at'], 'required'],
            [['blocked_at'], 'safe'],
        ];
    }

    /**
     * @param $dropId
     * @param $serverId
     * @param $blockedAt
     *
     * @return bool
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public static function createRecord($dropId, $serverId, $blockedAt): bool
    {
        $models = self::find()
            ->andWhere(['drop_id' => $dropId])
            ->andWhere(['server_Id' => $serverId])
            ->all();
        if (!empty($models)) {
            foreach ($models as $model) {
                $model->delete();
            }
        }
        $model = new self();
        $model->server_id = $serverId;
        $model->drop_id = $dropId;
        $model->blocked_at = $blockedAt;
        try {
            $model->save(false);
        } catch (\Exception $e) {
            \Yii::info("Drop Blocked string not save " . print_r($e->getMessage(), 1), 'problem');
            return false;
        }
        return true;
    }

    /**
     * @param $serverId
     *
     * @return bool
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public static function unBlocked($serverId): bool
    {
        $models = self::find()
                      ->andWhere(['server_id' => $serverId])
                      ->all();
        if (!empty($models)) {
            foreach ($models as $model) {
                $model->delete();
            }
        }

        return true;
    }

    public static function getBlocked($dropId, $serverId)
    {
        $cacheKey = "DropBlocked_getBlocked_" . $serverId;
        if (Yii::$app->cache->get($cacheKey)) {
            $items = Yii::$app->cache->get($cacheKey);
        } else {
            /** @var DropBlocked[] $models */
            $models = self::find()
                          ->andWhere(['server_id' => $serverId])
                          ->all();
            $items = [];
            foreach ($models as $model) {
                $items[$model->drop_id] = $model->blocked_at;
            }
            Yii::$app->cache->set($cacheKey, $items, 3*60);
        }

        if (empty($items[$dropId])) {
            return null;
        }

        return $items[$dropId];
    }

}
