<?php

namespace common\models\clan;

use common\components\base\ActiveRecord;
use common\models\user\User;
use Yii;

/**
 * This is the model class for table "clan_members".
 *
 * @property int $id
 * @property int $clan_id
 * @property int $user_id
 * @property string $role
 * @property string $join_date
 * @property string|null $leave_date
 * @property int $created_at
 *
 * @property Clan $clan
 * @property User $user
 * @property ClanMemberPermission[] $permissions
 */
class ClanMember extends ActiveRecord
{
    const ROLE_MEMBER = 'member';
    const ROLE_OFFICER = 'officer';
    const ROLE_LEADER = 'leader';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'clan_members';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['clan_id', 'user_id', 'join_date'], 'required'],
            [['clan_id', 'user_id'], 'integer'],
            [['join_date', 'leave_date'], 'safe'],
            [['role'], 'in', 'range' => [self::ROLE_MEMBER, self::ROLE_OFFICER, self::ROLE_LEADER]],
            [['role'], 'default', 'value' => self::ROLE_MEMBER],
            [['clan_id'], 'exist', 'skipOnError' => true, 'targetClass' => Clan::class, 'targetAttribute' => ['clan_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            [['clan_id', 'user_id'], 'validateUniqueActiveMember'],
        ];
    }

    /**
     * Валидация уникальности активного участника
     */
    public function validateUniqueActiveMember($attribute, $params)
    {
        if ($this->isNewRecord || $this->isAttributeChanged('clan_id') || $this->isAttributeChanged('user_id')) {
            $existing = self::find()
                ->where(['clan_id' => $this->clan_id, 'user_id' => $this->user_id])
                ->andWhere(['IS', 'leave_date', null])
                ->andWhere(['!=', 'id', $this->id ?: 0])
                ->exists();
            
            if ($existing) {
                $this->addError($attribute, Yii::t('common', 'Пользователь уже является активным участником этого клана'));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'clan_id' => Yii::t('common', 'Клан'),
            'user_id' => Yii::t('common', 'Пользователь'),
            'role' => Yii::t('common', 'Роль'),
            'join_date' => Yii::t('common', 'Дата вступления'),
            'leave_date' => Yii::t('common', 'Дата выхода'),
            'created_at' => Yii::t('common', 'Дата создания'),
        ];
    }

    /**
     * Gets query for [[Clan]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getClan()
    {
        return $this->hasOne(Clan::class, ['id' => 'clan_id']);
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
     * Gets query for [[Permissions]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPermissions()
    {
        return $this->hasMany(ClanMemberPermission::class, ['clan_member_id' => 'id'])
            ->with('permission');
    }

    /**
     * Проверка, активен ли участник
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->leave_date === null;
    }

    /**
     * Получение периода членства
     *
     * @return array
     */
    public function getMembershipPeriod()
    {
        return [
            'join_date' => $this->join_date,
            'leave_date' => $this->leave_date,
        ];
    }

    /**
     * Проверка наличия разрешения
     *
     * @param string $permissionKey
     * @return bool
     */
    public function hasPermission($permissionKey)
    {
        // Лидер автоматически имеет все права
        if ($this->isLeader()) {
            return true;
        }

        $permission = ClanPermission::findByKey($permissionKey);
        if (!$permission) {
            return false;
        }

        return ClanMemberPermission::find()
            ->where(['clan_member_id' => $this->id, 'permission_id' => $permission->id])
            ->exists();
    }

    /**
     * Проверка права приглашать
     *
     * @return bool
     */
    public function canInvite()
    {
        return $this->hasPermission('invite');
    }

    /**
     * Проверка права исключать
     *
     * @return bool
     */
    public function canKick()
    {
        return $this->hasPermission('kick');
    }

    /**
     * Проверка права повышать/понижать
     *
     * @return bool
     */
    public function canPromoteDemote()
    {
        return $this->hasPermission('promote_demote');
    }

    /**
     * Проверка права редактировать клан
     *
     * @return bool
     */
    public function canEditClan()
    {
        return $this->hasPermission('edit_clan');
    }

    /**
     * Проверка права управлять разрешениями
     *
     * @return bool
     */
    public function canManagePermissions()
    {
        return $this->hasPermission('manage_permissions');
    }

    /**
     * Проверка, является ли лидером
     *
     * @return bool
     */
    public function isLeader()
    {
        return $this->role === self::ROLE_LEADER;
    }

    /**
     * Добавление разрешения
     *
     * @param string $permissionKey
     * @return bool
     */
    public function addPermission($permissionKey)
    {
        $permission = ClanPermission::findByKey($permissionKey);
        if (!$permission) {
            return false;
        }

        $memberPermission = ClanMemberPermission::find()
            ->where(['clan_member_id' => $this->id, 'permission_id' => $permission->id])
            ->one();

        if (!$memberPermission) {
            $memberPermission = new ClanMemberPermission();
            $memberPermission->clan_member_id = $this->id;
            $memberPermission->permission_id = $permission->id;
            return $memberPermission->save();
        }

        return true;
    }

    /**
     * Удаление разрешения
     *
     * @param string $permissionKey
     * @return bool
     */
    public function removePermission($permissionKey)
    {
        // Нельзя снять разрешения у лидера
        if ($this->isLeader()) {
            return false;
        }

        $permission = ClanPermission::findByKey($permissionKey);
        if (!$permission) {
            return false;
        }

        $memberPermission = ClanMemberPermission::find()
            ->where(['clan_member_id' => $this->id, 'permission_id' => $permission->id])
            ->one();

        if ($memberPermission) {
            return $memberPermission->delete();
        }

        return true;
    }

    /**
     * Синхронизация разрешений (установка списка)
     *
     * @param array $permissionKeys
     * @return bool
     */
    public function syncPermissions($permissionKeys)
    {
        // Нельзя изменять разрешения лидера
        if ($this->isLeader()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // Удаляем все текущие разрешения
            ClanMemberPermission::deleteAll(['clan_member_id' => $this->id]);

            // Добавляем новые разрешения (результат обязателен: иначе «успех» при отсутствии строки в clan_permissions)
            foreach ($permissionKeys as $key) {
                if (!$this->addPermission($key)) {
                    $transaction->rollBack();
                    Yii::warning(
                        'syncPermissions: не удалось выдать право ' . json_encode($key, JSON_UNESCAPED_UNICODE),
                        __METHOD__
                    );
                    return false;
                }
            }

            $transaction->commit();
            return true;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error($e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Получение списка ключей разрешений участника
     *
     * @return array
     */
    public function getPermissionKeys()
    {
        if ($this->isLeader()) {
            // Лидер имеет все разрешения
            $permissions = ClanPermission::getDefaultPermissions();
            return array_map(function($p) { return $p->key; }, $permissions);
        }

        $rows = $this->getPermissions()->with('permission')->all();
        $keys = [];
        foreach ($rows as $row) {
            if ($row->permission) {
                $keys[] = $row->permission->key;
            }
        }

        return $keys;
    }
}

