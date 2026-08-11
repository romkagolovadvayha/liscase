<?php

namespace common\components\bansystem;

use common\models\invoice\Deposit;
use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;

class BanSystemApi extends Component
{

    const TYPE_GGRUST = 1;
    const TYPE_RUSTROOM = 2;
    const TYPE_RUSTUSSR = 3;
    const TYPE_MAGICRUST = 4;
    const TYPE_BRORUST = 5;
    const TYPE_GRANDRUST = 6;
    const TYPE_MOSKOV77 = 7;
    const TYPE_JOKERRUST = 8;
    const TYPE_SLABIYRUST = 9;
    const TYPE_PROSTOJ = 11;
    const TYPE_RUSTADMIN = 12;

    /**
     * @param int $type
     *
     * @return BaseInterface
     * @throws \Exception
     */
    public static function getInstance($type)
    {
        $classMap = [
//            BanSystemApi::TYPE_GGRUST          => GGRust::class,
//            BanSystemApi::TYPE_RUSTROOM          => RustRoom::class,
//            BanSystemApi::TYPE_RUSTUSSR          => RustUssr::class,
//            BanSystemApi::TYPE_MAGICRUST          => MagicRust::class,
//            BanSystemApi::TYPE_BRORUST          => BroRust::class,
//            BanSystemApi::TYPE_GRANDRUST          => GrandRust::class,
//            BanSystemApi::TYPE_MOSKOV77          => Moskov77::class,
//            BanSystemApi::TYPE_JOKERRUST          => JokerRust::class,
//            BanSystemApi::TYPE_SLABIYRUST          => Slabiy::class,
//            BanSystemApi::TYPE_PROSTOJ          => Prostoj::class,
            BanSystemApi::TYPE_RUSTADMIN          => RustAdmin::class,
        ];

        $className = ArrayHelper::getValue($classMap, $type);
        if (empty($className)) {
            throw new \Exception('Class for type payment not found');
        }

        return new $className;
    }
}
