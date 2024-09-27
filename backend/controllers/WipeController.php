<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\models\box\Drop;
use common\models\profit\Profit;
use common\models\promocode\Promocode;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\stats\Wipe;
use common\models\user\User;
use common\models\user\UserPromocode;
use common\models\user\UserTask;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use Yii;

class WipeController extends Controller
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

    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionBlock()
    {
        /** @var Drop[] $drops */
        $drops = Drop::find()
            ->all();

        foreach ($drops as $drop) {
            if (!empty($drop->blocked_hour)) {
                $date = new \DateTime();
                $date->modify("+{$drop->blocked_hour} hour");
                $drop->blocked_at = $date->format('Y-m-d H:i:s');
                $drop->save();
            }
        }
        Yii::$app->session->addFlash('success', 'Предметы успешно заблокированы!');
        return $this->redirect('index');
    }

    public function actionTop($server)
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['IN', 'tag', $server])
                          ->andWhere(['status' => Servers::STATUS_ACTIVE])
                          ->all();
        foreach ($servers as $server) {
            $stats = Statistics::getStats($server, null, false);
            if (empty($stats)) {
                continue;
            }
            $tops = [
                'kills' => 'Киллер',
                'scientists' => 'Мирный',
                'hunter' => 'Охотник',
                'fermer' => 'Фермер',
                'farmer' => 'Фармер',
                'fishing' => 'Рыбак',
                'playtime' => 'Онлайн',
                'reider' => 'Рейдер',
            ];
            $amount = [500, 150, 50];
            $tgMessage = [];
            foreach ($tops as $type => $value) {
                for ($i = 0; $i < 3; $i++) {
                    $userStats = Statistics::getTopWidgetItem($type, $stats, $i);
                    if (!empty($userStats) && !empty($userStats['user'])) {
                        $profit                  = new Profit();
                        $profit->status          = 1;
                        $profit->type            = Profit::TYPE_TOP;
                        $profit->amount          = $amount[$i];
                        $profit->user_balance_id = $userStats['user']->getPersonalBalance()->id;
                        $profit->comment         = "Награда за первое место в топе \"{$value}\"";
                        if ($i === 1) {
                            $profit->comment = "Награда за второе место в топе \"{$value}\"";
                        } elseif ($i === 2) {
                            $profit->comment = "Награда за третье место в топе \"{$value}\"";
                        }
                        if (!empty($userStats['user']->telegram_chat_id)) {
                            $text         = "🥇 Награда за первое место в топе \"{$value}\" - <b>{$profit->amount} РУБ</b>";
                            if ($i === 1) {
                                $text = "🥈 Награда за второе место в топе \"{$value}\" - <b>{$profit->amount} РУБ</b>";
                            } elseif ($i === 2) {
                                $text = "🥉 Награда за третье место в топе \"{$value}\" - <b>{$profit->amount} РУБ</b>";
                            }
                            if (!empty($tgMessage[$userStats['user']->steam_id])) {
                                $tgMessage[$userStats['user']->steam_id] .= PHP_EOL . $text;
                            } else {
                                $tgMessage[$userStats['user']->steam_id] = "Вам начислены награды за ТОП на сервере " . $server->name . PHP_EOL . $text;
                            }
                        }
                        $profit->created_at      = date('Y-m-d H:i:s');
                        $profit->save(false);
                        $userStats['user']->getPersonalBalance()->recalculateBalance();
                    }
                }
            }
            if (YII_ENV_PROD) {
                foreach ($tgMessage as $steamId => $message) {
                    $user = User::findBySteamId($steamId);
                    Yii::$app->personalBotTelegram->sendMessage($user->telegram_chat_id, $message);
                }
            }
        }

        Yii::$app->session->addFlash('success', 'Награды распределены успешно!');
        return $this->redirect('index');
    }

    public function actionPromocode()
    {
        /** @var UserPromocode[] $uPromocodes */
        $uPromocodes = UserPromocode::find()
            ->andWhere(['promocode_id' => 2])
            ->all();

        foreach ($uPromocodes as $item) {
            $item->delete();
        }

        Yii::$app->session->addFlash('success', 'Промокод теперь можно ввести заного!');
        return $this->redirect('index');
    }

    public function actionTaskClear()
    {
        /** @var UserTask[] $items */
        $items = UserTask::find()
            ->all();

        foreach ($items as $item) {
            $item->delete();
        }

        Yii::$app->session->addFlash('success', 'Задания обнулены!');
        return $this->redirect('index');
    }

}