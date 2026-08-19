<?php

namespace api\controllers\v1;

use common\helpers\StatsCacheHelper;
use common\models\battle_pass\BattlePassSeason;
use common\models\box\Category;
use common\models\box\Drop;
use common\models\box\DropBlocked;
use common\models\box\DropDrop;
use common\models\box\DropFavorite;
use common\models\clan\Clan;
use common\models\clan\ClanMember;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\support\Support;
use common\models\support\SupportMessage;
use common\models\support\SupportRead;
use common\models\user\User;
use common\models\user\UserBalance;
use common\models\user\UserDrop;
use common\models\user\UserProfile;
use common\models\invoice\Invoice;
use common\models\wipe_calendar\WipeCalendarEvent;
use common\components\queue\support\BeforeMessageJob;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Aggregate used by the in-game ProstojMenu plugin.
 * Requests are resolved by the configured server tag or IP/port; a server
 * secret is intentionally not required for this API.
 */
class RustMenuController extends BaseApiController
{
    public function actionSnapshot()
    {
        [$server, $steamId] = $this->authenticatePlayerRequest();

        $cacheKey = 'api_rust_menu_snapshot_' . md5($server->id . '|' . $steamId . '|v6');
        $cached = Yii::$app->cache->get($cacheKey);
        if (is_array($cached)) {
            // A cache hit only needs these two columns to preserve immediate
            // website server selection. Avoid hydrating the wide user row on
            // the hottest request path.
            $user = User::find()
                ->select(['id', 'server_id'])
                ->andWhere(['steam_id' => $steamId])
                ->one();
            $this->syncUserServer($user, $server);

            return $this->successResponse($cached, ['cached' => true]);
        }

        // Opening the in-game menu also selects the server on the website.
        // Cache misses need the full model for the player payload.
        $user = User::find()
            ->andWhere(['steam_id' => $steamId])
            ->one();
        $this->syncUserServer($user, $server);

        $wipe = $server->currentWipe();
        $stats = $this->buildPlayerStats($server, $steamId, $wipe);
        $calendar = $this->safeSection('calendar', function () use ($server) {
            return $this->buildCalendar($server);
        }, []);
        $leaderboard = $this->safeSection('leaderboard', function () use ($server, $wipe) {
            return $this->buildLeaderboard($server, $wipe);
        }, ['categories' => []]);
        $clans = $this->safeSection('clans', function () use ($server, $user) {
            return $this->buildClans($server, $user);
        }, ['my_clan' => null, 'items' => []]);
        $support = $this->safeSection('support', function () use ($user) {
            return $this->buildSupportSummary($user);
        }, ['unread_count' => 0, 'open_count' => 0]);
        $payload = [
            'generated_at' => time(),
            'server' => [
                'id' => (int) $server->id,
                'tag' => (string) $server->tag,
                'name' => (string) ($server->monitoring_name ?: $server->name),
                'players' => (int) $server->players,
                'max_players' => (int) $server->max,
                'current_wipe' => $wipe,
                'next_wipe_at' => $this->formatDateTime($server->getFactNextWipe() ?: $server->next_wipe),
                'timezone' => 'Europe/Moscow',
            ],
            'player' => $this->buildPlayer($user, $steamId, $stats),
            'calendar' => $calendar,
            'leaderboard' => $leaderboard,
            'clans' => $clans,
            'support' => $support,
        ];

        Yii::$app->cache->set($cacheKey, $payload, 30);

        return $this->successResponse($payload, ['cached' => false]);
    }

    /**
     * Keep the website's selected server aligned with the requested Rust
     * server. The write only happens on an actual mismatch.
     */
    private function syncUserServer(?User $user, Servers $server): void
    {
        if (!$user || (int) $user->server_id === (int) $server->id) {
            return;
        }

        $user->server_id = (int) $server->id;
        if (!$user->save(false, ['server_id'])) {
            Yii::warning(
                'RustMenu could not sync website server for user ' . (int) $user->id,
                'rust-menu'
            );
        }
    }

    /**
     * Battle Pass is intentionally loaded outside the common snapshot: the
     * task list is only needed while its tab is open and player progress must
     * not be hidden behind the snapshot cache.
     */
    public function actionBattlepass()
    {
        Yii::$app->response->headers->set('Cache-Control', 'private, no-store');
        [$server, $steamId] = $this->authenticatePlayerRequest();
        $season = BattlePassSeason::findActive();
        if (!$season) {
            return $this->errorResponse(
                'BATTLE_PASS_NOT_FOUND',
                'Активный сезон Battle Pass не найден',
                [],
                404
            );
        }

        $cacheKey = $this->battlePassCacheKey($server, $steamId);
        $cached = Yii::$app->cache->get($cacheKey);
        if (is_array($cached)) {
            return $this->successResponse($cached, ['cached' => true]);
        }

        $user = User::find()->andWhere(['steam_id' => $steamId])->one();
        $battlePass = new BattlePassController('battle-pass', $this->module);
        $payload = $battlePass->buildRustMenuPayload($season, $user);
        Yii::$app->cache->set($cacheKey, $payload, 15);

        return $this->successResponse($payload, ['cached' => false]);
    }

    /**
     * Runs the website's existing atomic completion/reward flow for the player
     * currently connected to the trusted Rust server.
     */
    public function actionBattlepassCheck($id)
    {
        Yii::$app->response->headers->set('Cache-Control', 'private, no-store');
        [$server, $steamId] = $this->authenticatePlayerRequest();
        $user = User::find()->andWhere(['steam_id' => $steamId])->one();
        if (!$user) {
            return $this->errorResponse(
                'ACCOUNT_REQUIRED',
                'Войдите на prostoj.store через Steam, чтобы выполнять задания.',
                [],
                403
            );
        }

        $previousIdentity = Yii::$app->user->identity;
        Yii::$app->user->setIdentity($user);
        try {
            $battlePass = new BattlePassController('battle-pass', $this->module);
            return $battlePass->actionCheck((int) $id);
        } finally {
            Yii::$app->cache->delete($this->battlePassCacheKey($server, $steamId));
            Yii::$app->user->setIdentity($previousIdentity);
        }
    }

    /**
     * Skin giveaway checklist for the in-game menu. Visibility additionally
     * depends on the current server's skindrops flag and Rust admin status.
     */
    public function actionSkindrops()
    {
        Yii::$app->response->headers->set('Cache-Control', 'private, no-store');
        [$server, $steamId] = $this->authenticatePlayerRequest();
        $isServerAdmin = filter_var(
            Yii::$app->request->get('server_admin', false),
            FILTER_VALIDATE_BOOLEAN
        );

        return $this->successResponse($this->buildSkinDropsPayload($server, $steamId, $isServerAdmin));
    }

    /** Save only the Steam trade URL; all other profile fields remain untouched. */
    public function actionSkindropsTradeLink()
    {
        Yii::$app->response->headers->set('Cache-Control', 'private, no-store');
        [$server, $steamId] = $this->authenticatePlayerRequest();
        $isServerAdmin = filter_var(
            Yii::$app->request->get('server_admin', false),
            FILTER_VALIDATE_BOOLEAN
        );
        if (!$isServerAdmin) {
            return $this->errorResponse('ADMIN_REQUIRED', 'Раздел доступен только администраторам сервера.', [], 403);
        }
        if (!Yii::$app->settings->get('section_skindrops') || !(bool) $server->skindrops) {
            return $this->errorResponse('SKINDROPS_DISABLED', 'Раздача скинов выключена на этом сервере.', [], 404);
        }

        $user = User::find()->andWhere(['steam_id' => $steamId])->one();
        if (!$user) {
            return $this->errorResponse('ACCOUNT_REQUIRED', 'Сначала войдите на сайт через Steam.', [], 403);
        }

        $body = Yii::$app->request->getBodyParams();
        if (empty($body)) {
            $body = json_decode((string) Yii::$app->request->getRawBody(), true) ?: [];
        }
        $tradeLink = trim((string) ($body['trade_link'] ?? ''));
        $error = $this->validateSteamTradeLink($tradeLink);
        if ($error !== null) {
            return $this->errorResponse('INVALID_TRADE_LINK', $error, ['trade_link' => $error], 422);
        }

        $profile = $user->userProfile ?: new UserProfile(['user_id' => (int) $user->id]);
        if ($profile->isNewRecord && empty($profile->name)) {
            $profile->name = (string) $user->username;
        }
        $profile->trade_link = $tradeLink;
        if ($profile->hasAttribute('skindrops')) {
            $profile->skindrops = 1;
        }
        if ($profile->hasAttribute('skindrops_error')) {
            $profile->skindrops_error = null;
        }
        if (!$profile->save(false)) {
            Yii::error('RustMenu skindrops trade link save failed for user ' . (int) $user->id, 'rust-menu');
            return $this->errorResponse('SAVE_FAILED', 'Не удалось сохранить трейд-ссылку.', [], 500);
        }
        $user->populateRelation('userProfile', $profile);

        return $this->successResponse($this->buildSkinDropsPayload($server, $steamId, true));
    }

    /** Admin-only notification preferences for the in-game menu. */
    public function actionNotifications()
    {
        Yii::$app->response->headers->set('Cache-Control', 'private, no-store');
        [$server, $steamId] = $this->authenticatePlayerRequest();
        $isServerAdmin = filter_var(
            Yii::$app->request->get('server_admin', false),
            FILTER_VALIDATE_BOOLEAN
        );

        return $this->successResponse($this->buildNotificationsPayload($server, $steamId, $isServerAdmin));
    }

    /** Save only raid/ban delivery flags; profile and channel bindings are untouched. */
    public function actionNotificationsSettings()
    {
        Yii::$app->response->headers->set('Cache-Control', 'private, no-store');
        [$server, $steamId] = $this->authenticatePlayerRequest();
        $isServerAdmin = filter_var(
            Yii::$app->request->get('server_admin', false),
            FILTER_VALIDATE_BOOLEAN
        );
        if (!$isServerAdmin) {
            return $this->errorResponse('ADMIN_REQUIRED', 'Раздел доступен только администраторам сервера.', [], 403);
        }

        $user = User::find()->andWhere(['steam_id' => $steamId])->one();
        if (!$user) {
            return $this->errorResponse('ACCOUNT_REQUIRED', 'Сначала войдите на сайт через Steam.', [], 403);
        }

        $body = Yii::$app->request->getBodyParams();
        if (empty($body)) {
            $body = json_decode((string) Yii::$app->request->getRawBody(), true) ?: [];
        }
        if (!array_key_exists('raid_notify', $body) || !array_key_exists('ban_notify', $body)) {
            return $this->errorResponse(
                'INVALID_SETTINGS',
                'Передайте настройки уведомлений о рейдах и банах.',
                [],
                422
            );
        }

        $raidNotify = filter_var($body['raid_notify'], FILTER_VALIDATE_BOOLEAN);
        $banNotify = filter_var($body['ban_notify'], FILTER_VALIDATE_BOOLEAN);
        $telegramConnected = !empty($user->telegram_chat_id) && empty($user->is_telegram_blocked);
        $vkConnected = !empty($user->vk_id);
        if (($raidNotify || $banNotify) && !$telegramConnected && !$vkConnected) {
            return $this->errorResponse(
                'CHANNEL_REQUIRED',
                'Сначала привяжите Telegram-бота или бота ВКонтакте в профиле на сайте.',
                [],
                422
            );
        }

        $user->raid_notify = $raidNotify ? 1 : 0;
        $user->ban_notify = $banNotify ? 1 : 0;
        if (!$user->save(false, ['raid_notify', 'ban_notify'])) {
            Yii::error('RustMenu notification settings save failed for user ' . (int) $user->id, 'rust-menu');
            return $this->errorResponse('SAVE_FAILED', 'Не удалось сохранить настройки уведомлений.', [], 500);
        }

        return $this->successResponse($this->buildNotificationsPayload($server, $steamId, true));
    }

    /** Cached public catalogue plus the current admin's balance and favorites. */
    public function actionShop()
    {
        Yii::$app->response->headers->set('Cache-Control', 'private, no-store');
        [$server, $steamId] = $this->authenticatePlayerRequest();
        $this->requireRustMenuAdmin();
        $user = $this->findRustMenuUser($steamId);
        $categoryId = max(0, (int) Yii::$app->request->get('category_id', 0));
        $page = max(1, (int) Yii::$app->request->get('page', 1));
        $pageSize = min(20, max(4, (int) Yii::$app->request->get('page_size', 8)));

        return $this->successResponse($this->buildShopPayload($server, $user, $categoryId, $page, $pageSize));
    }

    /** Toggle a product in the website's existing favorites table. */
    public function actionShopFavorite()
    {
        Yii::$app->response->headers->set('Cache-Control', 'private, no-store');
        [$server, $steamId] = $this->authenticatePlayerRequest();
        $this->requireRustMenuAdmin();
        $user = $this->findRustMenuUser($steamId);
        $body = $this->jsonBody();
        $dropId = (int) ($body['drop_id'] ?? 0);
        $drop = Drop::findOne($dropId);
        if (!$drop || (int) $drop->market_status !== Drop::MARKET_STATUS_ACTIVE) {
            return $this->errorResponse('PRODUCT_NOT_FOUND', 'Товар не найден или больше недоступен.', [], 404);
        }

        $favorite = filter_var($body['favorite'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $saved = $favorite
            ? DropFavorite::addToFavorite((int) $user->id, $dropId)
            : DropFavorite::removeFromFavorite((int) $user->id, $dropId);
        if (!$saved) {
            return $this->errorResponse('FAVORITE_SAVE_FAILED', 'Не удалось изменить избранное.', [], 500);
        }

        return $this->successResponse(['drop_id' => $dropId, 'favorite' => $favorite]);
    }

    /** Instant purchase using the same invoice and basket records as the website store. */
    public function actionShopBuy()
    {
        Yii::$app->response->headers->set('Cache-Control', 'private, no-store');
        [$server, $steamId] = $this->authenticatePlayerRequest();
        $this->requireRustMenuAdmin();
        $user = $this->findRustMenuUser($steamId);
        $dropId = (int) ($this->jsonBody()['drop_id'] ?? 0);
        $drop = Drop::findOne($dropId);
        if (!$drop
            || (int) $drop->status !== Drop::STATUS_ACTIVE
            || (int) $drop->market_status !== Drop::MARKET_STATUS_ACTIVE
            || (int) $drop->drop_type === Drop::TYPE_SELECT
        ) {
            return $this->errorResponse('PRODUCT_NOT_FOUND', 'Товар не найден или недоступен для покупки.', [], 404);
        }
        $block = $this->shopWipeBlock($drop, $server);
        if ($block['blocked']) {
            return $this->errorResponse('PRODUCT_BLOCKED', 'Товар временно закрыт вайп-блоком.', $block, 409);
        }

        $previousIdentity = Yii::$app->user->identity;
        Yii::$app->user->setIdentity($user);
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $price = (int) $drop->getRealPrice(true);
            $balance = $user->getPersonalBalance();
            if ($price > (int) $balance->balanceCeil) {
                $transaction->rollBack();
                return $this->errorResponse(
                    'INSUFFICIENT_BALANCE',
                    'Недостаточно средств. Пополните баланс по QR-коду.',
                    ['price' => $price, 'balance' => (int) $balance->balanceCeil],
                    409
                );
            }

            Invoice::createRecord(
                (int) $user->id,
                $price,
                Invoice::TYPE_PAYMENT_MARKET_DROP,
                null,
                null,
                (int) $drop->id,
                Yii::t('common', 'Мгновенная покупка предмета "{PARAMS_PREDNAME}"', [
                    'PARAMS_PREDNAME' => Yii::t('database', (string) $drop->name),
                ])
            );

            $userDropIds = [];
            if ((int) $drop->drop_type === Drop::TYPE_SET) {
                $relations = DropDrop::find()->where(['parent_drop_id' => (int) $drop->id])->with('drop')->all();
                foreach ($relations as $relation) {
                    if (!$relation->drop) {
                        continue;
                    }
                    $userDrop = UserDrop::createRecord(
                        (int) $user->id,
                        (int) $relation->drop_id,
                        null,
                        null,
                        UserDrop::STATUS_ACTIVE,
                        false,
                        max(1, (int) ($relation->count ?? 1)),
                        null,
                        (int) $drop->id
                    );
                    $userDropIds[] = (int) $userDrop->id;
                }
            } else {
                $userDrop = UserDrop::createRecord(
                    (int) $user->id,
                    (int) $drop->id,
                    null,
                    null,
                    UserDrop::STATUS_ACTIVE,
                    false,
                    max(1, (int) ($drop->count ?? 1))
                );
                $userDropIds[] = (int) $userDrop->id;
            }

            $balance->recalculateBalance();
            $newBalance = (int) $balance->balanceCeil;
            $transaction->commit();
            Yii::$app->drop->clearCountBuy((int) $user->id);
            $this->invalidateRustMenuSnapshot($server, $steamId);

            return $this->successResponse([
                'purchased' => true,
                'drop_id' => (int) $drop->id,
                'price' => $price,
                'new_balance' => $newBalance,
                'basket_ids' => $userDropIds,
                'message' => 'Товар куплен и добавлен в корзину.',
            ]);
        } catch (\Throwable $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            Yii::error('RustMenu shop purchase failed: ' . $e->getMessage(), 'rust-menu');
            return $this->errorResponse('PURCHASE_FAILED', 'Не удалось завершить покупку.', [], 500);
        } finally {
            Yii::$app->user->setIdentity($previousIdentity);
        }
    }

    /** Lightweight balance polling while the QR payment is open. */
    public function actionShopBalance()
    {
        Yii::$app->response->headers->set('Cache-Control', 'private, no-store');
        [$server, $steamId] = $this->authenticatePlayerRequest();
        $this->requireRustMenuAdmin();
        $user = $this->findRustMenuUser($steamId);
        $balance = $user->getPersonalBalance();
        $balance->recalculateBalance();

        return $this->successResponse([
            'balance' => (int) $balance->balanceCeil,
            'currency' => UserBalance::getCurrency(),
        ]);
    }

    /** Create a short-lived signed phone link and a first-party QR image URL. */
    public function actionShopTopup()
    {
        Yii::$app->response->headers->set('Cache-Control', 'private, no-store');
        [$server, $steamId] = $this->authenticatePlayerRequest();
        $this->requireRustMenuAdmin();
        $user = $this->findRustMenuUser($steamId);
        $amount = (int) ($this->jsonBody()['amount'] ?? 0);
        if ($amount < 50 || $amount > 50000) {
            return $this->errorResponse('INVALID_AMOUNT', 'Введите сумму от 50 до 50 000 ₽.', [], 422);
        }

        $token = $this->createShopTopupToken($steamId, $amount);
        $apiBase = rtrim((string) Yii::$app->request->hostInfo, '/');
        $balance = $user->getPersonalBalance();
        return $this->successResponse([
            'amount' => $amount,
            'initial_balance' => (int) $balance->balanceCeil,
            'expires_at' => time() + 15 * 60,
            'qr_url' => $apiBase . '/v1/rust-menu/shop/topup/qr?token=' . rawurlencode($token),
        ]);
    }

    /** PNG is generated on our API; the Rust client downloads and caches it in FileStorage. */
    public function actionShopTopupQr()
    {
        $payload = $this->validateShopTopupToken((string) Yii::$app->request->get('token', ''));
        $siteBase = $this->shopSiteBaseUrl();
        $url = $siteBase . '/payment/ingame?token=' . rawurlencode((string) Yii::$app->request->get('token', ''));
        $qr = (new \Da\QrCode\QrCode($url))->setSize(360)->setMargin(14);

        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'image/png');
        Yii::$app->response->headers->set('Cache-Control', 'private, max-age=900');
        Yii::$app->response->headers->set('X-Topup-Expires', (string) $payload['expires']);
        return $qr->writeString();
    }

    /** Public token verification for the phone landing page. */
    public function actionShopTopupResolve()
    {
        Yii::$app->response->headers->set('Cache-Control', 'private, no-store');
        $payload = $this->validateShopTopupToken((string) Yii::$app->request->get('token', ''));
        return $this->successResponse([
            'steam_id' => (string) $payload['steam_id'],
            'amount' => (int) $payload['amount'],
            'expires_at' => (int) $payload['expires'],
        ]);
    }

    /**
     * Returns the player's current support conversation and compact ticket history.
     * Uses the same Support models as the website, so staff replies are visible in game.
     */
    public function actionSupport()
    {
        Yii::$app->response->headers->set('Cache-Control', 'private, no-store');
        [$server, $steamId] = $this->authenticatePlayerRequest();
        $user = User::find()->andWhere(['steam_id' => $steamId])->one();
        $selectedNumber = (int) Yii::$app->request->get('ticket_number', 0);
        $knownRevision = trim((string) Yii::$app->request->get('known_revision', ''));
        if (!preg_match('/^[a-f0-9]{40}$/', $knownRevision)) {
            $knownRevision = '';
        }

        return $this->successResponse($this->buildSupportPayload($server, $user, $selectedNumber, $knownRevision));
    }

    /**
     * Creates a support ticket or appends a message to an existing open ticket.
     */
    public function actionSupportSend()
    {
        Yii::$app->response->headers->set('Cache-Control', 'private, no-store');
        [$server, $steamId] = $this->authenticatePlayerRequest();
        $user = User::find()->andWhere(['steam_id' => $steamId])->one();
        if (!$user) {
            return $this->errorResponse('ACCOUNT_REQUIRED', 'Войдите на сайт через Steam, чтобы написать в поддержку.', [], 403);
        }
        if ($user->isSupportWritingBlocked()) {
            return $this->errorResponse('SUPPORT_BLOCKED', 'Возможность писать в поддержку временно недоступна.', [], 403);
        }

        $message = $this->normalizeSupportText((string) Yii::$app->request->getBodyParam('message', ''));
        if ($message === '') {
            return $this->errorResponse('MESSAGE_REQUIRED', 'Введите сообщение.', [], 400);
        }
        if (mb_strlen($message, 'UTF-8') > 500) {
            return $this->errorResponse('MESSAGE_TOO_LONG', 'Сообщение не должно превышать 500 символов.', [], 400);
        }
        $rateKey = 'rust_menu_support_send_' . (int) $user->id;
        if (!Yii::$app->cache->add($rateKey, 1, 3)) {
            return $this->errorResponse('TOO_MANY_REQUESTS', 'Подождите несколько секунд перед следующим сообщением.', [], 429);
        }

        $ticketNumber = (int) Yii::$app->request->getBodyParam('ticket_number', 0);
        $forceNew = filter_var(Yii::$app->request->getBodyParam('new_ticket', false), FILTER_VALIDATE_BOOLEAN);
        $ticket = null;
        if ($ticketNumber > 0) {
            $ticket = Support::findByNumber($ticketNumber);
            if (!$ticket || (int) $ticket->user_id !== (int) $user->id) {
                throw new NotFoundHttpException('Обращение не найдено.');
            }
        } elseif (!$forceNew) {
            $ticket = Support::find()
                ->andWhere(['user_id' => (int) $user->id, 'status' => Support::STATUS_OPEN])
                ->orderBy(['updated_at' => SORT_DESC, 'id' => SORT_DESC])
                ->one();
        }

        if ($ticket && (int) $ticket->status !== Support::STATUS_OPEN) {
            return $this->errorResponse('TICKET_CLOSED', 'Обращение закрыто. Создайте новое.', [], 400);
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $isNew = $ticket === null;
            if ($isNew) {
                $ticket = new Support();
                $ticket->user_id = (int) $user->id;
                $ticket->server_tag = (string) $server->tag;
                $ticket->status = Support::STATUS_OPEN;
                $ticket->created_at = date('Y-m-d H:i:s');
                $ticket->updated_at = $ticket->created_at;
                if (!$ticket->save()) {
                    $transaction->rollBack();
                    return $this->validationErrorResponse($ticket);
                }

                foreach (['{USER_INFO}', '{ALERT_REPORT}'] as $systemText) {
                    $systemMessage = new SupportMessage();
                    $systemMessage->support_id = (int) $ticket->id;
                    $systemMessage->user_id = null;
                    $systemMessage->message = $systemText;
                    $systemMessage->created_at = date('Y-m-d H:i:s');
                    $systemMessage->save(false);
                }
            }

            $supportMessage = new SupportMessage();
            $supportMessage->support_id = (int) $ticket->id;
            $supportMessage->user_id = (int) $user->id;
            $supportMessage->message = $message;
            $supportMessage->created_at = date('Y-m-d H:i:s');
            if (!$supportMessage->save()) {
                $transaction->rollBack();
                return $this->validationErrorResponse($supportMessage);
            }

            $ticket->updated_at = $supportMessage->created_at;
            $ticket->save(false, ['updated_at']);
            SupportRead::readedAll((int) $ticket->id, (int) $user->id);
            SupportRead::createRecord((int) $ticket->user_id, (int) $user->id, (int) $supportMessage->id, (int) $ticket->id);
            $transaction->commit();

            $this->notifySupportMessage($ticket, $supportMessage, $user, $isNew);
            Yii::$app->response->statusCode = 201;

            return $this->successResponse($this->buildSupportPayload($server, $user, $ticket->getNumber()));
        } catch (\Throwable $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            Yii::error('RustMenu support send failed: ' . $e->getMessage(), 'rust-menu');
            return $this->errorResponse('SUPPORT_SEND_FAILED', 'Не удалось отправить сообщение. Попробуйте ещё раз.', [], 500);
        }
    }

    /**
     * Closes an open ticket owned by the player authenticated through the
     * trusted Rust server. Website/JWT support endpoints are not affected.
     */
    public function actionSupportClose($number)
    {
        Yii::$app->response->headers->set('Cache-Control', 'private, no-store');
        [$server, $steamId] = $this->authenticatePlayerRequest();
        $user = User::find()->andWhere(['steam_id' => $steamId])->one();
        if (!$user) {
            return $this->errorResponse('ACCOUNT_REQUIRED', 'Войдите на сайт через Steam, чтобы управлять обращением.', [], 403);
        }

        $ticket = $this->findOwnedSupportTicketByNumber((int) $user->id, (int) $number);
        if (!$ticket) {
            throw new NotFoundHttpException('Обращение не найдено.');
        }
        if ((int) $ticket->status !== Support::STATUS_OPEN) {
            return $this->errorResponse('ALREADY_CLOSED', 'Обращение уже закрыто.', [], 400);
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $closedAt = date('Y-m-d H:i:s');
            $systemMessage = new SupportMessage();
            $systemMessage->support_id = (int) $ticket->id;
            $systemMessage->user_id = null;
            $systemMessage->message = 'Обращение закрыто пользователем ' . (string) $user->username;
            $systemMessage->created_at = $closedAt;
            if (!$systemMessage->save()) {
                $transaction->rollBack();
                return $this->validationErrorResponse($systemMessage);
            }

            $ticket->status = Support::STATUS_CLOSED;
            $ticket->updated_at = $closedAt;
            $ticket->save(false, ['status', 'updated_at']);
            SupportRead::readedAll((int) $ticket->id, (int) $user->id);
            $transaction->commit();

            try {
                \console\controllers\NotificationServer::broadcastTicketStatus($ticket->getNumber(), 'closed');
            } catch (\Throwable $e) {
                Yii::warning('RustMenu support close websocket unavailable: ' . $e->getMessage(), 'rust-menu');
            }

            return $this->successResponse($this->buildSupportPayload($server, $user, $ticket->getNumber()));
        } catch (\Throwable $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            Yii::error('RustMenu support close failed: ' . $e->getMessage(), 'rust-menu');
            return $this->errorResponse('SUPPORT_CLOSE_FAILED', 'Не удалось закрыть обращение. Попробуйте ещё раз.', [], 500);
        }
    }

    private function buildSkinDropsPayload(Servers $server, string $steamId, bool $isServerAdmin): array
    {
        $available = (bool) Yii::$app->settings->get('section_skindrops') && (bool) $server->skindrops;
        $prefix = trim((string) (Yii::$app->settings->get('skindrops_prefix') ?? ''));
        $user = User::find()->andWhere(['steam_id' => $steamId])->one();
        $profile = $user ? $user->userProfile : null;
        $tradeLink = $profile ? trim((string) ($profile->trade_link ?? '')) : '';
        $usernameCompleted = false;

        if ($user && $prefix !== '') {
            $prefixNormalized = mb_strtolower($prefix, 'UTF-8');
            $names = [(string) $user->username];
            if ($profile) {
                $names[] = (string) $profile->name;
            }
            foreach ($names as $name) {
                if ($name !== '' && mb_strpos(mb_strtolower($name, 'UTF-8'), $prefixNormalized) !== false) {
                    $usernameCompleted = true;
                    break;
                }
            }
        }

        $registered = $user !== null;
        $tradeLinkCompleted = $tradeLink !== '';
        return [
            'available' => $available,
            'eligible' => $available && $isServerAdmin,
            'prefix' => $prefix,
            'server' => [
                'id' => (int) $server->id,
                'tag' => (string) $server->tag,
                'name' => (string) ($server->monitoring_name ?: $server->name),
                'skindrops_enabled' => (bool) $server->skindrops,
            ],
            'user' => [
                'registered' => $registered,
                'username' => $user ? (string) $user->username : '',
                'steam_id' => $steamId,
                'username_completed' => $usernameCompleted,
                'trade_link' => $tradeLink !== '' ? $tradeLink : null,
                'trade_link_completed' => $tradeLinkCompleted,
                'all_completed' => $registered && $usernameCompleted && $tradeLinkCompleted,
            ],
        ];
    }

    private function buildNotificationsPayload(Servers $server, string $steamId, bool $isServerAdmin): array
    {
        $user = User::find()->andWhere(['steam_id' => $steamId])->one();
        $telegramConnected = $user !== null
            && !empty($user->telegram_chat_id)
            && empty($user->is_telegram_blocked);
        $vkConnected = $user !== null && !empty($user->vk_id);
        $deliveryReady = $telegramConnected || $vkConnected;

        $telegramUsername = trim((string) (
            Yii::$app->settings->get('telegram_personal_bot_username')
            ?: Yii::$app->settings->get('tgbot_login')
            ?: ''
        ));
        $telegramUsername = ltrim($telegramUsername, '@');
        $vkLink = trim((string) (Yii::$app->settings->get('social_vk') ?? ''));

        return [
            'available' => true,
            'eligible' => $isServerAdmin,
            'registered' => $user !== null,
            'can_manage' => $isServerAdmin && $user !== null && $deliveryReady,
            'delivery_ready' => $deliveryReady,
            'server' => [
                'id' => (int) $server->id,
                'tag' => (string) $server->tag,
                'name' => (string) ($server->monitoring_name ?: $server->name),
            ],
            'channels' => [
                'telegram' => [
                    'connected' => $telegramConnected,
                    'blocked' => $user !== null && !empty($user->telegram_chat_id) && !empty($user->is_telegram_blocked),
                    'bot_username' => $telegramUsername !== '' ? $telegramUsername : null,
                ],
                'vk' => [
                    'connected' => $vkConnected,
                    'community_url' => $vkLink !== '' ? $vkLink : null,
                ],
            ],
            'settings' => [
                'raid_notify' => $user !== null && !empty($user->raid_notify),
                'ban_notify' => $user !== null && !empty($user->ban_notify),
            ],
        ];
    }

    private function buildShopPayload(Servers $server, User $user, int $categoryId, int $page, int $pageSize): array
    {
        $catalog = $this->shopCatalogIndex();
        $rows = $categoryId > 0
            ? array_values(array_filter($catalog, static function (array $row) use ($categoryId): bool {
                return (int) $row['category_id'] === $categoryId;
            }))
            : $catalog;
        $total = count($rows);
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $slice = array_slice($rows, ($page - 1) * $pageSize, $pageSize);
        $dropIds = array_map('intval', array_column($slice, 'id'));
        $favoriteIds = empty($dropIds)
            ? []
            : array_map('intval', DropFavorite::find()
                ->select('drop_id')
                ->where(['user_id' => (int) $user->id, 'drop_id' => $dropIds])
                ->column());
        $favoriteMap = array_fill_keys($favoriteIds, true);
        $dropMap = Drop::getDropListAll();
        $images = Drop::productsImages();
        $products = [];

        $previousIdentity = Yii::$app->user->identity;
        Yii::$app->user->setIdentity($user);
        try {
            foreach ($slice as $row) {
                $drop = $dropMap[(int) $row['id']] ?? null;
                if (!$drop) {
                    continue;
                }
                $block = $this->shopWipeBlock($drop, $server);
                $image = (string) ($images[(int) $drop->id]['150px'] ?? '');
                if ($image === '' && $drop->imageOrig) {
                    $image = (string) $drop->imageOrig->getImagePubUrl();
                }
                $products[] = [
                    'id' => (int) $drop->id,
                    'name' => Yii::t('database', (string) $drop->name),
                    'price' => (int) $drop->getRealPrice(true),
                    'image' => $image,
                    'rust_id' => (int) ($drop->rust_id ?? 0),
                    'count' => max(1, (int) ($drop->count ?? 1)),
                    'category_id' => (int) ($drop->category_id ?? 0),
                    'favorite' => isset($favoriteMap[(int) $drop->id]),
                    'popular' => (int) $row['popularity'] > 0,
                    'popularity' => (int) $row['popularity'],
                    'blocked' => (bool) $block['blocked'],
                    'blocked_seconds' => (int) $block['left_time'],
                    'can_buy' => !$block['blocked'],
                ];
            }
        } finally {
            Yii::$app->user->setIdentity($previousIdentity);
        }

        $balance = $user->getPersonalBalance();
        return [
            'available' => true,
            'eligible' => true,
            'balance' => (int) $balance->balanceCeil,
            'currency' => UserBalance::getCurrency(),
            'categories' => $this->shopCategories(),
            'products' => $products,
            'pagination' => [
                'page' => $page,
                'page_size' => $pageSize,
                'pages' => $pages,
                'total' => $total,
            ],
            'sort' => 'popularity',
            'catalog_cache_seconds' => 600,
        ];
    }

    /**
     * Shared catalogue order. Product IDs and global popularity are cached for
     * every player; only the eight visible prices/favorites are personalized.
     */
    private function shopCatalogIndex(): array
    {
        $cacheKey = 'rust_menu_shop_catalog_index_v2';
        $cached = Yii::$app->cache->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $popular = Yii::$app->cache->get('rust_menu_shop_popularity_v1');
        if (!is_array($popular)) {
            $since = date('Y-m-d H:i:s', time() - 90 * 86400);
            $popularRows = Invoice::find()
                ->select(['drop_id', 'COUNT(*) AS purchases'])
                ->where(['type' => Invoice::TYPE_PAYMENT_MARKET_DROP])
                ->andWhere(['IS NOT', 'drop_id', null])
                ->andWhere(['>=', 'created_at', $since])
                ->groupBy('drop_id')
                ->asArray()
                ->all();
            $popular = [];
            foreach ($popularRows as $row) {
                $popular[(int) $row['drop_id']] = (int) $row['purchases'];
            }
            Yii::$app->cache->set('rust_menu_shop_popularity_v1', $popular, 600);
        }

        $rows = [];
        foreach (Drop::getForMarket(false) as $drop) {
            if ((int) $drop->status !== Drop::STATUS_ACTIVE || (int) $drop->drop_type === Drop::TYPE_SELECT) {
                continue;
            }
            $rows[] = [
                'id' => (int) $drop->id,
                'category_id' => (int) ($drop->category_id ?? 0),
                'sort' => (int) ($drop->sort ?? 0),
                'popularity' => (int) ($popular[(int) $drop->id] ?? 0),
            ];
        }
        usort($rows, static function (array $a, array $b): int {
            return ($b['popularity'] <=> $a['popularity'])
                ?: ($a['sort'] <=> $b['sort'])
                ?: ($a['id'] <=> $b['id']);
        });
        Yii::$app->cache->set($cacheKey, $rows, 600);
        return $rows;
    }

    private function shopCategories(): array
    {
        $cacheKey = 'rust_menu_shop_categories_v1';
        $cached = Yii::$app->cache->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }
        $result = [['id' => 0, 'name' => 'Популярное', 'tag' => 'popular']];
        foreach (Category::find()->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])->all() as $category) {
            if (strtolower((string) ($category->tag ?? '')) === 'sets') {
                continue;
            }
            $result[] = [
                'id' => (int) $category->id,
                'name' => Yii::t('database', (string) $category->name),
                'tag' => (string) ($category->tag ?? ''),
            ];
        }
        Yii::$app->cache->set($cacheKey, $result, 600);
        return $result;
    }

    private function shopWipeBlock(Drop $drop, Servers $server): array
    {
        $leftTime = 0;
        if (empty($drop->command) && !empty($drop->rust_id) && (int) $drop->rust_id > 0) {
            $isBlueprint = (int) $drop->rust_id === -1580979675;
            $cacheKey = 'wipe_block_left_time_' . (int) $server->id . '_' . (int) $drop->id . '_'
                . (int) $drop->rust_id . '_' . ($isBlueprint ? '1' : '0');
            $leftTime = (int) Yii::$app->cache->get($cacheKey);
            if ($leftTime <= 0) {
                $blockedAt = DropBlocked::getBlocked((int) $drop->id, (int) $server->id);
                $leftTime = $blockedAt ? max(0, strtotime($blockedAt) - time()) : 0;
            }
        }
        return [
            'blocked' => $leftTime > 0,
            'left_time' => $leftTime,
        ];
    }

    private function requireRustMenuAdmin(): void
    {
        $isServerAdmin = filter_var(
            Yii::$app->request->get('server_admin', false),
            FILTER_VALIDATE_BOOLEAN
        );
        if (!$isServerAdmin) {
            throw new \yii\web\ForbiddenHttpException('Раздел доступен только администраторам сервера.');
        }
    }

    private function findRustMenuUser(string $steamId): User
    {
        $user = User::find()->andWhere(['steam_id' => $steamId])->one();
        if (!$user) {
            throw new \yii\web\ForbiddenHttpException('Сначала войдите на сайт через Steam.');
        }
        return $user;
    }

    private function jsonBody(): array
    {
        $body = Yii::$app->request->getBodyParams();
        if (empty($body)) {
            $body = json_decode((string) Yii::$app->request->getRawBody(), true) ?: [];
        }
        return is_array($body) ? $body : [];
    }

    private function invalidateRustMenuSnapshot(Servers $server, string $steamId): void
    {
        Yii::$app->cache->delete('api_rust_menu_snapshot_' . md5($server->id . '|' . $steamId . '|v6'));
    }

    private function createShopTopupToken(string $steamId, int $amount): string
    {
        $payload = [
            'steam_id' => $steamId,
            'amount' => $amount,
            'expires' => time() + 15 * 60,
            'nonce' => Yii::$app->security->generateRandomString(10),
        ];
        $encoded = rtrim(strtr(base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encoded, $this->shopTopupSigningKey());
        return $encoded . '.' . $signature;
    }

    private function validateShopTopupToken(string $token): array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2
            || !preg_match('/^[A-Za-z0-9_-]+$/', $parts[0])
            || !preg_match('/^[a-f0-9]{64}$/', $parts[1])
            || !hash_equals(hash_hmac('sha256', $parts[0], $this->shopTopupSigningKey()), $parts[1])
        ) {
            throw new BadRequestHttpException('QR-код недействителен.');
        }
        $padded = strtr($parts[0], '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        $payload = json_decode((string) base64_decode($padded, true), true);
        if (!is_array($payload)
            || !preg_match('/^\d{17}$/', (string) ($payload['steam_id'] ?? ''))
            || (int) ($payload['amount'] ?? 0) < 50
            || (int) ($payload['amount'] ?? 0) > 50000
            || (int) ($payload['expires'] ?? 0) < time()
        ) {
            throw new BadRequestHttpException('Срок действия QR-кода истёк.');
        }
        return $payload;
    }

    private function shopTopupSigningKey(): string
    {
        $key = (string) (Yii::$app->params['rustMenuTopupSigningKey'] ?? Yii::$app->request->cookieValidationKey ?? '');
        if ($key === '') {
            throw new \RuntimeException('Rust menu top-up signing key is not configured.');
        }
        return $key;
    }

    private function shopSiteBaseUrl(): string
    {
        $host = strtolower((string) Yii::$app->request->hostName);
        return strpos($host, 'moscow77') !== false ? 'https://moscow77.store' : 'https://prostoj.store';
    }

    private function validateSteamTradeLink(string $tradeLink): ?string
    {
        if ($tradeLink === '' || mb_strlen($tradeLink, 'UTF-8') > 255) {
            return 'Укажите полную Steam Trade URL длиной до 255 символов.';
        }
        $parts = parse_url($tradeLink);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^www\./', '', $host);
        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        parse_str((string) ($parts['query'] ?? ''), $query);
        if (($parts['scheme'] ?? '') !== 'https'
            || $host !== 'steamcommunity.com'
            || $path !== '/tradeoffer/new'
            || empty($query['partner'])
            || empty($query['token'])
        ) {
            return 'Ссылка должна иметь вид https://steamcommunity.com/tradeoffer/new/?partner=...&token=...';
        }
        return null;
    }

    private function authenticatePlayerRequest(): array
    {
        $request = Yii::$app->request;
        $steamId = trim((string) $request->get('steam_id', ''));
        if (!preg_match('/^\d{17}$/', $steamId)) {
            throw new BadRequestHttpException('A valid 17-digit steam_id is required.');
        }

        $server = $this->findServer(
            trim((string) $request->get('server_tag', '')),
            trim((string) $request->get('server_ip', '')),
            (int) $request->get('server_port', 0)
        );
        return [$server, $steamId];
    }

    private function battlePassCacheKey(Servers $server, string $steamId): string
    {
        return 'api_rust_menu_battlepass_v1_' . md5((int) $server->id . '|' . $steamId);
    }

    private function buildSupportPayload(
        Servers $server,
        ?User $user,
        int $selectedNumber = 0,
        string $knownRevision = ''
    ): array {
        if (!$user) {
            return [
                'unchanged' => false,
                'revision' => null,
                'registered' => false,
                'can_write' => false,
                'notice' => 'Войдите на prostoj.store через Steam, чтобы пользоваться поддержкой.',
                'unread_count' => 0,
                'unread_count_capped' => false,
                'tickets' => [],
                'active_ticket' => null,
                'messages' => [],
            ];
        }

        $tickets = $this->loadRecentSupportTickets((int) $user->id, 8);
        $activeTicket = $selectedNumber > 0
            ? $this->findOwnedSupportTicketByNumber((int) $user->id, $selectedNumber)
            : null;
        if ($activeTicket) {
            $alreadyListed = false;
            foreach ($tickets as $candidate) {
                if ((int) $candidate->id === (int) $activeTicket->id) {
                    $alreadyListed = true;
                    break;
                }
            }
            if (!$alreadyListed) {
                array_unshift($tickets, $activeTicket);
                $tickets = array_slice($tickets, 0, 8);
            }
        }
        if (!$activeTicket) {
            foreach ($tickets as $candidate) {
                if ((int) $candidate->status === Support::STATUS_OPEN) {
                    $activeTicket = $candidate;
                    break;
                }
            }
        }
        if (!$activeTicket && !empty($tickets)) {
            $activeTicket = $tickets[0];
        }

        $canWrite = !$user->isSupportWritingBlocked();
        $unreadSupportIds = SupportRead::unreadSupportIdsCapped((int) $user->id, 100);
        $unreadRowsBySupport = array_count_values($unreadSupportIds);
        $unreadTotal = count($unreadSupportIds);
        $revision = $this->buildSupportRevision($tickets, $activeTicket, $unreadTotal, $canWrite);

        // A poll with an unchanged revision avoids loading messages, users and read receipts.
        if ($knownRevision !== '' && hash_equals($revision, $knownRevision)) {
            return [
                'unchanged' => true,
                'revision' => $revision,
                'registered' => true,
                'can_write' => $canWrite,
                'notice' => $canWrite ? null : 'Возможность писать в поддержку временно недоступна.',
                'unread_count' => $unreadTotal,
                'unread_count_capped' => $unreadTotal >= 100,
                'server_tag' => (string) $server->tag,
            ];
        }

        $messages = [];
        if ($activeTicket) {
            // InnoDB stores the PK in the support_id secondary index. Ordering by id
            // therefore reads the newest messages directly instead of filesorting history.
            $models = SupportMessage::find()
                ->where(['support_id' => (int) $activeTicket->id])
                ->andWhere(['not in', 'message', ['{USER_INFO}', '{ALERT_REPORT}']])
                ->with('user')
                ->orderBy(['id' => SORT_DESC])
                ->limit(12)
                ->all();
            $models = array_reverse($models);
            foreach ($models as $model) {
                $messages[] = $this->serializeSupportMessage($model, $user);
            }

            $markedRead = SupportRead::readedAllReturningMessageIds(
                (int) $activeTicket->id,
                (int) $user->id,
                100
            );
            SupportRead::notifyReadReceiptsWebSocketIfNeeded($activeTicket, (int) $user->id, $markedRead);

            $unreadSupportIds = SupportRead::unreadSupportIdsCapped((int) $user->id, 100);
            $unreadRowsBySupport = array_count_values($unreadSupportIds);
            $unreadTotal = count($unreadSupportIds);
        }

        $serializedTickets = [];
        foreach ($tickets as $ticket) {
            $unread = (int) ($unreadRowsBySupport[(int) $ticket->id] ?? 0);
            $serializedTickets[] = $this->serializeSupportTicket($ticket, $unread);
        }
        $revision = $this->buildSupportRevision($tickets, $activeTicket, $unreadTotal, $canWrite);

        return [
            'unchanged' => false,
            'revision' => $revision,
            'registered' => true,
            'can_write' => $canWrite,
            'notice' => $canWrite ? null : 'Возможность писать в поддержку временно недоступна.',
            'unread_count' => $unreadTotal,
            'unread_count_capped' => $unreadTotal >= 100,
            'server_tag' => (string) $server->tag,
            'tickets' => $serializedTickets,
            'active_ticket' => $activeTicket
                ? $this->serializeSupportTicket(
                    $activeTicket,
                    (int) ($unreadRowsBySupport[(int) $activeTicket->id] ?? 0)
                )
                : null,
            'messages' => $messages,
        ];
    }

    /**
     * Two equality-filtered, bounded queries avoid a mixed-direction filesort
     * over every ticket owned by a long-lived account.
     *
     * @return Support[]
     */
    private function loadRecentSupportTickets(int $userId, int $limit): array
    {
        $limit = max(1, min($limit, 20));
        $open = Support::find()
            ->andWhere(['user_id' => $userId, 'status' => Support::STATUS_OPEN])
            ->orderBy(['updated_at' => SORT_DESC, 'id' => SORT_DESC])
            ->limit($limit)
            ->all();
        $remaining = $limit - count($open);
        if ($remaining <= 0) {
            return $open;
        }

        $closed = Support::find()
            ->andWhere(['user_id' => $userId, 'status' => Support::STATUS_CLOSED])
            ->orderBy(['updated_at' => SORT_DESC, 'id' => SORT_DESC])
            ->limit($remaining)
            ->all();

        return array_merge($open, $closed);
    }

    private function findOwnedSupportTicketByNumber(int $userId, int $number): ?Support
    {
        $primaryKey = Support::primaryKeyFromPublicNumber($number);
        if ($primaryKey === null) {
            return null;
        }

        return Support::find()
            ->andWhere(['id' => $primaryKey, 'user_id' => $userId])
            ->one();
    }

    private function buildSupportRevision(
        array $tickets,
        ?Support $activeTicket,
        int $unreadTotal,
        bool $canWrite
    ): string {
        $parts = [
            $activeTicket ? (int) $activeTicket->id : 0,
            $unreadTotal,
            $canWrite ? 1 : 0,
        ];
        foreach ($tickets as $ticket) {
            $parts[] = implode(':', [
                (int) $ticket->id,
                (int) $ticket->status,
                (string) $ticket->updated_at,
            ]);
        }

        return sha1(implode('|', $parts));
    }

    private function serializeSupportTicket(Support $ticket, int $unread): array
    {
        return [
            'number' => $ticket->getNumber(),
            'status' => (int) $ticket->status === Support::STATUS_OPEN ? 'open' : 'closed',
            'server_tag' => (string) ($ticket->server_tag ?? ''),
            'updated_at' => $this->formatDateTime((string) $ticket->updated_at),
            'unread_count' => $unread,
        ];
    }

    private function serializeSupportMessage(SupportMessage $message, User $viewer): array
    {
        $isOwn = $message->user_id !== null && (int) $message->user_id === (int) $viewer->id;
        $isSystem = $message->user_id === null;
        return [
            'id' => (int) $message->id,
            'is_own' => $isOwn,
            'is_staff' => !$isOwn,
            'author' => $isOwn
                ? (string) $viewer->username
                : ($isSystem ? 'Система' : 'Поддержка • ' . (string) ($message->user->username ?? 'Администратор')),
            'steam_id' => $message->user ? (string) $message->user->steam_id : null,
            'avatar' => $message->user ? (string) $message->user->getAvatar() : null,
            'message' => $this->plainSupportText((string) $message->message),
            'created_at' => $this->formatDateTime((string) $message->created_at),
        ];
    }

    private function normalizeSupportText(string $value): string
    {
        $value = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $value);
        return trim($value);
    }

    private function plainSupportText(string $value): string
    {
        $value = preg_replace('/<br\s*\/?>/i', "\n", $value);
        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[ \t]+/', ' ', $value);
        $value = preg_replace('/\n{3,}/', "\n\n", $value);
        return trim((string) $value);
    }

    private function notifySupportMessage(Support $ticket, SupportMessage $message, User $sender, bool $isNew): void
    {
        try {
            if (Yii::$app->has('queueProcess')) {
                Yii::$app->queueProcess->push(new BeforeMessageJob([
                    'chatId' => (int) $ticket->id,
                    'userId' => (int) $sender->id,
                    'message' => (string) $message->message,
                    'username' => (string) $sender->username,
                    'chatNumber' => $ticket->getNumber(),
                    'messageId' => (int) $message->id,
                ]));
            }
        } catch (\Throwable $e) {
            Yii::warning('RustMenu support queue unavailable: ' . $e->getMessage(), 'rust-menu');
        }

        try {
            if ($isNew) {
                \console\controllers\NotificationServer::broadcastNewTicket($ticket->getNumber(), (int) $sender->id);
            }
            \console\controllers\NotificationServer::broadcastNewSupportMessage(
                $ticket->getNumber(),
                (int) $message->id,
                (int) $sender->id,
                (int) $ticket->user_id
            );
        } catch (\Throwable $e) {
            Yii::warning('RustMenu support websocket unavailable: ' . $e->getMessage(), 'rust-menu');
        }
    }

    private function safeSection(string $name, callable $builder, array $fallback): array
    {
        try {
            $result = $builder();
            return is_array($result) ? $result : $fallback;
        } catch (\Throwable $e) {
            Yii::warning('RustMenu ' . $name . ' unavailable: ' . $e->getMessage(), 'rust-menu');
            return $fallback;
        }
    }

    private function formatDateTime(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            $timezone = new \DateTimeZone('Europe/Moscow');
            return (new \DateTimeImmutable($value, $timezone))->format(DATE_ATOM);
        } catch (\Throwable $e) {
            Yii::warning('RustMenu invalid date: ' . $value, 'rust-menu');
            return null;
        }
    }

    private function findServer(string $tag, string $ip, int $port): Servers
    {
        $query = Servers::find();
        if ($tag !== '') {
            $query->andWhere(['tag' => $tag]);
        } elseif ($ip !== '' && $port > 0) {
            $query->andWhere(['ip' => $ip, 'port' => $port]);
        } else {
            throw new BadRequestHttpException('server_tag or server_ip/server_port is required.');
        }

        $server = $query->cache(60)->one();
        if (!$server) {
            throw new NotFoundHttpException('Server not found.');
        }

        return $server;
    }

    private function buildPlayer(?User $user, string $steamId, array $stats): array
    {
        $balance = 0;
        if ($user) {
            $model = UserBalance::getBalance((int) $user->id, UserBalance::TYPE_PERSONAL);
            $balance = $model ? (int) $model->balanceCeil : 0;
        }

        return [
            'registered' => $user !== null,
            'steam_id' => $steamId,
            'username' => $user ? (string) $user->username : 'Rust player',
            'avatar' => $user ? (string) $user->getAvatar() : null,
            'balance' => $balance,
            'currency' => UserBalance::getCurrency(),
            'stats' => $stats,
        ];
    }

    private function buildPlayerStats(Servers $server, string $steamId, string $wipe): array
    {
        $cacheKey = 'api_rust_menu_player_stats_v1_'
            . md5((int) $server->id . '|' . $steamId . '|' . $wipe);
        $cached = Yii::$app->cache->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $rows = Statistics::getPlayerStats($server, $steamId, $wipe);
        } catch (\Throwable $e) {
            Yii::warning('RustMenu stats unavailable: ' . $e->getMessage(), 'rust-menu');
            $rows = [];
        }

        $kills = (int) Statistics::getParam($rows, 'kills');
        $deaths = (int) Statistics::getParam($rows, 'deaths');
        $images = Statistics::productsImages();
        $lootDefinitions = [
            ['key' => 'crates', 'name' => 'Крейты', 'image_key' => 'codelockedhackablecrate_oilrig', 'stat_keys' => ['codelockedhackablecrate_oilrig', 'codelockedhackablecrate']],
            ['key' => 'crate_elite', 'name' => 'Элитный ящик', 'image_key' => 'crate_elite', 'stat_keys' => ['crate_elite']],
            ['key' => 'crate_normal', 'name' => 'Армейский ящик', 'image_key' => 'crate_normal', 'stat_keys' => ['crate_normal']],
            ['key' => 'crate_underwater_advanced', 'name' => 'Подводный ящик (продвинутый)', 'image_key' => 'crate_underwater_advanced', 'stat_keys' => ['crate_underwater_advanced']],
            ['key' => 'crate_underwater_basic', 'name' => 'Подводный ящик (базовый)', 'image_key' => 'crate_underwater_basic', 'stat_keys' => ['crate_underwater_basic']],
            ['key' => 'supply_drop', 'name' => 'Аирдроп', 'image_key' => 'supply_drop', 'stat_keys' => ['supply_drop']],
            ['key' => 'barrel', 'name' => 'Разбито бочек', 'image_key' => 'barrel', 'stat_keys' => ['barrel']],
            ['key' => 'crate_open', 'name' => 'Обычный ящик', 'image_key' => 'crate_open', 'stat_keys' => ['crate_open']],
        ];
        $loot = [];
        foreach ($lootDefinitions as $definition) {
            $count = 0;
            foreach ($definition['stat_keys'] as $statKey) {
                $count += (int) Statistics::getParam($rows, $statKey);
            }
            $loot[] = [
                'key' => $definition['key'],
                'name' => $definition['name'],
                'image' => Statistics::getImage($images, $definition['image_key']),
                'count' => $count,
            ];
        }

        $foundDefinitions = [
            ['key' => 'diesel_barrel', 'name' => 'Дизельная бочка', 'stat_key' => 'diesel_barrel', 'image_key' => 'diesel_barrel'],
            ['key' => 'animal_fat', 'name' => 'Животный жир', 'stat_key' => 'fat.animal', 'image_key' => 'fat.animal'],
            ['key' => 'leather', 'name' => 'Кожа', 'stat_key' => 'leather', 'image_key' => 'leather'],
            ['key' => 'scrap', 'name' => 'Скрап', 'stat_key' => 'scrap', 'image_key' => 'scrap'],
        ];
        $found = [];
        foreach ($foundDefinitions as $definition) {
            $found[] = [
                'key' => $definition['key'],
                'name' => $definition['name'],
                'image' => Statistics::getImage($images, $definition['image_key']),
                'count' => (int) Statistics::getParam($rows, $definition['stat_key']),
            ];
        }

        $result = [
            'kills' => $kills,
            'deaths' => $deaths,
            'kd' => $deaths > 0 ? round($kills / $deaths, 2) : (float) $kills,
            'playtime' => (int) Statistics::getParam($rows, 'playtime'),
            'scientists' => (int) Statistics::getParam($rows, 'scientists'),
            'headshots' => (int) Statistics::getParam($rows, 'head_hits'),
            'loot' => $loot,
            'found' => $found,
            'resources' => [
                'wood' => (int) Statistics::getParam($rows, 'wood'),
                'stones' => (int) Statistics::getParam($rows, 'stones'),
                'metal' => (int) Statistics::getParam($rows, 'metal_ore'),
                'sulfur' => (int) Statistics::getParam($rows, 'sulfur_ore'),
            ],
            'raid_score' => round(
                (float) Statistics::getParam($rows, 'c4thrown')
                + (float) Statistics::getParam($rows, 'satchelsthrown') * 0.2
                + (float) Statistics::getParam($rows, 'rocket_basic') * 0.5
                + (float) Statistics::getParam($rows, 'ammo_explosive') * 0.01,
                1
            ),
        ];
        // Statistics uploads are five-minute aggregates. A one-minute
        // formatted cache is therefore fresher than the source cadence while
        // removing repeated image lookup and mapping work after prefetches.
        Yii::$app->cache->set($cacheKey, $result, 60);

        return $result;
    }

    private function buildSupportSummary(?User $user): array
    {
        if (!$user) {
            return ['unread_count' => 0, 'open_count' => 0];
        }

        $unreadCount = count(SupportRead::unreadSupportIdsCapped((int) $user->id, 100));
        $openIds = Support::find()
            ->select('id')
            ->andWhere(['user_id' => (int) $user->id, 'status' => Support::STATUS_OPEN])
            ->limit(10)
            ->column();

        return [
            'unread_count' => $unreadCount,
            'open_count' => count($openIds),
            'counts_capped' => $unreadCount >= 100 || count($openIds) >= 10,
        ];
    }

    private function buildCalendar(Servers $server): array
    {
        $cacheKey = 'api_rust_menu_calendar_v1_' . (int) $server->id;
        $cached = Yii::$app->cache->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $from = date('Y-m-d H:i:s');
        $to = date('Y-m-d H:i:s', strtotime('+120 days'));
        $serverEvents = WipeCalendarEvent::find()
            ->andWhere(['server_id' => (int) $server->id])
            ->andWhere(['>=', 'event_at', $from])
            ->andWhere(['<=', 'event_at', $to])
            ->orderBy(['event_at' => SORT_ASC, 'id' => SORT_ASC])
            ->limit(64)
            ->all();
        $globalEvents = WipeCalendarEvent::find()
            ->andWhere(['server_id' => null, 'event_type' => WipeCalendarEvent::TYPE_GAME_UPDATE])
            ->andWhere(['>=', 'event_at', $from])
            ->andWhere(['<=', 'event_at', $to])
            ->orderBy(['event_at' => SORT_ASC, 'id' => SORT_ASC])
            ->limit(64)
            ->all();
        $models = array_merge($serverEvents, $globalEvents);
        usort($models, static function (WipeCalendarEvent $a, WipeCalendarEvent $b): int {
            $timeOrder = strcmp((string) $a->event_at, (string) $b->event_at);
            return $timeOrder !== 0 ? $timeOrder : ((int) $a->id <=> (int) $b->id);
        });
        $models = array_slice($models, 0, 64);

        $labels = [
            WipeCalendarEvent::TYPE_MAP_WIPE => 'Вайп карты',
            WipeCalendarEvent::TYPE_GLOBAL_WIPE => 'Глобальный вайп',
            WipeCalendarEvent::TYPE_GAME_UPDATE => 'Обновление Rust',
            WipeCalendarEvent::TYPE_CUSTOM => 'Событие',
        ];

        $items = array_map(function (WipeCalendarEvent $event) use ($labels) {
            return [
                'id' => (int) $event->id,
                'type' => (string) $event->event_type,
                'title' => (string) ($event->title ?: ($labels[$event->event_type] ?? 'Событие')),
                'label' => (string) ($labels[$event->event_type] ?? 'Событие'),
                'event_at' => $this->formatDateTime((string) $event->event_at),
            ];
        }, $models);
        Yii::$app->cache->set($cacheKey, $items, StatsCacheHelper::SERVER_PLAYERS_TABLE_TTL);

        return $items;
    }

    private function buildLeaderboard(Servers $server, string $wipe): array
    {
        $wipeHash = md5($wipe);
        $cacheKey = 'api_rust_menu_category_tops_v3_' . (int) $server->id . '_' . $wipeHash;
        $categories = Yii::$app->cache->get($cacheKey);
        if (!is_array($categories)) {
            // Reuse the same cached data source as /stats/{server}: eight
            // categories with three leaders, without per-player leaderboard queries.
            $websiteTops = StatsCacheHelper::getTopsFormatted($server, $wipe);
            $labels = [
                'reider' => 'Лучший рейдер',
                'killer' => 'Лучший киллер',
                'peaceful' => 'Лучший мирный',
                'playtime' => 'Топ по онлайну',
                'farmer' => 'Лучший фармер',
                'fishing' => 'Лучший рыбак',
                'hunter' => 'Лучший охотник',
                'fermer' => 'Лучший фермер',
            ];

            $categories = [];
            foreach ($labels as $key => $fallbackLabel) {
                $category = isset($websiteTops[$key]) && is_array($websiteTops[$key])
                    ? $websiteTops[$key]
                    : [];
                $categories[$key] = [
                    'label' => !empty($category['label']) ? (string) $category['label'] : $fallbackLabel,
                    'items' => array_slice(
                        isset($category['items']) && is_array($category['items']) ? $category['items'] : [],
                        0,
                        3
                    ),
                ];
            }
            Yii::$app->cache->set($cacheKey, $categories, StatsCacheHelper::SERVER_PLAYERS_TABLE_TTL);
        }

        return ['categories' => $categories];
    }

    private function buildClans(Servers $server, ?User $user): array
    {
        $myClan = null;
        if ($user) {
            $membership = ClanMember::find()
                ->with('clan')
                ->andWhere(['user_id' => (int) $user->id])
                ->andWhere(['IS', 'leave_date', null])
                ->one();
            if ($membership && $membership->clan && (int) $membership->clan->server_id === (int) $server->id) {
                $myClan = $this->serializeClan($membership->clan, $membership->role);
            }
        }

        $cacheKey = 'api_rust_menu_clans_v1_' . (int) $server->id;
        $serializedClans = Yii::$app->cache->get($cacheKey);
        if (!is_array($serializedClans)) {
            $clans = Clan::find()
                ->andWhere(['server_id' => (int) $server->id])
                ->orderBy(['experience' => SORT_DESC, 'id' => SORT_DESC])
                ->limit(8)
                ->all();
            $clanIds = array_map(static function (Clan $clan): int {
                return (int) $clan->id;
            }, $clans);
            $memberCounts = [];
            if ($clanIds !== []) {
                $countRows = ClanMember::find()
                    ->select(['clan_id', 'COUNT(*) AS member_count'])
                    ->andWhere(['clan_id' => $clanIds, 'leave_date' => null])
                    ->groupBy('clan_id')
                    ->asArray()
                    ->all();
                foreach ($countRows as $countRow) {
                    $memberCounts[(int) $countRow['clan_id']] = (int) $countRow['member_count'];
                }
            }
            foreach ($clans as $clan) {
                $clan->setActiveMembersCount($memberCounts[(int) $clan->id] ?? 0);
            }
            $serializedClans = array_map(function (Clan $clan) {
                return $this->serializeClan($clan);
            }, $clans);
            Yii::$app->cache->set(
                $cacheKey,
                $serializedClans,
                StatsCacheHelper::SERVER_PLAYERS_TABLE_TTL
            );
        }

        return [
            'my_clan' => $myClan,
            'items' => $serializedClans,
        ];
    }

    private function serializeClan(Clan $clan, ?string $role = null): array
    {
        return [
            'id' => (int) $clan->id,
            'name' => (string) $clan->name,
            'tag' => (string) $clan->tag,
            'tag_color' => (string) ($clan->color_tag ?: Clan::DEFAULT_TAG_COLOR),
            'motto' => (string) ($clan->motto ?: ''),
            'members' => (int) $clan->activeMembersCount,
            'level' => (int) $clan->level,
            'experience' => (int) $clan->experience,
            'role' => $role,
        ];
    }
}
