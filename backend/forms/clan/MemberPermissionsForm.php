<?php

namespace backend\forms\clan;

use common\models\clan\ClanPermission;
use Yii;
use yii\base\Model;

/**
 * Форма прав участника клана (админка).
 */
class MemberPermissionsForm extends Model
{
    /** @var string[] */
    public $permissionKeys = [];

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['permissionKeys'], 'safe'],
            [['permissionKeys'], 'validatePermissions'],
        ];
    }

    public function validatePermissions(string $attribute): void
    {
        if (!is_array($this->permissionKeys)) {
            $this->permissionKeys = [];
            return;
        }

        $validKeys = array_map(static function ($p) {
            return $p->key;
        }, ClanPermission::getDefaultPermissions());

        foreach ($this->permissionKeys as $key) {
            if (!in_array($key, $validKeys, true)) {
                $this->addError($attribute, Yii::t('common', 'Неверный ключ разрешения: {key}', ['key' => $key]));
                return;
            }
        }
    }

    public function attributeLabels(): array
    {
        return [
            'permissionKeys' => Yii::t('common', 'Разрешения'),
        ];
    }
}
