<?php

namespace common\models\box;

use common\components\base\ActiveRecord;
use Yii;

/**
 * @property int                 $id
 * @property int                 $drop_id
 * @property int                 $sets_id
 * @property int                 $count
 * @property string              $created_at
 *
 * @property Drop $drop
 * @property Sets $Sets
 */
class SetsDrop extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'sets_drop';
    }

    public function rules(): array
    {
        return [
            [['drop_id', 'sets_id', 'count', 'created_at'], 'required'],
            [['drop_id', 'sets_id', 'count'], 'integer'],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * Gets query for [[Sets]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSets(): \yii\db\ActiveQuery
    {
        return $this->hasOne(SetsDrop::class, ['id' => 'sets_id']);
    }

    /**
     * Gets query for [[Drop]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getDrop(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Drop::class, ['id' => 'drop_id']);
    }

    /**
     * @throws \Exception
     */
    public static function createRecord($setsId, $dropId, $count): bool
    {
        $model = SetsDrop::find()
            ->andWhere(['sets_id' => $setsId])
            ->andWhere(['drop_id' => $dropId])
            ->one();

        if (!empty($model)) {
            return false;
        }

        $model = new self();
        $model->drop_id = $dropId;
        $model->sets_id = $setsId;
        $model->count = $count;
        $model->created_at = date('Y-m-d H:i:s');
        try {
            $model->save(false);
        } catch (\Exception $e) {
            \Yii::info("Sets Drop not save " . print_r($e->getMessage(), 1), 'problem');
            return false;
        }
        return true;
    }
}
