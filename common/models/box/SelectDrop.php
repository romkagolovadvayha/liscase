<?php

namespace common\models\box;

use common\components\base\ActiveRecord;
use Yii;

/**
 * @property int                 $id
 * @property int                 $drop_id
 * @property int                 $select_id
 * @property string              $created_at
 *
 * @property Drop $drop
 * @property Select $select
 */
class SelectDrop extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'select_drop';
    }

    public function rules(): array
    {
        return [
            [['drop_id', 'select_id', 'created_at'], 'required'],
            [['drop_id', 'select_id'], 'integer'],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * Gets query for [[Select]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSelect(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Select::class, ['id' => 'select_id']);
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
    public static function createRecord($selectId, $dropId): bool
    {
        $model = SelectDrop::find()
            ->andWhere(['select_id' => $selectId])
            ->andWhere(['drop_id' => $dropId])
            ->one();

        if (!empty($model)) {
            return false;
        }

        $model = new self();
        $model->drop_id = $dropId;
        $model->select_id = $selectId;
        $model->created_at = date('Y-m-d H:i:s');
        try {
            $model->save(false);
        } catch (\Exception $e) {
            \Yii::info("Select Image file string not save " . print_r($e->getMessage(), 1), 'problem');
            return false;
        }
        return true;
    }
}
