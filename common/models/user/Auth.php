<?php
namespace common\models\user;

use Yii;
use common\components\base\ActiveRecord;

/**
 * @property int                 $id
 * @property int                 $user_id
 * @property string              $source
 * @property string              $source_id
 *
 * @property User     $user
 */
class Auth extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'auth';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'                  => Yii::t('common', 'ID'),
            'user_id'               => Yii::t('common', 'ID пользователя'),
            'source'            => Yii::t('common', 'Источник'),
            'source_id'              => Yii::t('common', 'ID Источника'),
        ];
    }

    public function rules(): array
    {
        return [
            [['user_id', 'source', 'source_id'], 'required'],
            [['user_id'], 'integer'],
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

}
