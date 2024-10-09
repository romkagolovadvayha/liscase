<?php
namespace console\controllers;

use common\components\bansystem\BanSystemApi;
use common\components\oauth\Steam;
use common\models\bansystem\BanList;
use yii\console\Controller;

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
            BanSystemApi::TYPE_PROSTOJ,
        ];

        foreach ($projects as $project) {
            $banSystem = BanSystemApi::getInstance($project);
            $count = 0;
            foreach ($banSystem->banList() as $item) {
                BanList::createModel(
                    $item['steam_id'],
                    $item['project'],
                    $item['server'],
                    $item['reason'],
                    $item['date'],
                    $item['expireDate'],
                );
                if ($count > 30) {
                    break;
                }
                $count++;
            }
        }
    }

}