<?php

namespace frontend\forms\promocode;

use common\components\web\Cookie;
use common\models\profit\Profit;
use common\models\promocode\Promocode;
use common\models\user\User;
use common\models\user\UserPromocode;
use common\models\user\UserTree;
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
            $user = Yii::$app->user->identity;
            /** @var User $userCode */
            // Используем явное приведение collation для избежания ошибки смешивания collations
            // BINARY заставляет MySQL сравнивать строки как бинарные данные, игнорируя collation
            // Параметризованный запрос защищает от SQL инъекций - значение $this->code автоматически экранируется
            $userCode = User::find()
                         ->andWhere('BINARY `promocode` = :code', [':code' => $this->code])
                         ->andWhere(['IS NOT', 'promocode', null])
                         ->andWhere(['!=', 'promocode', ''])
                         ->one();

            if (empty($userCode)) {
                $this->addError('code', Yii::t('common', 'Промокод не существует!'));
                return null;
            }

            $dateTime = new \DateTime();
            $currentDate = $dateTime->format('d.m.Y H:i:s');
            $dateTime = new \DateTime($user->created_at);
            $regDate = $dateTime->format('d.m.Y H:i:s');

            if ($userCode->id == $user->id) {
                $this->addError('code', Yii::t('common', 'Вы не можете ввести свой промокод!'));
                return null;
            }

            if (!(strtotime($user->created_at) >= time() - 15 * 60)) {
                if (!empty($userCode->telegram_chat_id)) {
                    Yii::$app->personalBotTelegram->sendMessage($userCode->telegram_chat_id, "Пользователь пытался ввести ваш промокод, но с даты регистрации пользователя прошло более 15 минут.\nПользователь: {$user->steam_id}\nДата регистрации: {$regDate}\nТекущая дата: {$currentDate}");
                }
                $this->addError('code', Yii::t('common', 'Промокод просрочен!'));
                return null;
            }

            /** @var UserTree $userTree */
            $userTree = UserTree::find()
                ->andWhere(['user_id' => $user->id])
                ->one();

            if (!empty($userTree) && $userTree->parent_user_id == 509) {
                $userTree->parent_user_id = $userCode->id;
                if ($userTree->save()) {
                    if (!empty($userCode->telegram_chat_id)) {
                        Yii::$app->personalBotTelegram->sendMessage($userCode->telegram_chat_id, "По вашму промокоду зарегистировался новый пользователь.\nПользователь: {$user->steam_id}");
                    }
                    $userBalance = $user->getPersonalBalance();
                    $profit = new Profit();
                    $profit->status = 1;
                    $profit->type = Profit::TYPE_REFERRAL;
                    $profit->amount = 50;
                    $profit->user_balance_id = $userBalance->id;
                    $profit->comment = Yii::t('common', 'Активация промокода "{PARAMS_PROMCODE}" на {PARAMS_PROMSUM} RUB', [
                        'PARAMS_PROMCODE' => $this->code,
                        'PARAMS_PROMSUM' => $profit->amount,
                    ], 'ru-RU');
                    $profit->created_at = date('Y-m-d H:i:s');
                    $profit->save(false);
                    $model = new Promocode();
                    $model->amount = $profit->amount;
                    return $model;
                }
            } else {
                if (!empty($userCode->telegram_chat_id)) {
                    Yii::$app->personalBotTelegram->sendMessage($userCode->telegram_chat_id, "Пользователь пытался ввести ваш промокод, но он уже был зарегистирован под другим пользователем.\nПользователь: {$user->steam_id}\nДата регистрации: {$regDate}\nТекущая дата: {$currentDate}");
                }
                $this->addError('code', Yii::t('common', 'Промокод больше не доступен!'));
                return null;
            }
        }
        if ($model->status !== Promocode::STATUS_ACTIVE) {
            $this->addError('code', Yii::t('common', 'Промокод больше не активен!'));
            return null;
        }
        // Бессрочные: finished_at === null
        if ($model->finished_at !== null && strtotime($model->finished_at) < time()) {
            $model->status = Promocode::STATUS_NOT_ACTIVE;
            $model->save(false);
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
