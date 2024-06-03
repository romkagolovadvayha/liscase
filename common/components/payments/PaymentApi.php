<?php

namespace common\components\payments;

use common\models\invoice\Deposit;
use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;

class PaymentApi extends Component
{
    /**
     * @param int $type
     *
     * @return BaseInterface
     * @throws \Exception
     */
    public static function getInstance($type)
    {
        $classMap = [
            Deposit::TYPE_PAYMENT_CARD          => PaymentCardRf::class,
            Deposit::TYPE_PAYMENT_SBP           => PaymentSbp::class,
            Deposit::TYPE_PAYMENT_TRC20         => PaymentTrc20::class,
            Deposit::TYPE_PAYMENT_STEAM_PAY     => PaymentSteamPay::class,
            Deposit::TYPE_PAYMENT_YOOONEY       => PaymentYoomoney::class,
            Deposit::TYPE_PAYMENT_ERC20         => PaymentErc20::class,
            Deposit::TYPE_PAYMENT_VISA          => PaymentVisa::class,
            Deposit::TYPE_PAYMENT_MIR           => PaymentMir::class,
            Deposit::TYPE_PAYMENT_PERFECT_MONEY => PaymentPerfectMoney::class,
            Deposit::TYPE_PAYMENT_TRON => PaymentTron::class,
            Deposit::TYPE_PAYMENT_CARD_UA => PaymentCardUA::class,
        ];

        $className = ArrayHelper::getValue($classMap, $type);
        if (empty($className)) {
            throw new \Exception('Class for type payment not found');
        }

        return new $className;
    }
}
