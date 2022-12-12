<?php

namespace common\models\user;

use common\components\base\ActiveRecord;
use common\models\box\Box;
use common\models\box\Drop;
use Yii;

/**
 * @property int                 $id
 * @property int                 $user_id
 * @property int                 $drop_id
 * @property int                 $box_id
 * @property int                 $status
 * @property int                 $auto
 * @property string              $created_at
 *
 * @property Drop $drop
 * @property User $user
 * @property Box  $box
 */
class UserDrop extends ActiveRecord
{

    const STATUS_TEMP_BLOCKED = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_SENDED = 2;
    const STATUS_SELL = 3;

    /**
     * @return array
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_TEMP_BLOCKED       => Yii::t('common', 'Времено блокирован'),
            self::STATUS_ACTIVE       => Yii::t('common', 'Доступен'),
            self::STATUS_SENDED       => Yii::t('common', 'Отправлен'),
            self::STATUS_SELL       => Yii::t('common', 'Продан'),
        ];
    }

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'user_drop';
    }

    public function rules(): array
    {
        return [
            [['drop_id', 'box_id', 'created_at'], 'required'],
            [['drop_id', 'box_id', 'status'], 'integer'],
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
     * Gets query for [[Drop]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getDrop(): \yii\db\ActiveQuery
    {
        return $this->hasMany(Drop::class, ['id' => 'drop_id']);
    }

    /**
     * @return UserDrop[]
     */
    public static function getUsersDropLast() {
        return UserDrop::find()
            ->cache(6)
            ->andWhere(['IN', 'status', [UserDrop::STATUS_ACTIVE, UserDrop::STATUS_SENDED]])
            ->andWhere('box_id IS NOT NULL')
            ->orderBy(['id' => SORT_DESC])
            ->limit(10)
            ->all();
    }

    /**
     * @throws \Exception
     */
    public static function createRecord($userId, $dropId, $boxId = null, $status = null, $auto = false): bool
    {
        $model = new UserDrop();
        $model->user_id = $userId;
        $model->drop_id = $dropId;
        $model->box_id = $boxId;
        $model->auto = $auto;
        $model->status = UserDrop::STATUS_ACTIVE;
        if (!empty($status)) {
            $model->status = $status;
        }
        $model->created_at = date('Y-m-d H:i:s');
        $model->save(false);
        return true;
    }
}
