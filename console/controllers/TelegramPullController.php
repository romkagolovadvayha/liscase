<?php
namespace console\controllers;

use common\components\telegram\TelegramNewsService;
use Yii;
use yii\console\Controller;
use common\components\telegram\TelegramPublicPullService;

class TelegramPullController extends Controller
{
    /**
     * telegram-pull/publish
     */
    public function actionPublish($id = 20)
    {
        Yii::$container->get(TelegramNewsService::class)->publish($id);
    }

    public function actionRun($usernamesCsv = null, $limit = 20)
    {
        /** @var TelegramPublicPullService $svc */
        $svc = Yii::$container->get(TelegramPublicPullService::class);

        $list = $usernamesCsv
            ? array_filter(array_map('trim', explode(',', $usernamesCsv)))
            : array_filter(array_map('trim', explode(',', Yii::$app->settings->get('telegram_parser_source_usernames') ?: '')));

        if (!$list) {
            $this->stderr("No source usernames configured.\n");
            return 1;
        }

        $total = 0;
        foreach ($list as $u) {
            $cnt = $svc->pullUsername($u, (int)$limit);
            $this->stdout(sprintf("%s: +%d\n", $u, $cnt));
            $total += $cnt;
        }
        $this->stdout("Total: {$total}\n");
        return 0;
    }
}
