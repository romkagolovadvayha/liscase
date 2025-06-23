<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\components\queue\process\MapGenerateJob;
use common\models\box\Drop;
use common\models\box\DropBlocked;
use common\models\map\Map;
use common\models\profit\Profit;
use common\models\promocode\Promocode;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\stats\Wipe;
use common\models\user\User;
use common\models\user\UserPromocode;
use common\models\user\UserTask;
use common\models\user\UserTop;
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

    /**
     * @throws \yii\db\StaleObjectException
     * @throws \Throwable
     */
    public function actionBlock($id)
    {
        $cacheKey = "WIPE_actionBlock_{$id}";
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        /** @var Drop[] $drops */
        $drops = Drop::find()
            ->all();
        DropBlocked::unBlocked($id);
        foreach ($drops as $drop) {
            if (!empty($drop->blocked_hour)) {
                $date = new \DateTime();
                $date->modify("+{$drop->blocked_hour} hour");
                DropBlocked::createRecord($drop->id, $id, $date->format('Y-m-d H:i:s'));
            }
        }
        Yii::$app->cache->set($cacheKey, 1, 5*60);

        $cacheKeyGetBlocked = "DropBlocked_getBlocked_" . $id;
        Yii::$app->cache->delete($cacheKeyGetBlocked);

        Yii::$app->session->addFlash('success', 'Предметы успешно заблокированы!');
        return $this->redirect('index');
    }

    public function actionSelectMap($id)
    {
        $cacheKey = "WIPE_actionSelectMap_{$id}";
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        Yii::$app->cache->set($cacheKey, 1, 30*60);
        /** @var Map $map */
        $map = Map::find()
            ->andWhere(['server_id' => $id])
            ->andWhere(['is_archive' => 0])
            ->orderBy(['votes' => SORT_DESC])
            ->one();

        $server = Servers::findOne($id);
        $server->map_id = $map->id;
        $server->save(false);

        Yii::$app->session->addFlash('success', 'Карта успешно зафиксирована!');
        return $this->redirect('index');
    }

    public function actionGenerateMap($id)
    {
        $cacheKey = "WIPE_actionGenerateMap_{$id}";
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        Yii::$app->cache->set($cacheKey, 1, 5*60);

        \Yii::$app->queueProcess->push(new MapGenerateJob(['serverId'  => $id]));
        Yii::$app->session->addFlash('success', 'Генерирация запущена!');
        return $this->redirect('index');
    }

    public function actionTop($server, $wipe = null)
    {
        $cacheKey = "WIPE_actionTop_{$server}";
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        Yii::$app->cache->set($cacheKey, 1, 30*60);
        ini_set('memory_limit', '512M');
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['IN', 'tag', $server])
                          ->andWhere(['IN', 'status', [Servers::STATUS_NOACTIVE, Servers::STATUS_ACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();
        foreach ($servers as $server) {
            if (empty($wipe)) {
                $wipe = $server->currentWipe();
            }
            $tops = UserTop::getUserTops($server, $wipe);
            foreach ($tops as $top) {
                $value = $top['label'];
                foreach ($top['items'] as $i => $item) {
                    $user                    = User::findBySteamId($item['steam_id'], false, 'top');
                    $profit                  = new Profit();
                    $profit->status          = 1;
                    $profit->type            = Profit::TYPE_TOP;
                    $profit->amount          = $item['amount'];
                    $profit->user_balance_id = $user->getPersonalBalance()->id;
                    $profit->comment         = "Награда за первое место в топе \"{$value}\"";
                    if ($i === 1) {
                        $profit->comment = "Награда за второе место в топе \"{$value}\"";
                    } elseif ($i === 2) {
                        $profit->comment = "Награда за третье место в топе \"{$value}\"";
                    }
                    if (!empty($user->telegram_chat_id)) {
                        $text = "🥇 Награда за первое место в топе \"{$value}\" - <b>{$profit->amount} РУБ</b>";
                        if ($i === 1) {
                            $text = "🥈 Награда за второе место в топе \"{$value}\" - <b>{$profit->amount} РУБ</b>";
                        } elseif ($i === 2) {
                            $text = "🥉 Награда за третье место в топе \"{$value}\" - <b>{$profit->amount} РУБ</b>";
                        }
                        if (!empty($tgMessage[$user->steam_id])) {
                            $tgMessage[$user->steam_id] .= PHP_EOL . $text;
                        } else {
                            $tgMessage[$user->steam_id] = "Вам начислены награды за ТОП на сервере "
                                . $server->name . PHP_EOL . $text;
                        }
                    }
                    $profit->created_at = date('Y-m-d H:i:s');
                    $profit->save(false);
                }
            }
        }
        if (YII_ENV_PROD) {
            foreach ($tgMessage as $steamId => $message) {
                $user = User::findBySteamId($steamId, false, 'top2');
                Yii::$app->personalBotTelegram->sendMessage($user->telegram_chat_id, $message);
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

    public function actionClearCache()
    {
        Yii::$app->runAction('translate/clear-translate-cache');

        Yii::$app->session->addFlash('success', 'Кэш очищен!');
        return $this->redirect('index');
    }

}