<?php

namespace common\models\building;

use common\models\servers\Servers;
use common\models\user\User;
use Leafo\ScssPhp\Server;
use Yii;

/**
 * This is the model class for table "signs".
 *
 * @property int         $id
 * @property int         $user_id
 * @property int         $signId
 * @property int         $status
 * @property string|null $type
 * @property string|null $image
 * @property string|null $position
 * @property int|null    $server_id
 * @property string|null $created_at
 *
 * @property User $user
 * @property Servers $server
 */
class Signs extends \yii\db\ActiveRecord
{
    public const STATUS_DISABLED = 0;
    public const STATUS_ACTIVE   = 1;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'signs';
    }

    /**
     * @return array
     */
    public static function getStatusList()
    {
        return [
            self::STATUS_DISABLED       => Yii::t('common', 'Не виден'),
            self::STATUS_ACTIVE      => Yii::t('common', 'Виден'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id'], 'required'],
            [['user_id', 'status'], 'integer'],
            [['created_at'], 'safe'],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            [['server_id'], 'exist', 'skipOnError' => true, 'targetClass' => Servers::class, 'targetAttribute' => ['server_id' => 'id']],
        ];
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Gets query for [[Servers]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServer()
    {
        return $this->hasOne(Servers::class, ['id' => 'server_id']);
    }

}
