<?php

namespace common\models\box;

use common\components\base\ActiveRecord;
use Yii;

/**
 * @property int                 $id
 * @property int                 $drop_id
 * @property int                 $box_id
 * @property string              $created_at
 *
 * @property Drop $drop
 * @property Box $box
 */
class BoxDrop extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'box_drop';
    }

    public function rules(): array
    {
        return [
            [['drop_id', 'box_id', 'created_at'], 'required'],
            [['drop_id', 'box_id'], 'integer'],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * Gets query for [[Box]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getBox(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Box::class, ['id' => 'box_id']);
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
    public static function createRecord($boxId, $dropId): bool
    {
        $model = BoxDrop::find()
            ->andWhere(['box_id' => $boxId])
            ->andWhere(['drop_id' => $dropId])
            ->one();

        if (!empty($model)) {
            return false;
        }

        $model = new self();
        $model->drop_id = $dropId;
        $model->box_id = $boxId;
        $model->created_at = date('Y-m-d H:i:s');
        try {
            $model->save(false);
        } catch (\Exception $e) {
            \Yii::info("Drop Image file string not save " . print_r($e->getMessage(), 1), 'problem');
            return false;
        }
        return true;
    }
}
