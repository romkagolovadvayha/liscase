<?php
namespace console\controllers;

use common\components\bansystem\BanSystemApi;
use common\components\oauth\Steam;
use common\models\bans\Bans;
use common\models\bansystem\BanList;
use common\models\statistics\Reports;
use common\models\user\User;
use yii\console\Controller;
use yii\helpers\HtmlPurifier;

class RustotekaController extends Controller
{
    /**
     * rustoteka/test
     */
    public function actionTest()
    {
        echo (Steam::hasLinkProfile("76561199615706587") ? 1 : 0) . PHP_EOL;
        echo (Steam::hasLinkProfile("https://steamcommunity.com/id/eptromsa/") ? 1 : 0) . PHP_EOL;
        echo (Steam::hasLinkProfile("https://steamcommunity.com/profiles/76561199615706587") ? 1 : 0) . PHP_EOL;
        echo Steam::getSteamId("https://steamcommunity.com/profiles/76561199615706587/") . PHP_EOL;
        echo Steam::getSteamId("https://steamcommunity.com/idlk/3csdfczdxcvxvx2") . PHP_EOL;
    }

    /**
     * rustoteka/replace
     */
    public function actionReplace() {
        /** @var BanList[] $list */
        $list = BanList::find()
            ->andWhere(['LIKE', 'project_name', '%Сервер удален%', false])
            ->all();

        foreach ($list as $item) {
            $item->project_name = str_replace('<i>', '', $item->project_name);
            $item->project_name = str_replace(' [Сервер удалён]</i>', '', $item->project_name);
            $item->save();
        }
        /** @var BanList[] $list */
        $list = BanList::find()
            ->andWhere(['LIKE', 'project_name', '%Баны %', false])
            ->all();

        foreach ($list as $item) {
            $item->project_name = str_replace('Баны ', '', $item->project_name);
            $item->save();
        }

        /** @var BanList[] $list */
        $list = BanList::find()
                       ->andWhere(['LIKE', 'server_name', '%Сервер удален%', false])
                       ->all();

        foreach ($list as $item) {
            $item->server_name = str_replace('<i>', '', $item->server_name);
            $item->server_name = str_replace(' [Сервер удалён]</i>', '', $item->server_name);
            $item->save();
        }

        $list = BanList::find()
                       ->orWhere(['LIKE', 'project_name', '%Rustorion #%', false])
                       ->orWhere(['LIKE', 'project_name', '%RUSTJOKER [%', false])
                       ->orWhere(['LIKE', 'project_name', '%Ultimate #%', false])
                       ->orWhere(['LIKE', 'project_name', '%FURY RUST #%', false])
                       ->orWhere(['LIKE', 'project_name', '%#FUNRUST%', false])
                       ->orWhere(['LIKE', 'project_name', '%X-Ray Rust #%', false])
                       ->orWhere(['LIKE', 'project_name', '%Age of Hardcore #%', false])
                       ->all();

        foreach ($list as $item) {
            $projectName = null;
            $serverName = null;
            if (strpos($item->project_name, 'Rustorion ') !== false) {
                $projectName = "Rustorion";
                $serverName = str_replace('Rustorion ', '', $item->project_name);
            }
            if (strpos($item->project_name, 'RUSTJOKER ') !== false) {
                $projectName = "RUSTJOKER";
                $serverName = str_replace('RUSTJOKER ', '', $item->project_name);
            }
            if (strpos($item->project_name, 'Rust Room') !== false && strpos($item->project_name, 'MAIN') !== false) {
                $projectName = "Rust Room";
                $serverName = str_replace('Rust Room ', '', $item->project_name);
            }
            if (strpos($item->project_name, 'Ultimate ') !== false) {
                $projectName = "Ultimate";
                $serverName = str_replace('Ultimate ', '', $item->project_name);
            }
            if (strpos($item->project_name, '#FUNRUST | ') !== false) {
                $projectName = "FUNRUST";
                $serverName = str_replace('#FUNRUST | ', '', $item->project_name);
            }
            if (strpos($item->project_name, 'X-Ray Rust #') !== false) {
                $projectName = "X-Ray Rust";
                $serverName = str_replace('X-Ray Rust ', '', $item->project_name);
            }
            if (strpos($item->project_name, 'Age of Hardcore #') !== false) {
                $projectName = "Age of Hardcore";
                $serverName = str_replace('Age of Hardcore ', '', $item->project_name);
            }
            if (!empty($projectName)) {
                $item->project_name = $projectName;
                $item->server_name = $serverName;
                $item->save();
            }
        }
    }

    /**
     * rustoteka/ban-import
     */
    public function actionBanImport() {
        $projects = [
//            BanSystemApi::TYPE_GGRUST,
//            BanSystemApi::TYPE_RUSTROOM,
//            BanSystemApi::TYPE_RUSTUSSR,
//            BanSystemApi::TYPE_MAGICRUST,
////            BanSystemApi::TYPE_BRORUST,
//            BanSystemApi::TYPE_GRANDRUST,
////            BanSystemApi::TYPE_MOSKOV77,
//            BanSystemApi::TYPE_SLABIYRUST,
////            BanSystemApi::TYPE_JOKERRUST,
//            BanSystemApi::TYPE_RUSTCHEATCHECK,
//            BanSystemApi::TYPE_PROSTOJ,
            BanSystemApi::TYPE_RUSTAPP,
        ];

        foreach ($projects as $project) {
            $banSystem = BanSystemApi::getInstance($project);
            $banList = $banSystem->banList();
            foreach ($banList as $item) {
                $user = User::findBySteamId($item['steam_id']);
                $model = new Bans();
                $model->username = HtmlPurifier::process($item['username']);
                $model->steam_id = $item['steam_id'];
                $model->server_id = $item['server_id'];
                $model->reason = $item['reason'];
                $model->banned_at = $item['date'];
                $model->unbanned_at = $item['expireDate'];
                $model->user_id = $user->id;
                $model->ip = $item['ip'];
                $model->save();
                try {
                    if (date('Y-m-d') === date('Y-m-d', strtotime($model->banned_at))) {
                        /** @var Reports[] $reports */
                        $reports = Reports::find()
                                          ->andWhere(['recepient_steam_id' => $model->steam_id])
                                          ->andWhere(['>=', 'created_at', (new \DateTime())->modify('-21 day')->format('Y-m-d H:i:s')])
                                          ->all();

                        foreach ($reports as $report) {
                            $_user = User::findBySteamId($report->steam_id);
                            if (!empty($_user) && $_user->ban_notify && !empty($_user->telegram_chat_id)) {
                                \Yii::$app->personalBotTelegram->sendMessage($_user->telegram_chat_id, "⛔️Игрок {$model->username}, на которого вы жаловались забанен по причине {$model->reason}!");
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Yii::$app->telegramChats->sendMessage("actionBanImport\nFile: {$e->getFile()}:{$e->getLine()}\nError: {$e->getMessage()}");
                }
            }
            echo "project: {$project}; count: " . count($banList) . PHP_EOL;
        }
    }

}