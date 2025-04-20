<?php

namespace common\models\box;

use common\components\base\ActiveRecord;
use Yii;

/**
 * @property int                 $id
 * @property int                 $drop_id
 * @property int                 $parent_drop_id
 * @property int                 $count
 * @property string              $created_at
 *
 * @property Drop $drop
 * @property Drop $parentDrop
 */
class DropDrop extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'drop_drop';
    }

    public function rules(): array
    {
        return [
            [['drop_id', 'parent_drop_id', 'count', 'created_at'], 'required'],
            [['drop_id', 'parent_drop_id', 'count'], 'integer'],
            [['created_at'], 'safe'],
        ];
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
     * Gets query for [[ParentDrop]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getParentDrop(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Drop::class, ['id' => 'parent_drop_id']);
    }

    /**
     * @throws \Exception
     */
    public static function createRecord($parentDropId, $dropId, $count): bool
    {
        $model = DropDrop::find()
            ->andWhere(['parent_drop_id' => $parentDropId])
            ->andWhere(['drop_id' => $dropId])
            ->one();

        if (!empty($model)) {
            return false;
        }

        $model = new DropDrop();
        $model->drop_id = $dropId;
        $model->parent_drop_id = $parentDropId;
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
