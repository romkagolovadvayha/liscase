<?php

namespace console\controllers;

use common\models\invoice\Deposit;
use common\models\mirrors\Mirrors;
use common\models\servers\Servers;
use common\models\skindrops\SkindropsLink;
use common\models\statistics\Statistics;
use common\models\stats\Info;
use common\models\user\User;
use yii\base\BaseObject;
use yii\console\Controller;

class MirrorController extends Controller
{
    /**
     * Отчет по зеркалам
     * mirror/report
     *
     * @throws \Exception
     */
    public function actionReport()
    {
        $date = new \DateTime();
        $date->modify('-1 day');
        /** @var Mirrors[] $models */
        $models = Mirrors::find()
                           ->andWhere(['>=', 'created_at', $date->format('Y-m-d 00:00:00')])
                           ->andWhere(['<=', 'created_at', $date->format('Y-m-d 23:59:59')])
                           ->all();

        $countUsersReg = User::find()
                           ->andWhere(['is_mirror_registration' => 1])
                           ->andWhere(['>=', 'created_at', $date->format('Y-m-d 00:00:00')])
                           ->andWhere(['<=', 'created_at', $date->format('Y-m-d 23:59:59')])
                           ->count();
        $totalCountUsersReg = User::find()
                           ->andWhere(['is_mirror_registration' => 1])
                           ->count();
        $countUsers = User::find()
                           ->andWhere(['is_mirror_returned' => 1])
                           ->andWhere(['>=', 'created_at', $date->format('Y-m-d 00:00:00')])
                           ->andWhere(['<=', 'created_at', $date->format('Y-m-d 23:59:59')])
                           ->count();
        $totalCountUsers = User::find()
                           ->andWhere(['is_mirror_returned' => 1])
                           ->count();

        $depositSum = Deposit::find()
                           ->alias('d')
                           ->joinWith(['user u'])
                           ->andWhere(['d.status' => Deposit::STATUS_SUCCESS])
                           ->andWhere(['OR', ['u.is_mirror_registration' => 1], ['u.is_mirror_returned' => 1]])
                           ->sum('amount');

        $depositSumDay = Deposit::find()
                           ->alias('d')
                           ->joinWith(['user u'])
                           ->andWhere(['d.status' => Deposit::STATUS_SUCCESS])
                           ->andWhere(['>=', 'd.created_at', $date->format('Y-m-d 00:00:00')])
                           ->andWhere(['<=', 'd.created_at', $date->format('Y-m-d 23:59:59')])
                           ->andWhere(['OR', ['u.is_mirror_registration' => 1], ['u.is_mirror_returned' => 1]])
                           ->sum('amount');

        $message = "💰️ <b>Отчет по зеркалам за {$date->format('d.m.Y')}</b>" . PHP_EOL . PHP_EOL;

        $types = [];
        $count = [];
        $totalDay = 0;
        $playtimes = [];
        $playtimes30 = 0;
        $playtimes60 = 0;
        foreach ($models as $model) {
            $hash = md5($model->mirror_name);
            if (!in_array($hash, $types)) {
                $types[$hash] = $model->mirror_name;
            }
            if (empty($count[$hash])) {
                $count[$hash] = 0;
            }
            if (empty($playtimes[$hash])) {
                $playtimes[$hash] = 0;
                /** @var Statistics $stats */
                $stats = Statistics::find()
                                   ->andWhere(['steam_id' => $model->steam_id])
                                   ->andWhere(['key' => 'playtime'])
                                   ->orderBy(['id' => SORT_DESC])
                                   ->one();

                if (!empty($stats)) {
                    $playtimes[$hash] = $stats->value;
                }

                if ($playtimes[$hash] > 30) {
                    $playtimes30++;
                }
                if ($playtimes[$hash] > 60) {
                    $playtimes60++;
                }
            }
            $count[$hash]++;


            $totalDay++;
        }
        foreach ($types as $hash => $type) {
            $message .= "<i>{$type}</i>" . PHP_EOL;
            $message .= "Количество подключений: {$count[$hash]}" . PHP_EOL;
            $message .= "Новых участников: {$count[$hash]}" . PHP_EOL;
            $message .= PHP_EOL;
        }

        $message .= PHP_EOL;
        $message .= "Всего подключений за день: {$totalDay}" . PHP_EOL;
        $message .= "Новых с зеркал за день: {$countUsersReg}" . PHP_EOL;
        $message .= "Старичков с зеркал за день: {$countUsers}" . PHP_EOL;
        $message .= "Донатов с зеркал за день: {$depositSumDay}" . PHP_EOL;
        $message .= "Поиграло более 30 минут за день: {$playtimes30}" . PHP_EOL;
        $message .= "Поиграло более 60 минут за день: {$playtimes60}" . PHP_EOL;
        $message .= PHP_EOL;
        $message .= "Всего новых с зеркал за день: {$totalCountUsersReg}" . PHP_EOL;
        $message .= "Всего старичков с зеркал за день: {$totalCountUsers}" . PHP_EOL;
        $message .= "Всего донатов с зеркал: {$depositSum}";

        \Yii::$app->telegramReport->sendMessage($message);
    }
}
