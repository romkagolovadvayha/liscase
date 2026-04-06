<?php

namespace api\controllers;

use common\components\clan\GamePluginClanListBuilder;
use Yii;
use yii\web\Controller;
use yii\web\Response;

/**
 * Legacy-эндпоинт списка кланов для плагина (тот же формат, что GET /v1/clans/list).
 */
class ClansController extends Controller
{
    public function actionList()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $ip = trim((string) Yii::$app->request->get('ip', ''));
        $portRaw = Yii::$app->request->get('port');
        $port = $portRaw !== null && $portRaw !== '' ? (int) $portRaw : null;

        if ($ip === '' || $port === null || $port <= 0) {
            Yii::$app->response->statusCode = 400;

            return ['error' => 'ip and port are required'];
        }

        return GamePluginClanListBuilder::buildForIpPort($ip, $port);
    }
}
