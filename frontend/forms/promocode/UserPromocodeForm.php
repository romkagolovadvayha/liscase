<?php

namespace frontend\forms\promocode;

use common\components\web\Cookie;
use common\models\profit\Profit;
use common\models\promocode\Promocode;
use common\models\user\User;
use common\models\user\UserPromocode;
use Yii;
use yii\base\BaseObject;

class UserPromocodeForm extends User
{

    public function rules(): array
    {
        return [
            [['promocode'], 'required'],
            [['promocode'], 'trim'],
            [['promocode'], 'string', 'max' => 120, 'min' => 5],
        ];
    }

    /**
     * @return bool|Promocode|\yii\db\ActiveRecord|null
     */
    public function saveRecord()
    {
        if (Yii::$app->user->isGuest) {
            $this->addError('code', Yii::t('common', 'Вам нужно авторизоваться на сайте!'));
            return null;
        }
        if (!$this->validate()) {
            return false;
        }

        if (!preg_match("/^[a-zA-Z0-9-]+$/", $this->promocode)) {
            $this->addError('promocode', Yii::t('common', 'Разрешены только буквы латинского алфавита и цифры'));
            return false;
        }

        $exist = User::find()
            ->andWhere(['promocode' => $this->promocode])
            ->andWhere(['NOT IN', 'id', [$this->id]])
            ->exists();

        if ($exist) {
            $this->addError('promocode', Yii::t('common', 'Промокод уже существует, используйте другой'));
            return false;
        }

        $exist = Promocode::find()
                     ->andWhere(['code' => $this->promocode])
                     ->exists();

        if ($exist) {
            $this->addError('promocode', Yii::t('common', 'Промокод уже существует, используйте другой'));
            return false;
        }

        return $this->save();
    }

}
