<?php

namespace backend\forms\userProfile;

use common\components\helpers\Role;
use common\models\user\User;
use Yii;
use yii\base\Model;

class RoleForm extends Model
{
    public $roleCodes;

    /**
     * @var User
     */
    public $user;

    public function attributeLabels()
    {
        return [
            'roleCodes' => 'Роль',
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['roleCodes', 'user'], 'required'],
        ];
    }

    public function setUserId($userId)
    {
        if (empty($userId)) {
            return;
        }
        $this->user = User::findOne($userId);
        $this->roleCodes = $this->user->getUserRoles();
        if (empty($this->user)) {
            $this->addError('formError', 'Пользователь не найден');
        }
    }

    /**
     * @return bool
     */
    public function saveRecord(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        Role::updateUserRoles($this->user->id, $this->roleCodes);
        return true;
    }
}
