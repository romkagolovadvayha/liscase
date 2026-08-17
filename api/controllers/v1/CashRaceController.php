<?php

namespace api\controllers\v1;

use api\components\jwt\JwtAuthFilter;
use common\components\tournament\CashRaceService;
use common\models\servers\Servers;
use common\models\tournament\CashRaceScore;
use common\models\tournament\CashRaceTerminalSession;
use common\models\tournament\CashRaceTournament;
use common\models\tournament\Tournament;
use common\models\user\User;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;

class CashRaceController extends BaseApiController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = ['class' => JwtAuthFilter::class, 'throwException' => false];
        return $behaviors;
    }

    /** Website payload. Private preview: staff and the configured Steam ID only. */
    public function actionIndex()
    {
        $user = Yii::$app->user->identity;
        if (!$user instanceof User) throw new UnauthorizedHttpException('Войдите через Steam');
        $config = CashRaceService::findCurrent($user->server_id ? (int)$user->server_id : null) ?: CashRaceService::findCurrent();
        if (!$config) throw new NotFoundHttpException('Денежная гонка пока не запланирована');
        if (!CashRaceService::canPreview($user, $config) && $config->preview_only) throw new NotFoundHttpException('Раздел пока недоступен');
        $this->finalizeIfEnded($config);
        return $this->successResponse($this->payload($config, $user));
    }

    /** Lightweight server snapshot, cached by the Rust plugin between polls. */
    public function actionPluginStatus()
    {
        [$server, $body] = $this->pluginContext();
        $config = CashRaceService::findCurrent((int)$server->id);
        if (!$config) return $this->successResponse(['available' => false, 'poll_after' => 60]);
        $steamId = preg_replace('/\D+/', '', (string)($body['steam_id'] ?? Yii::$app->request->get('steam_id', '')));
        $user = $steamId ? User::find()->where(['steam_id' => $steamId])->one() : null;
        $serverAdmin = !empty($body['server_admin']) || Yii::$app->request->get('server_admin') === '1';
        $eligible = $user instanceof User && CashRaceService::canPlayerParticipate($user, $config, $serverAdmin);
        $this->finalizeIfEnded($config);
        $data = $this->payload($config, $eligible ? $user : null, 8);
        $data['available'] = true;
        $data['eligible'] = $eligible;
        $data['poll_after'] = 30;
        return $this->successResponse($data);
    }

    public function actionPluginMint()
    {
        [$server, $body] = $this->pluginContext();
        [$config, $user] = $this->activePlayer($server, $body);
        $tokens = CashRaceService::mint($config, $server, $user, (array)($body['tokens'] ?? []));
        return $this->successResponse(['tokens' => array_map(static fn($t) => $t->token_uuid, $tokens)]);
    }

    public function actionPluginLost()
    {
        [$server, $body] = $this->pluginContext();
        [$config, $user] = $this->activePlayer($server, $body, false);
        return $this->successResponse(['lost' => CashRaceService::markLost($config, $user, (array)($body['tokens'] ?? []))]);
    }

    public function actionPluginTerminalOpen()
    {
        [$server, $body] = $this->pluginContext();
        $config = CashRaceService::findCurrent((int)$server->id);
        if (!$config) throw new NotFoundHttpException('Турнир не найден');
        $terminal = CashRaceService::openTerminal($config, $server, $body);
        return $this->successResponse(['session_uuid' => $terminal->session_uuid, 'expires_at' => $terminal->expires_at]);
    }

    public function actionPluginTerminalClose()
    {
        [$server, $body] = $this->pluginContext();
        $config = CashRaceService::findCurrent((int)$server->id);
        if ($config) CashRaceService::closeTerminal($config, (string)($body['session_uuid'] ?? ''), !empty($body['destroyed']));
        return $this->successResponse(['closed' => true]);
    }

    public function actionPluginDeposit()
    {
        [$server, $body] = $this->pluginContext();
        [$config, $user] = $this->activePlayer($server, $body);
        $result = CashRaceService::deposit(
            $config, $server, $user, (string)($body['deposit_uuid'] ?? ''),
            (string)($body['terminal_uuid'] ?? ''), (array)($body['tokens'] ?? [])
        );
        return $this->successResponse($result);
    }

    private function payload(CashRaceTournament $config, ?User $user, int $leaderboardLimit = 20): array
    {
        $t = $config->tournament;
        $terminal = CashRaceTerminalSession::find()->where([
            'tournament_id' => $t->id, 'server_id' => $t->server_id,
            'status' => CashRaceTerminalSession::STATUS_ACTIVE,
        ])->andWhere(['>', 'expires_at', date('Y-m-d H:i:s')])->orderBy(['id' => SORT_DESC])->one();
        $score = $user ? CashRaceScore::findOne(['tournament_id' => $t->id, 'user_id' => $user->id]) : null;
        $rewards = [];
        foreach ($t->rewards as $reward) {
            $rewards[] = ['place' => (int)$reward->place, 'title' => $reward->title, 'subtitle' => $reward->subtitle, 'image' => $reward->getImageUrl()];
        }
        return [
            'id' => (int)$t->id, 'slug' => $t->slug, 'title' => $t->title,
            'description' => $t->description, 'phase' => $t->getPublicPhase(),
            'starts_at' => $t->starts_at, 'ends_at' => $t->ends_at,
            'server_now_unix' => time(),
            'starts_at_unix' => strtotime((string)$t->starts_at),
            'ends_at_unix' => strtotime((string)$t->ends_at),
            'server' => ['id' => (int)$t->server_id, 'tag' => $t->server ? $t->server->tag : '', 'name' => $t->server ? $t->server->name : ''],
            'prize_pool_label' => $t->prize_pool_label, 'rules' => $t->rules_text,
            'preview_only' => (bool)$config->preview_only,
            'mechanics' => [
                'drop_chance' => (float)$config->drop_chance, 'drop_min' => (int)$config->drop_min, 'drop_max' => (int)$config->drop_max,
                'key_shortname' => $config->key_shortname, 'key_skin_id' => (string)$config->key_skin_id,
                'terminal_active_seconds' => (int)$config->terminal_active_seconds,
                'terminal_cooldown_min_seconds' => (int)$config->terminal_cooldown_min_seconds,
                'terminal_cooldown_max_seconds' => (int)$config->terminal_cooldown_max_seconds,
                'terminal_prefab' => $config->terminal_prefab,
                'allowed_monuments' => $config->getAllowedMonumentsArray(),
            ],
            'terminal' => $terminal ? [
                'active' => true, 'session_uuid' => $terminal->session_uuid,
                'monument_key' => $terminal->monument_key, 'monument_name' => $terminal->monument_name,
                'expires_at' => $terminal->expires_at,
            ] : ['active' => false],
            'player' => $user ? [
                'steam_id' => (string)$user->steam_id, 'username' => $user->username, 'avatar' => $user->avatar,
                'keys_found' => $score ? (int)$score->keys_found : 0,
                'keys_lost' => $score ? (int)$score->keys_lost : 0,
                'keys_deposited' => $score ? (int)$score->keys_deposited : 0,
                'position' => $score && $score->position ? (int)$score->position : null,
            ] : null,
            'rewards' => $rewards,
            'leaderboard' => CashRaceService::leaderboard((int)$t->id, $leaderboardLimit),
        ];
    }

    private function pluginContext(): array
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) $body = [];
        $tag = trim((string)($body['server_tag'] ?? Yii::$app->request->get('server_tag', Yii::$app->request->headers->get('serverTag', ''))));
        $server = $tag !== '' ? Servers::findOne(['tag' => $tag]) : null;
        if (!$server) throw new ForbiddenHttpException('Сервер не распознан');
        if (YII_ENV === 'prod') {
            $remoteIp = trim((string)Yii::$app->request->userIP);
            $allowedIps = array_values(array_filter(array_unique([
                trim((string)$server->ip),
                trim((string)$server->text_ip),
            ])));
            if ($remoteIp === '' || !in_array($remoteIp, $allowedIps, true)) {
                Yii::warning("Cash Race rejected server request: tag={$tag}, remoteIp={$remoteIp}", 'cash-race');
                throw new ForbiddenHttpException('Источник запроса не совпадает с игровым сервером');
            }
        }
        return [$server, $body];
    }

    private function activePlayer(Servers $server, array $body, bool $requireActive = true): array
    {
        $config = CashRaceService::findCurrent((int)$server->id);
        if (!$config) throw new NotFoundHttpException('Турнир не найден');
        if ($requireActive && $config->tournament->getPublicPhase() !== Tournament::PHASE_ACTIVE) throw new ForbiddenHttpException('Турнир не активен');
        $steamId = preg_replace('/\D+/', '', (string)($body['steam_id'] ?? ''));
        $user = $steamId ? User::find()->where(['steam_id' => $steamId])->one() : null;
        if (!$user) throw new NotFoundHttpException('Игрок не найден');
        if (!CashRaceService::canPlayerParticipate($user, $config, !empty($body['server_admin']))) throw new ForbiddenHttpException('Приватный тест недоступен');
        return [$config, $user];
    }

    private function finalizeIfEnded(CashRaceTournament $config): void
    {
        if (!$config->awards_issued_at && $config->tournament && $config->tournament->getPublicPhase() === Tournament::PHASE_PAST) {
            CashRaceService::finalize($config);
        }
    }
}
