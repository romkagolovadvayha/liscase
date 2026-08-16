<?php

namespace api\controllers;

use common\components\queue\stats\SaveClanCupboardsJob;
use common\components\queue\stats\SaveRaidJob;
use common\components\queue\stats\SaveSignsJob;
use common\components\queue\stats\SaveStatsJob;
use common\components\queue\stats\SaveTeamsJob;
use common\components\queue\stats\UpdateUsersJob;
use common\controllers\WebController;
use Yii;
use yii\filters\Cors;
use yii\web\UnauthorizedHttpException;

class StatsController extends WebController
{
    public $enableCsrfValidation = false;

    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'cors' => [
                'class' => Cors::class,
                'cors' => [
                    'Origin' => ['*'],
                    'Access-Control-Request-Method' => ['GET', 'POST', 'OPTIONS'],
                    'Access-Control-Request-Headers' => ['*'],
                ],
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    public function actionUpdate($serverTag) {
        $rawBody = Yii::$app->request->getRawBody();
        $this->validateIngestSignature($serverTag, $rawBody);
        Yii::$app->queueStats->push(new SaveStatsJob([
            'data' => $rawBody,
            'serverTag' => $serverTag,
        ]));
        Yii::$app->response->setStatusCode(202);
    }

    public function actionUpdateUsers($serverTag) {
        $rawBody = Yii::$app->request->getRawBody();
        $this->validateIngestSignature($serverTag, $rawBody);
        Yii::$app->queueOnline->push(new UpdateUsersJob([
            'data' => $rawBody,
            'serverTag' => $serverTag,
        ]));
        Yii::$app->response->setStatusCode(202);
    }

    public function actionRaid($serverTag) {
        $rawBody = Yii::$app->request->getRawBody();
        $this->validateIngestSignature($serverTag, $rawBody);
        Yii::$app->queueRaid->push(new SaveRaidJob([
            'data' => $rawBody,
            'serverTag' => $serverTag,
        ]));
        Yii::$app->response->setStatusCode(202);
    }

    public function actionTeams() {
        $rawBody = Yii::$app->request->getRawBody();
        $this->validateIngestSignature('teams', $rawBody);
        Yii::$app->queueTeam->push(new SaveTeamsJob([
            'data' => $rawBody,
            'ip' => $_SERVER['REMOTE_ADDR'],
        ]));
        Yii::$app->response->setStatusCode(202);
    }

    public function actionSigns() {
        $rawBody = Yii::$app->request->getRawBody();
        $this->validateIngestSignature('signs', $rawBody);
        Yii::$app->queueProcess->push(new SaveSignsJob([
            'data' => $rawBody,
            'ip' => $_SERVER['REMOTE_ADDR'],
        ]));
        Yii::$app->response->setStatusCode(202);
    }

    public function actionClanCupboards()
    {
        $rawBody = Yii::$app->request->getRawBody();
        $this->validateIngestSignature('clan-cupboards', $rawBody);
        Yii::$app->queueProcess->push(new SaveClanCupboardsJob([
            'data' => $rawBody,
            'ip' => $_SERVER['REMOTE_ADDR'],
        ]));
        Yii::$app->response->setStatusCode(200);
        return [
            'success' => true,
            'queued' => true,
        ];
    }

    /**
     * При заданном PLUGIN_INGEST_SECRET требует подпись:
     * HMAC-SHA256("timestamp.serverTag.rawBody", secret). Старые инсталляции
     * без секрета продолжают работать, что позволяет выполнить поэтапный
     * rollout плагинов без простоя.
     */
    private function validateIngestSignature(string $serverTag, string $rawBody): void
    {
        $secret = (string)(Yii::$app->params['pluginIngestSecret'] ?? getenv('PLUGIN_INGEST_SECRET') ?: '');
        if ($secret === '') {
            return;
        }
        $timestamp = (string)Yii::$app->request->headers->get('X-Ingest-Timestamp', '');
        $signature = strtolower((string)Yii::$app->request->headers->get('X-Ingest-Signature', ''));
        if (!ctype_digit($timestamp) || abs(time() - (int)$timestamp) > 300) {
            throw new UnauthorizedHttpException('Invalid ingest timestamp');
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $serverTag . '.' . $rawBody, $secret);
        if ($signature === '' || !hash_equals($expected, $signature)) {
            throw new UnauthorizedHttpException('Invalid ingest signature');
        }
    }
}
