<?php

namespace backend\models;

use common\models\clan\ClanMember;
use common\models\user\User;
use Yii;
use yii\base\Model;

/**
 * Добавление участника в клан из админки.
 */
class ClanMemberAddForm extends Model
{
    /** @var int */
    public $user_id;

    /** @var string */
    public $role = ClanMember::ROLE_MEMBER;

    public function rules(): array
    {
        return [
            [['user_id'], 'required'],
            [['user_id'], 'integer'],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            [['role'], 'in', 'range' => [ClanMember::ROLE_MEMBER, ClanMember::ROLE_OFFICER]],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'user_id' => Yii::t('common', 'ID пользователя'),
            'role' => Yii::t('common', 'Роль'),
        ];
    }
}
