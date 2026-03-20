<?php

namespace common\models\clan;

use common\components\base\ActiveRecord;
use Yii;

/**
 * This is the model class for table "clan_member_permissions".
 *
 * @property int $id
 * @property int $clan_member_id
 * @property int $permission_id
 * @property int $created_at
 *
 * @property ClanMember $clanMember
 * @property ClanPermission $permission
 */
class ClanMemberPermission extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'clan_member_permissions';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['clan_member_id', 'permission_id'], 'required'],
            [['clan_member_id', 'permission_id'], 'integer'],
            [['clan_member_id', 'permission_id'], 'unique', 'targetAttribute' => ['clan_member_id', 'permission_id']],
            [['clan_member_id'], 'exist', 'skipOnError' => true, 'targetClass' => ClanMember::class, 'targetAttribute' => ['clan_member_id' => 'id']],
            [['permission_id'], 'exist', 'skipOnError' => true, 'targetClass' => ClanPermission::class, 'targetAttribute' => ['permission_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'clan_member_id' => Yii::t('common', 'Участник клана'),
            'permission_id' => Yii::t('common', 'Разрешение'),
            'created_at' => Yii::t('common', 'Дата назначения'),
        ];
    }

    /**
     * Gets query for [[ClanMember]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getClanMember()
    {
        return $this->hasOne(ClanMember::class, ['id' => 'clan_member_id']);
    }

    /**
     * Gets query for [[Permission]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPermission()
    {
        return $this->hasOne(ClanPermission::class, ['id' => 'permission_id']);
    }
}

