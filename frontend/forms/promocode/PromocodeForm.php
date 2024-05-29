<?php

namespace frontend\forms\promocode;

use common\components\web\Cookie;
use common\models\profit\Profit;
use common\models\promocode\Promocode;
use common\models\user\UserPromocode;
use Yii;
use yii\base\BaseObject;

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
     * @return bool|Promocode|\yii\db\ActiveRecord|null
     */
    public function saveRecord()
    {
        if (Yii::$app->user->isGuest) {
            $this->addError('code', Yii::t('common', 'Вам нужно авторизоваться на сайте!'));
            return null;
        }
        $model = Promocode::findByCode($this->code);
        if (empty($model)) {
            $this->addError('code', Yii::t('common', 'Промокод не существует!'));
            return null;
        }
        if ($model->status === Promocode::STATUS_NOT_ACTIVE) {
            $this->addError('code', Yii::t('common', 'Промокод больше не активен!'));
            return null;
        }
        if (strtotime($model->finished_at) < time()) {
            $model->status = Promocode::STATUS_NOT_ACTIVE;
            $model->save();
            $this->addError('code', Yii::t('common', 'Промокод больше не активен!'));
            return null;
        }
        $user =  Yii::$app->user->identity;
        $exist = UserPromocode::find()
                              ->andWhere(['user_id' => $user->id])
                              ->andWhere(['promocode_id' => $model->id])
                              ->exists();
        if ($exist) {
            $this->addError('code', Yii::t('common', 'Вы уже использовали этот промокод!'));
            return null;
        }
        UserPromocode::createRecord($user->id, $model->id);

        return $model;
    }

}
