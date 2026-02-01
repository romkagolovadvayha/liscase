<?php

namespace common\models\user;

use common\components\base\ActiveRecord;
use common\models\skindrops\Skindrops;
use Yii;
use yii\base\BaseObject;

/**
 * @property int    $id
 * @property int    $user_id
 * @property float  $amount
 * @property float  $price
 * @property int    $skin_id
 * @property int    $status
 * @property string $name
 * @property string $image
 * @property string $image300
 * @property string $created_at
 * @property string $type
 *
 * @property User $user
 */
class UserPayoutSkins extends ActiveRecord
{
    const STATUS_WAIT = 0;
    const STATUS_SUCCESS = 1;
    const STATUS_REJECT = 2;
    const STATUS_NEW = 3;

    /**
     * @return array
     */
    public static function getStatusList()
    {
        return [
            self::STATUS_WAIT    => Yii::t('common', 'Отправляется'),
            self::STATUS_SUCCESS => Yii::t('common', 'Получен'),
            self::STATUS_REJECT  => Yii::t('common', 'Не получен'),
            self::STATUS_NEW  => Yii::t('common', 'Создан трейд'),
        ];
    }

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'user_payout_skins';
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public static function check($date = null, $status = null) {
        /** @var UserPayoutSkins $payout */
        $payout = UserPayoutSkins::find()
                       ->andWhere(['status' => $status])
                       ->andWhere(['>=', 'created_at', '2026-01-01 00:00:01'])
                       ->orderBy(['created_at' => SORT_ASC])
                       ->one();
        if (empty($payout)) {
            return;
        }
        $items = Yii::$app->rustTm->history($date)['data'];
        UserPayoutSkins::checkRust($items, $status);
    }

    public static function checkRust($items, $status) {
        $payouts = UserPayoutSkins::find()
                                  ->andWhere(['status' => $status])
                                  ->orderBy(['created_at' => SORT_DESC])
                                  ->indexBy('skin_id')
                                  ->all();

        if (!empty($payouts)) {
            foreach ($items as $item) {
                if (empty($payouts[$item['item_id']])) {
                    continue;
                }
                /** @var UserPayoutSkins $payout */
                $payout = $payouts[$item['item_id']];
                if ($item['stage'] == 5) {
                    $payout->status = UserPayoutSkins::STATUS_REJECT;
                    $payout->save();
                    $payout->user->getSkinsBalance()->recalculateBalance();
                }
                if ($item['stage'] == 2) {
                    $payout->status = UserPayoutSkins::STATUS_SUCCESS;
                    $payout->save();

                    UserPayoutSkins::alert($payout->user, $payout->name, $payout->type, $payout->amount, $payout->image300);
                }
                if ($item['stage'] == 1 && strtotime($payout->created_at) < time() - 60 * 60 * 5) {
                    $payout->status = UserPayoutSkins::STATUS_NEW;
                    $payout->save();
                }
            }
        }
    }

    public static function alert($user, $name, $type, $price, $image) {
        try {
            if (!empty(Yii::$app->settings->get('skindrops_discordHook')) && !empty($user->server)) {
                $title = '';
                $game = "CS2";
                if ($type == 'rust') {
                    $game = "Rust";
                }
                $description = "Игрок **[{$user->username}](http://steamcommunity.com/profiles/{$user->steam_id})** вывел скин {$name} для игры {$game} за **{$price} RUB**.";

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
                Yii::$app->discord->send(Yii::$app->settings->get('skindrops_discordHook'), $title, $description, $image, $fields, $user->server->discord_token);
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("SkinsForm ({$e->getFile()}:{$e->getLine()}): {$e->getMessage()}");
        }
    }

    public static function clear() {
        /** @var UserPayoutSkins[] $payouts */
        $payouts = UserPayoutSkins::find()
                                  ->andWhere(['status' => UserPayoutSkins::STATUS_WAIT])
                                  ->orderBy(['created_at' => SORT_DESC])
                                  ->all();
        foreach ($payouts as $payout) {
            if (empty($payout->skin_id)) {
                $payout->status = UserPayoutSkins::STATUS_REJECT;
                $payout->save();
                $payout->user->getSkinsBalance()->recalculateBalance();
            }
        }
    }
}
