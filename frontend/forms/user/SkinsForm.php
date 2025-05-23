<?php

namespace frontend\forms\user;

use common\components\helpers\DateHelper;
use common\components\web\Cookie;
use common\models\profit\Profit;
use common\models\promocode\Promocode;
use common\models\skindrops\Skindrops;
use common\models\user\User;
use common\models\user\UserPayoutReferral;
use common\models\user\UserPayoutSkins;
use common\models\user\UserPromocode;
use Yii;
use yii\base\BaseObject;

class SkinsForm extends \common\components\base\ActiveRecord
{
    public string $id;
    public string $amount;
    public $market;
    public $type;

    public function rules(): array
    {
        return [
            [['id', 'amount'], 'required'],
            [['id', 'amount'], 'string'],
        ];
    }

    /**
     * @return bool|\yii\db\ActiveRecord|null
     */
    public function saveRecord()
    {
        if (Yii::$app->user->isGuest) {
            $this->addError('code', Yii::t('common', 'Вам нужно авторизоваться на сайте!'));
            return false;
        }
        /** @var User $user */
        $user = Yii::$app->user->identity;

        $cacheKey = "SkinsForm_{$user->id}";
        if (Yii::$app->cache->get($cacheKey)) {
            $seconds = Yii::$app->cache->get($cacheKey) - time();
            $secondsWord = DateHelper::numDecline($seconds, 'секунда, секунды, секунд', false);
            $this->addError('id', Yii::t('common', "Вы делаете запросы слишком часто, попробуйте через {PARAM_SECOND} {PARAM_SECOND_WORD}.", [
                'PARAM_SECOND' => $seconds,
                'PARAM_SECOND_WORD' => $secondsWord,
            ]));
            return false;
        }
        Yii::$app->cache->set($cacheKey, time() + 5, 5);

        $data = $this->market->items();
        if (empty($data[$this->id])) {
            $this->addError('id', Yii::t('common', 'Этот скин ксожалению уже купили, выберите другой!'));
            return false;
        }

        $balance = $user->getSkinsBalance();

        $item = $data[$this->id];
        if ($item['price'] > $this->amount) {
            $this->addError('id', Yii::t('common', 'Этот скин ксожалению уже купили, выберите другой!'));
            return false;
        }
        if ($item['price'] > $balance->balance) {
            $this->addError('id', Yii::t('common', 'Недостаточно средств на счету!'));
            return false;
        }

        $dbTransaction = Yii::$app->db->beginTransaction();

        $userPayout = new UserPayoutSkins();
        $userPayout->user_id  = $user->id;
        $userPayout->name  = $item['name'];
        $userPayout->image  = $item['image'];
        $userPayout->type  = $this->type;
        $userPayout->image300  = $item['image300'];
        $userPayout->status  = UserPayoutSkins::STATUS_WAIT;
        $userPayout->amount  = $item['price'];
        $userPayout->created_at = date('Y-m-d H:i:s');
        if (!$userPayout->save()) {
            $userPayout->status = UserPayoutSkins::STATUS_REJECT;
            $userPayout->save(false);
            $user->getSkinsBalance()->recalculateBalance();
            $this->addError('id', Yii::t('common', 'Произошла ошибка при получении скина!'));
            Yii::$app->telegramChats->sendMessage(json_encode($userPayout->getErrors()));
            return false;
        }

        try {
            if (!empty(Yii::$app->settings->get('skindrops_discordHook')) && !empty($user->server)) {
                $title = '';
                $description = "Игрок **[{$user->username}](http://steamcommunity.com/profiles/{$user->steam_id})** вывел скин {$item['name']} за **{$item['price']} RUB**.";

                $countSkins = Skindrops::find()
                                       ->andWhere(['steam_id' => $user->steam_id])
                                       ->count();

                $fields = [
                    [
                        'name' => " ",
                        'value' => " ",
                        'inline' => false,
                    ],
                    [
                        'name' => " ",
                        'value' => " ",
                        'inline' => false,
                    ],
                    [
                        'name' => $countSkins,
                        'value' => 'Игрок выиграл скинов',
                        'inline' => true,
                    ],
                ];
                Yii::$app->discord->send(Yii::$app->settings->get('skindrops_discordHook'), $title, $description, $item['image300'], $fields, $user->server->discord_token);
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("SkinsForm ({$e->getFile()}:{$e->getLine()}): {$e->getMessage()}");
        }

        $user->getSkinsBalance()->recalculateBalance();
        try {
            $partner = Skindrops::getUrlQuery($user->userProfile->trade_link, 'partner');
            $token = Skindrops::getUrlQuery($user->userProfile->trade_link, 'token');
            if (empty($partner) || empty($token)) {
                $userPayout->status = UserPayoutSkins::STATUS_REJECT;
                $userPayout->save(false);
                $user->getSkinsBalance()->recalculateBalance();
                $this->addError('id', Yii::t('common', 'Неверная ссылка для обмена, проверьте или попробуйте еще раз'));
                return false;
            }
            $trade = $this->market->buy($item['market_hash_name'], $item['price'] * 100, $partner, $token);
            if (empty($trade)) {
                $this->addError('id', Yii::t('common', 'Произошла ошибка при получении скина!'));
                $userPayout->status = UserPayoutSkins::STATUS_REJECT;
                $userPayout->save(false);
                $user->getSkinsBalance()->recalculateBalance();
                return false;
            }
            if (!empty($trade['error'])) {
                if ($trade['error'] === "Не найден предмет по указанной цене или ниже") {
                    $this->addError('id', Yii::t('common', 'Этот скин, к сожалению, уже купили. Пожалуйста, выберите другой.'));
                } elseif (strpos($trade['error'],'проверьте возможность оффлайн') !== false) {
                    $this->addError('id', Yii::t('common', 'Ошибка проверки ссылки, наш бот не сможет забрать или передать вам вещи, проверьте возможность оффлайн трейдов на вашем аккаунте.'));
                } elseif (strpos($trade['error'],'К сожалению, предложение устарело') !== false) {
                    $this->addError('id', Yii::t('common', 'Этот скин, к сожалению, уже купили. Пожалуйста, выберите другой.'));
                } elseif (strpos($trade['error'],'открыть инвентарь') !== false) {
                    $this->addError('id', Yii::t('common', 'Вам нужно сначала открыть инвентарь в настройках стим профиля.'));
                } elseif (strpos($trade['error'],'Неверная ссылка для обмена') !== false) {
                    $this->addError('id', Yii::t('common', 'Неверная ссылка для обмена, проверьте или попробуйте еще раз'));
                } else {
                    $userPayout->status = UserPayoutSkins::STATUS_REJECT;
                    $userPayout->save(false);
                    $user->getSkinsBalance()->recalculateBalance();
                    Yii::$app->telegramChats->sendMessage(json_encode($trade));
                    $this->addError('id', Yii::t('common', 'Произошла ошибка при получении скина!'));
                    return false;
                }
                $userPayout->status = UserPayoutSkins::STATUS_REJECT;
                $userPayout->save(false);
                $user->getSkinsBalance()->recalculateBalance();
                return false;
            }

            if (empty($trade['id'])) {
                $userPayout->status = UserPayoutSkins::STATUS_REJECT;
                $userPayout->save(false);
                $user->getSkinsBalance()->recalculateBalance();
                Yii::$app->telegramChats->sendMessage("Ошибка сохранения скина (нет ID) у UserId: " . Yii::$app->user->id);
                Yii::$app->telegramChats->sendMessage(json_encode($trade));
                $this->addError('id', Yii::t('common', 'Произошла ошибка при получении скина!'));
                $dbTransaction->rollBack();
                return false;
            }

            $userPayout->skin_id  = $trade['id'];
            $userPayout->price  = $trade['price'] / 100;

            if ($userPayout->save(false)) {
                $user->getSkinsBalance()->recalculateBalance();
                $dbTransaction->commit();
                return true;
            } else {
                Yii::$app->telegramChats->sendMessage("Ошибка сохранения скина у UserId: " . Yii::$app->user->id);
                $dbTransaction->rollBack();
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("SkinsForm ({$e->getFile()}:{$e->getLine()}): {$e->getMessage()}");
            $dbTransaction->rollBack();
            $this->addError('id', Yii::t('common', 'Произошла ошибка при получении скина!'));
        }
        return false;
    }

}
