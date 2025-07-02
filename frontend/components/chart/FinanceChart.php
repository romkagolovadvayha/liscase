<?php

namespace cabinet\components\chart;

use Yii;
use yii\helpers\ArrayHelper;
use common\components\helpers\DateHelper;
use common\models\profit\Profit;

class FinanceChart
{
    /**
     * @return array
     */
    public function getProfitBarData()
    {
        $dateFrom = date('Y-m-d', strtotime('-30 days'));
        $dateTo   = date('Y-m-d');

        $dateList = DateHelper::getDateList($dateFrom, $dateTo, true);

        $profitData = Profit::find()
            ->select([
                'date'   => 'DATE(created_at)',
                'amount' => 'SUM(amount)',
            ])
            ->andWhere(['BETWEEN', 'created_at', $dateFrom, $dateTo . ' 23:59:59'])
            ->andWhere(['user_balance_id' => Yii::$app->user->identity->getPartnerUsdBalance()->id])
            ->andWhere(['status' => 1])
            ->groupBy('date')
            ->createCommand()
            ->queryAll();

        $profitData = ArrayHelper::map($profitData, 'date', 'amount');

        $chartSeries = [];
        foreach ($dateList as &$date) {
            $chartSeries[] = (float)ArrayHelper::getValue($profitData, $date, 0);

            $date = Yii::$app->formatter->asDate($date);
        }
        unset($date);

        return [
            $dateList,
            [
                [
                    'name' => Yii::t('common', 'Сумма начислений'),
                    'data' => $chartSeries,
                ],
            ],
        ];
    }
}