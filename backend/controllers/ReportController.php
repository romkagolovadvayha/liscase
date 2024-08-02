<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\models\invoice\Invoice;
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
        ini_set('memory_limit', '1024M');
        $result = [];
        $date = new \DateTime();
        for ($i = 0; $i < 3; $i++) {
            $result[$date->format('Y-m-01')] = [];
            $result[$date->format('Y-m-01')]['invoices'] = Invoice::find()
                                                                ->andWhere(['>=', 'created_at', $date->format('Y-m-01 00:00:01')])
                                                                ->andWhere(['<=', 'created_at', $date->format('Y-m-t 23:59:59')])
                                                                ->andWhere('drop_id IS NOT NULL')
                                                                ->all();

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
            $result[$date->format('Y-m-01')]['month'] = $arr[$month];
            $result[$date->format('Y-m-01')]['total'] = 0;
            $result[$date->format('Y-m-01')]['products'] = [];

            $data = [];
            $total = 0;
            /** @var Invoice $item */
            if (empty($result[$date->format('Y-m-01')]['invoices'])) {
                $date->modify('-1 month');
                continue;
            }
            foreach ($result[$date->format('Y-m-01')]['invoices'] as $item) {
                if (empty($data[$item->drop_id])) {
                    $data[$item->drop_id] = [
                        'count' => 0,
                        'drop_id' => $item->drop_id,
                    ];
                }
                $data[$item->drop_id]['count']++;
            }
            usort($data, function ($a, $b) {
                if ($a['count'] == $b['count']) {
                    return 0;
                }
                return ($a['count'] > $b['count']) ? -1 : 1;
            });

            $result[$date->format('Y-m-01')]['total'] = $total;
            $result[$date->format('Y-m-01')]['products'] = $data;

            $date->modify('-1 month');
        }

        return $this->render('products', [
            'data' => $result
        ]);
    }

    public function actionSets()
    {
        ini_set('memory_limit', '1024M');
        $result = [];
        $date = new \DateTime();
        for ($i = 0; $i < 3; $i++) {
            $result[$date->format('Y-m-01')] = [];
            $result[$date->format('Y-m-01')]['invoices'] = Invoice::find()
                                                                ->andWhere(['>=', 'created_at', $date->format('Y-m-01 00:00:01')])
                                                                ->andWhere(['<=', 'created_at', $date->format('Y-m-t 23:59:59')])
                                                                ->andWhere('sets_id IS NOT NULL')
                                                                ->all();

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
            $result[$date->format('Y-m-01')]['month'] = $arr[$month];
            $result[$date->format('Y-m-01')]['total'] = 0;
            $result[$date->format('Y-m-01')]['products'] = [];

            $data = [];
            $total = 0;
            /** @var Invoice $item */
            if (empty($result[$date->format('Y-m-01')]['invoices'])) {
                $date->modify('-1 month');
                continue;
            }
            foreach ($result[$date->format('Y-m-01')]['invoices'] as $item) {
                if (empty($data[$item->sets_id])) {
                    $data[$item->sets_id] = [
                        'count' => 0,
                        'sets_id' => $item->sets_id,
                    ];
                }
                $data[$item->sets_id]['count']++;
            }
            usort($data, function ($a, $b) {
                if ($a['count'] == $b['count']) {
                    return 0;
                }
                return ($a['count'] > $b['count']) ? -1 : 1;
            });

            $result[$date->format('Y-m-01')]['total'] = $total;
            $result[$date->format('Y-m-01')]['products'] = $data;

            $date->modify('-1 month');
        }

        return $this->render('sets', [
            'data' => $result
        ]);
    }

    public function actionDeposits()
    {
        ini_set('memory_limit', '1024M');
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