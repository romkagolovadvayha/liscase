<?php

namespace api\controllers;

use common\components\queue\stats\SaveClanCupboardsJob;
use common\components\queue\stats\SaveRaidJob;
use common\components\queue\stats\SaveSignsJob;
use common\components\queue\stats\SaveStatsJob;
use common\components\queue\stats\SaveTeamsJob;
use common\components\queue\stats\UpdateUsersJob;
use common\controllers\WebController;
use common\models\servers\Servers;
use Yii;
use yii\filters\Cors;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\RequestEntityTooLargeHttpException;
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
        if (strlen($rawBody) > 5 * 1024 * 1024) {
            throw new RequestEntityTooLargeHttpException('Statistics payload is too large');
        }
        $this->validateIngestSignature(
            $serverTag,
            $rawBody,
            'pluginStatsIngestSecret',
            'PLUGIN_STATS_INGEST_SECRET'
        );
        $payload = $this->validateStatsPayload($serverTag, $rawBody);
        $normalizedBody = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $batchId = isset($payload['batch_id']) ? strtolower((string)$payload['batch_id']) : null;
        $dedupeKey = $batchId === null ? null : 'stats-ingest:' . hash('sha256', $serverTag . ':' . $batchId);

        if ($dedupeKey !== null && !Yii::$app->cache->add($dedupeKey, 1, 7 * 86400)) {
            Yii::$app->response->setStatusCode(202);
            return ['success' => true, 'queued' => false, 'duplicate' => true];
        }

        try {
            Yii::$app->queueStats->push(new SaveStatsJob([
                'data' => $normalizedBody,
                'serverTag' => $serverTag,
                'batchId' => $batchId,
            ]));
        } catch (\Throwable $e) {
            if ($dedupeKey !== null) {
                Yii::$app->cache->delete($dedupeKey);
            }
            throw $e;
        }
        Yii::$app->response->setStatusCode(202);
        return ['success' => true, 'queued' => true, 'batch_id' => $batchId];
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
    private function validateIngestSignature(
        string $serverTag,
        string $rawBody,
        ?string $specificParam = null,
        ?string $specificEnv = null
    ): void
    {
        $specificSecret = '';
        if ($specificParam !== null) {
            $specificSecret = (string)(Yii::$app->params[$specificParam] ?? '');
        }
        if ($specificSecret === '' && $specificEnv !== null) {
            $specificSecret = (string)(getenv($specificEnv) ?: '');
        }
        $secret = $specificSecret !== ''
            ? $specificSecret
            : (string)(Yii::$app->params['pluginIngestSecret'] ?? getenv('PLUGIN_INGEST_SECRET') ?: '');
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

    /**
     * Reject malformed or unbounded statistics payloads before they enter Redis.
     * Older plugins remain compatible: batch_id is optional.
     */
    private function validateStatsPayload(string $serverTag, string $rawBody): array
    {
        if (!preg_match('/^[\w-]{1,64}$/u', $serverTag)) {
            throw new BadRequestHttpException('Invalid server tag');
        }
        if (strlen($rawBody) > 5 * 1024 * 1024) {
            throw new RequestEntityTooLargeHttpException('Statistics payload is too large');
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
            throw new BadRequestHttpException('Invalid JSON payload');
        }

        foreach (['users', 'kills', 'teams', 'reports', 'chats', 'server'] as $key) {
            if (!isset($payload[$key]) || !is_array($payload[$key])) {
                throw new BadRequestHttpException("Missing or invalid {$key}");
            }
        }
        if (count($payload['users']) > 5000 || count($payload['kills']) > 10000
            || count($payload['teams']) > 10000 || count($payload['reports']) > 2000
            || count($payload['chats']) > 10000) {
            throw new RequestEntityTooLargeHttpException('Too many statistics events');
        }

        if (isset($payload['batch_id'])
            && !preg_match('/^(?:[a-f0-9]{32}|[a-f0-9-]{36})$/i', (string)$payload['batch_id'])) {
            throw new BadRequestHttpException('Invalid batch_id');
        }

        foreach ($payload['users'] as $steamId => &$metrics) {
            if (!preg_match('/^\d{17}$/', (string)$steamId) || !is_array($metrics) || count($metrics) > 1000) {
                throw new BadRequestHttpException('Invalid user statistics');
            }
            $normalizedMetrics = [];
            foreach ($metrics as $key => $value) {
                if (!is_string($key)) {
                    throw new BadRequestHttpException('Invalid statistics metric');
                }
                $normalizedKey = str_replace(' ', '_', $key);
                if (!preg_match('/^[a-zA-Z0-9_.-]{1,128}$/', $normalizedKey)
                    || !is_int($value) || $value < 0 || $value > 2147483647) {
                    throw new BadRequestHttpException('Invalid statistics metric');
                }
                $normalizedMetrics[$normalizedKey] = min(
                    2147483647,
                    ($normalizedMetrics[$normalizedKey] ?? 0) + $value
                );
            }
            $metrics = $normalizedMetrics;
        }
        unset($metrics);

        foreach ($payload['kills'] as $event) {
            if (!is_array($event)
                || !$this->isSteamId($event['steam_id'] ?? null)
                || !in_array($event['type'] ?? null, ['kill', 'animal', 'deaths'], true)
                || !is_string($event['dead'] ?? null) || strlen($event['dead']) > 255
                || !is_string($event['weapon'] ?? '') || strlen($event['weapon'] ?? '') > 255
                || !is_int($event['distance'] ?? null) || $event['distance'] < 0 || $event['distance'] > 100000
                || !is_array($event['inventoryWear'] ?? null) || count($event['inventoryWear']) > 128
                || !is_array($event['signs'] ?? null) || count($event['signs']) > 32
                || !$this->areShortStrings($event['inventoryWear'], 255)
                || !$this->areShortStrings($event['signs'], 64)
                || !$this->isEventId($event['event_id'] ?? null)) {
                throw new BadRequestHttpException('Invalid kill event');
            }
        }
        foreach ($payload['chats'] as $event) {
            if (!is_array($event) || !$this->isSteamId($event['steam_id'] ?? null)
                || !is_string($event['message'] ?? null) || $event['message'] === ''
                || strlen($event['message']) > 8000 || !$this->isEventId($event['event_id'] ?? null)) {
                throw new BadRequestHttpException('Invalid chat event');
            }
        }
        foreach ($payload['reports'] as $event) {
            if (!is_array($event) || !$this->isSteamId($event['steam_id'] ?? null)
                || !$this->isSteamId($event['recepient_steam_id'] ?? null)
                || !is_string($event['reason'] ?? null) || strlen($event['reason']) > 16000
                || !$this->isEventId($event['event_id'] ?? null)) {
                throw new BadRequestHttpException('Invalid report event');
            }
        }

        foreach (['online', 'join', 'queue'] as $key) {
            if (!array_key_exists($key, $payload['server']) || !is_int($payload['server'][$key])
                || $payload['server'][$key] < 0 || $payload['server'][$key] > 100000) {
                throw new BadRequestHttpException("Invalid server.{$key}");
            }
        }

        if (!Servers::find()->andWhere(['tag' => $serverTag])->cache(60)->exists()) {
            // A 4xx response keeps the batch in the plugin's local retry store,
            // rather than acknowledging and silently dropping a mistagged batch.
            throw new NotFoundHttpException('Unknown server tag');
        }

        return $payload;
    }

    private function isSteamId($value): bool
    {
        return is_string($value) && preg_match('/^\d{17}$/', $value) === 1;
    }

    private function isEventId($value): bool
    {
        // Missing IDs are accepted for rolling upgrades from ExpertStatistics 1.1.
        return $value === null
            || (is_string($value) && preg_match('/^(?:[a-f0-9]{32}|[a-f0-9-]{36})$/i', $value) === 1);
    }

    private function areShortStrings(array $values, int $maxBytes): bool
    {
        foreach ($values as $value) {
            if (!is_string($value) || strlen($value) > $maxBytes) {
                return false;
            }
        }
        return true;
    }
}
