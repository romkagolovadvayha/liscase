<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\models\skindrops\Skindrops;
use yii\web\Controller;
use Yii;

class ReportController extends Controller
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN],
                    ],
                ],
            ],
        ];
    }

    public function actionProducts()
    {
        return $this->render('products');
    }

    public function actionDeposits()
    {
        $data = [];
        $date = new \DateTime();
        for ($i = 0; $i < 3; $i++) {
            $data[$date->format('Y-m-01')] = [];
            $data[$date->format('Y-m-01')]['deposits'] = \common\models\invoice\Deposit::find()
                ->andWhere(['status' => \common\models\invoice\Deposit::STATUS_SUCCESS])
                ->andWhere(['>=', 'created_at', $date->format('Y-m-01 00:00:01')])
                ->andWhere(['<=', 'created_at', $date->format('Y-m-t 23:59:59')])
                ->all();
            $data[$date->format('Y-m-01')]['skindrops'] = Skindrops::find()
                ->andWhere(['>=', 'created_at', $date->format('Y-m-01 00:00:01')])
                ->andWhere(['<=', 'created_at', $date->format('Y-m-t 23:59:59')])
                ->sum('real_price');

            $users = [];
            $total = 0;
            /** @var \common\models\invoice\Deposit $deposit */
            foreach ($data[$date->format('Y-m-01')]['deposits'] as $deposit) {
                if (empty($users[$deposit->user_id])) {
                    $users[$deposit->user_id] = [
                       'amount' => 0,
                       'user' => $deposit->user,
                    ];
                }
                $users[$deposit->user_id]['amount'] += $deposit->amount;
                $total += $deposit->amount;
            }
            usort($users, function ($a, $b) {
                if ($a['amount'] == $b['amount']) {
                    return 0;
                }
                return ($a['amount'] > $b['amount']) ? -1 : 1;
            });

            $arr = [
                'Январь',
                'Февраль',
                'Март',
                'Апрель',
                'Май',
                'Июнь',
                'Июль',
                'Август',
                'Сентябрь',
                'Октябрь',
                'Ноябрь',
                'Декабрь'
            ];

            $month = $date->format('n')-1;

            $data[$date->format('Y-m-01')]['month'] = $arr[$month];
            $data[$date->format('Y-m-01')]['total'] = $total;
            $data[$date->format('Y-m-01')]['users'] = $users;

            $date->modify('-1 month');
        }

        return $this->render('deposits', [
            'data' => $data
        ]);
    }

}