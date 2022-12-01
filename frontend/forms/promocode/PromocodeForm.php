<?php

namespace frontend\forms\promocode;

use common\components\web\Cookie;
use common\models\promocode\Promocode;
use common\models\user\UserPromocode;
use Yii;

class PromocodeForm extends Promocode
{

    public function rules(): array
    {
        return [
            [['code'], 'required'],
            [['code'], 'trim'],
            [['code'], 'string', 'max' => 255],
        ];
    }

    /**
     * @return bool
     */
    public function setCookiePromocode(): bool
    {
        $model = Promocode::findByCode($this->code);
        if (empty($model)) {
            $this->addError('code', Yii::t('common', 'Промокод не существует!'));
            return false;
        }
        if ($model->left_count <= 0) {
            $this->addError('code', Yii::t('common', 'Промокод больше не активен!'));
            return false;
        }
        if (!Yii::$app->user->isGuest) {
            $user =  Yii::$app->user->identity;
            $exist = UserPromocode::find()
                ->andWhere(['user_id' => $user->id])
                ->andWhere(['promocode_id' => $model->id])
                ->exists();
            if ($exist) {
                $this->addError('code', Yii::t('common', 'Вы уже использовали проомокод!'));
                return false;
            }
        }

        Cookie::remove('promocode');
        Cookie::add('promocode', $this->code, true, 365 * 24 * 60);
        return true;
    }

}
