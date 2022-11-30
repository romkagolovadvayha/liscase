<?php

namespace common\models\user;

use common\components\base\ActiveRecord;
use common\models\box\Box;
use Yii;

/**
 * @property int                 $id
 * @property int                 $user_id
 * @property int                 $box_id
 * @property int                 $status
 * @property int                 $auto
 * @property string              $created_at
 *
 * @property Box $box
 * @property User $user
 */
class UserBox extends ActiveRecord
{
    const STATUS_WAIT_OPEN = 1;
    const STATUS_OPENED = 2;

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'user_box';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'box_id', 'created_at'], 'required'],
            [['user_id', 'box_id', 'status'], 'integer'],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
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
     * @throws \Exception
     */
    public static function createRecord($userId, $boxId, $status = null, $auto = false)
    {
        $model = new UserBox();
        $model->user_id = $userId;
        $model->box_id = $boxId;
        $model->status = UserBox::STATUS_WAIT_OPEN;
        $model->auto = $auto;
        if (!empty($status)) {
            $model->status = $status;
        }
        $model->created_at = date('Y-m-d H:i:s');
        $model->save(false);
        return Yii::$app->db->getLastInsertID();
    }

    /**
     * @throws \Exception
     */
    public static function botGenerate()
    {
//        try {
            /** @var Box $box */
            $box = Box::find()
                      ->andWhere(['status' => Box::STATUS_ACTIVE])
                      ->orderBy('rand()')
                      ->one();

            /** @var User $user */
            $user = User::find()
                        ->andWhere(['status' => User::STATUS_ACTIVE])
                        ->andWhere(['auto' => 1])
                        ->orderBy('rand()')
                        ->one();

            if (empty($user) || empty($box)) {
                return;
            }
            $drop = $box->getBoxDrop()
                        ->orderBy('rand()')
                        ->one();

            UserBox::createRecord($user->id, $box->id, UserBox::STATUS_OPENED, true);
            UserDrop::createRecord($user->id, $drop->drop->id, $box->id, UserDrop::STATUS_SENDED, true);
//        } catch (\Exception $ex) {
//
//        }
    }
}
