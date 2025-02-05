<?php

namespace common\models\user;

use common\components\base\ActiveRecord;
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
 *
 * @property User $user
 */
class UserPayoutSkins extends ActiveRecord
{
    const STATUS_WAIT = 0;
    const STATUS_SUCCESS = 1;
    const STATUS_REJECT = 2;

    /**
     * @return array
     */
    public static function getStatusList()
    {
        return [
            self::STATUS_WAIT    => Yii::t('common', 'Отправляется'),
            self::STATUS_SUCCESS => Yii::t('common', 'Получен'),
            self::STATUS_REJECT  => Yii::t('common', 'Не получен'),
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

    public static function check() {
        $payouts = UserPayoutSkins::find()
                                  ->andWhere(['status' => UserPayoutSkins::STATUS_WAIT])
                                  ->orderBy(['created_at' => SORT_DESC])
                                  ->indexBy('skin_id')
                                  ->all();

        if (!empty($payouts)) {
            $items = Yii::$app->rustTm->history()['data'];
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
               }
            }
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
