<?php

namespace common\models\box;

use common\components\base\ActiveRecord;
use Yii;

/**
 * @property int                 $id
 * @property int                 $drop_id
 * @property string              $stat_key
 * @property int                 $value
 * @property string              $created_at
 */
class DropStat extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'drop_stat';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'                  => Yii::t('common', 'ID'),
            'drop_id'               => Yii::t('common', 'Дроп'),
            'stat_key'               => Yii::t('common', 'Параметр статистики'),
            'value'               => Yii::t('common', 'Значение'),
            'created_at'          => Yii::t('common', 'Заблокирован до'),
        ];
    }

    public function rules(): array
    {
        return [
            [['drop_id', 'stat_key', 'created_at'], 'required'],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * @param $dropId
     * @param $statkey
     * @param $value
     *
     * @return bool
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public static function createRecord($dropId, $statkey, $value): bool
    {
        $models = self::find()
            ->andWhere(['drop_id' => $dropId])
            ->andWhere(['stat_key' => $statkey])
            ->all();
        if (!empty($models)) {
            foreach ($models as $model) {
                $model->delete();
            }
        }
        $model = new self();
        $model->stat_key = $statkey;
        $model->value = $value;
        $model->drop_id = $dropId;
        $model->created_at = date('Y-m-d H:i:s');
        try {
            $model->save(false);
        } catch (\Exception $e) {
            \Yii::info("Drop Stat string not save " . print_r($e->getMessage(), 1), 'problem');
            return false;
        }
        return true;
    }

}
