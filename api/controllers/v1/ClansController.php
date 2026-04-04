<?php

namespace api\controllers\v1;

use api\components\jwt\JwtAuthFilter;
use common\components\clan\ApplicantTrustHelper;
use common\models\clan\Clan;
use common\models\clan\ClanAchievement;
use common\models\clan\ClanApplication;
use common\models\clan\ClanEvent;
use common\models\clan\ClanInvite;
use common\models\clan\ClanInviteLink;
use common\models\clan\ClanMember;
use common\models\clan\ClanPost;
use common\models\clan\ClanMemberStatistics;
use common\models\clan\ClanPermission;
use common\models\clan\ClanRanking;
use common\models\clan\ClanStatistics;
use common\models\servers\Servers;
use common\models\statistics\Kills as KillsStats;
use common\models\statistics\Statistics;
use common\models\user\UserRaid;
use common\models\user\User;
use Yii;
use yii\db\Query;
use yii\data\ActiveDataProvider;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;
use yii\web\UploadedFile;
use yii\helpers\Inflector;
use yii\imagine\Image;

/**
 * API кланов: просмотр — всем; изменение — только лидеру или участникам с нужными правами.
 */
class ClansController extends BaseApiController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = [
            'class' => JwtAuthFilter::class,
            'throwException' => false,
        ];

        return $behaviors;
    }

    /**
     * GET /v1/clans — список кланов
     */
    public function actionIndex()
    {
        $request = Yii::$app->request;
        $page = max(1, (int)$request->get('page', 1));
        $pageSize = min(50, max(1, (int)$request->get('pageSize', 20)));
        $serverTag = $request->get('server_tag');

        $query = Clan::find()->with(['leaderUser.userProfile', 'server']);

        if ($serverTag !== null && $serverTag !== '') {
            $server = Servers::findOne(['tag' => $serverTag]);
            if (!$server) {
                return $this->successResponse(['items' => [], 'pagination' => ['page' => $page, 'pageSize' => $pageSize, 'total' => 0]]);
            }
            $query->andWhere(['server_id' => $server->id]);
        }

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'page' => $page - 1,
                'pageSize' => $pageSize,
            ],
            'sort' => [
                'defaultOrder' => ['created_at' => SORT_DESC],
            ],
        ]);

        $models = $provider->getModels();
        $items = [];
        foreach ($models as $clan) {
            $items[] = $this->serializeClanListItem($clan);
        }

        return $this->successResponse([
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => (int)$provider->getTotalCount(),
                'pageCount' => $provider->pagination ? (int)$provider->pagination->getPageCount() : 0,
            ],
        ]);
    }

    /**
     * POST /v1/clans — создать клан
     */
    public function actionCreate()
    {
        $user = $this->getCurrentUser();
        $body = $this->getJsonBody();

        $name = isset($body['name']) ? trim((string)$body['name']) : '';
        $tag = isset($body['tag']) ? trim((string)$body['tag']) : '';
        $serverId = isset($body['server_id']) ? (int)$body['server_id'] : null;
        if (!$serverId && !empty($body['server_tag'])) {
            $server = Servers::findOne(['tag' => $body['server_tag']]);
            $serverId = $server ? $server->id : null;
        }

        $motto = isset($body['motto']) ? trim((string)$body['motto']) : null;

        if ($name === '' || $tag === '' || !$serverId) {
            throw new BadRequestHttpException('name, tag and server_id (or server_tag) are required');
        }

        $server = Servers::findOne($serverId);
        if (!$server) {
            throw new BadRequestHttpException('Server not found');
        }

        if ($this->hasActiveClanOnServer($user->id, $serverId)) {
            return $this->errorResponse('ALREADY_IN_CLAN', 'You already have an active clan on this server', [], 409);
        }

        $clan = new Clan();
        $clan->name = $name;
        $clan->tag = $tag;
        $clan->leader_user_id = $user->id;
        $clan->server_id = $serverId;
        $clan->motto = $motto ?: null;
        $clan->privacy = Clan::PRIVACY_OPEN;
        $clan->description = isset($body['description']) ? (string)$body['description'] : null;
        if (isset($body['color_tag'])) {
            $clan->color_tag = trim((string)$body['color_tag']);
        }

        if (!$clan->save()) {
            return $this->validationErrorResponse($clan);
        }

        $clan->refresh();
        $clan = Clan::find()->where(['id' => $clan->id])->with(['leaderUser.userProfile', 'server'])->one();

        return $this->successResponse($this->serializeClanDetail($clan, $this->getActiveMember($clan)), [], 201);
    }

    /**
     * GET /v1/clans/permissions — справочник прав
     */
    public function actionPermissions()
    {
        $rows = ClanPermission::find()->orderBy(['id' => SORT_ASC])->all();
        $items = [];
        foreach ($rows as $p) {
            $items[] = [
                'id' => (int)$p->id,
                'key' => $p->key,
                'name' => $p->name,
                'description' => $p->description,
            ];
        }

        return $this->successResponse(['items' => $items]);
    }

    /**
     * GET /v1/clans/list?ip=1.2.3.4&port=28015
     * Ответ: JSON-массив в формате плагина Oxide ClanManager (массив объектов ClanData).
     */
    public function actionGamePluginList()
    {
        $ip = trim((string)Yii::$app->request->get('ip', ''));
        $portRaw = Yii::$app->request->get('port');
        $port = $portRaw !== null && $portRaw !== '' ? (int)$portRaw : null;

        if ($ip === '' || $port === null || $port <= 0) {
            Yii::$app->response->statusCode = 400;
            return ['error' => 'ip and port are required'];
        }

        $server = Servers::find()
            ->where(['port' => $port])
            ->andWhere(['or', ['ip' => $ip], ['text_ip' => $ip]])
            ->orderBy(['status' => SORT_DESC, 'id' => SORT_ASC])
            ->one();

        if (!$server) {
            return [];
        }

        $clans = Clan::find()
            ->where(['server_id' => $server->id])
            ->with(['activeMembers.user'])
            ->all();

        $out = [];
        foreach ($clans as $clan) {
            $members = $clan->activeMembers;
            $memberIds = array_map(static function (ClanMember $m) {
                return (int)$m->id;
            }, $members);

            $permByMemberId = [];
            if ($memberIds !== []) {
                $rows = (new Query())
                    ->from(['cmp' => 'clan_member_permissions'])
                    ->innerJoin(['cp' => 'clan_permissions'], '[[cp]].[[id]] = [[cmp]].[[permission_id]]')
                    ->where(['cmp.clan_member_id' => $memberIds])
                    ->select(['cmp.clan_member_id', 'cp.key'])
                    ->all();
                foreach ($rows as $row) {
                    $mid = (int)$row['clan_member_id'];
                    if (!isset($permByMemberId[$mid])) {
                        $permByMemberId[$mid] = [];
                    }
                    $permByMemberId[$mid][] = (string)$row['key'];
                }
            }

            $users = [];
            foreach ($members as $member) {
                $user = $member->user;
                if (!$user) {
                    continue;
                }
                $steamId = (string)$user->steam_id;
                if ($steamId === '' || $steamId === '0') {
                    continue;
                }

                $flags = $this->gamePluginMemberAuthFlags($member, $permByMemberId[(int)$member->id] ?? []);
                $users[] = array_merge([
                    'steam_id' => $steamId,
                ], $flags);
            }

            $colorTag = $clan->color_tag;
            if (!in_array($colorTag, Clan::TAG_COLOR_PRESETS, true)) {
                $colorTag = Clan::DEFAULT_TAG_COLOR;
            }

            $out[] = [
                'tag' => $clan->tag,
                'color_tag' => $colorTag,
                'update_at' => gmdate('c', (int)$clan->updated_at),
                'users' => $users,
            ];
        }

        return $out;
    }

    /**
     * Флаги авторизации в объектах для плагина (совпадают с правами auth_*).
     *
     * @param string[] $permissionKeys
     * @return array{lock: bool, turrets: bool, defense: bool, cupboard_auth: bool}
     */
    private function gamePluginMemberAuthFlags(ClanMember $member, array $permissionKeys): array
    {
        if ($member->isLeader()) {
            return [
                'lock' => true,
                'turrets' => true,
                'defense' => true,
                'cupboard_auth' => true,
            ];
        }

        $set = array_fill_keys($permissionKeys, true);

        return [
            'lock' => !empty($set['auth_lock']),
            'turrets' => !empty($set['auth_turret']),
            'defense' => !empty($set['auth_sam']),
            'cupboard_auth' => !empty($set['auth_cupboard']),
        ];
    }

    /**
     * GET /v1/clans/rankings
     */
    public function actionRankings()
    {
        $request = Yii::$app->request;
        $serverTag = $request->get('server_tag');
        $type = $request->get('type', 'overall');
        $period = $request->get('period', 'all_time');
        $page = max(1, (int)$request->get('page', 1));
        $pageSize = min(50, max(1, (int)$request->get('pageSize', 20)));

        $query = ClanRanking::find()->with(['clan']);

        if ($serverTag !== null && $serverTag !== '') {
            $server = Servers::findOne(['tag' => $serverTag]);
            if ($server) {
                $query->andWhere(['server_id' => $server->id]);
            } else {
                return $this->successResponse(['items' => [], 'pagination' => ['page' => $page, 'pageSize' => $pageSize, 'total' => 0]]);
            }
        }

        $query->andWhere(['ranking_type' => $type, 'period' => $period])
            ->orderBy(['position' => SORT_ASC]);

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'page' => $page - 1,
                'pageSize' => $pageSize,
            ],
        ]);

        $items = [];
        foreach ($provider->getModels() as $row) {
            $items[] = [
                'position' => (int)$row->position,
                'score' => (float)$row->score,
                'ranking_type' => $row->ranking_type,
                'period' => $row->period,
                'clan' => $row->clan ? $this->serializeClanListItem($row->clan) : null,
            ];
        }

        return $this->successResponse([
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => (int)$provider->getTotalCount(),
            ],
        ]);
    }

    /**
     * GET /v1/clans/podium?server_tag= — топ-3 клана по overall / all_time и топ участников по киллам за текущий вайп.
     */
    public function actionPodium()
    {
        $serverTag = Yii::$app->request->get('server_tag');
        if ($serverTag === null || $serverTag === '') {
            throw new BadRequestHttpException('server_tag is required');
        }

        $server = Servers::findOne(['tag' => $serverTag]);
        if (!$server) {
            return $this->successResponse(['items' => [], 'current_wipe' => null]);
        }

        $wipe = $server->currentWipe();
        $rankRows = ClanRanking::find()
            ->where([
                'server_id' => $server->id,
                'ranking_type' => ClanRanking::RANKING_OVERALL,
                'period' => ClanRanking::PERIOD_ALL_TIME,
            ])
            ->orderBy(['position' => SORT_ASC])
            ->limit(3)
            ->with(['clan.leaderUser.userProfile', 'clan.server'])
            ->all();

        if ($rankRows === []) {
            return $this->successResponse(['items' => [], 'current_wipe' => $wipe]);
        }

        $clanIds = [];
        foreach ($rankRows as $r) {
            if ($r->clan) {
                $clanIds[] = (int)$r->clan->id;
            }
        }
        $clanIds = array_values(array_unique($clanIds));

        $membersByClan = [];
        $allMemberIds = [];
        if ($clanIds !== []) {
            $members = ClanMember::find()
                ->where(['clan_id' => $clanIds])
                ->andWhere(['IS', 'leave_date', null])
                ->with(['user.userProfile'])
                ->all();
            foreach ($members as $m) {
                $cid = (int)$m->clan_id;
                if (!isset($membersByClan[$cid])) {
                    $membersByClan[$cid] = [];
                }
                $membersByClan[$cid][] = $m;
                $allMemberIds[] = (int)$m->id;
            }
        }

        $statsByMemberId = [];
        if ($wipe !== null && $wipe !== '' && $allMemberIds !== []) {
            $statModels = ClanMemberStatistics::find()
                ->where([
                    'server_id' => $server->id,
                    'wipe' => $wipe,
                    'clan_member_id' => $allMemberIds,
                ])
                ->with('statValues')
                ->all();
            foreach ($statModels as $sm) {
                $statsByMemberId[(int)$sm->clan_member_id] = $sm;
            }
        }

        $items = [];
        foreach ($rankRows as $r) {
            if (!$r->clan) {
                continue;
            }
            $clan = $r->clan;
            $cid = (int)$clan->id;
            $enriched = [];
            foreach ($membersByClan[$cid] ?? [] as $m) {
                $stat = $statsByMemberId[(int)$m->id] ?? null;
                $kills = $stat ? (int)$stat->getStatValue('kills') : 0;
                $deaths = $stat ? (int)$stat->getStatValue('deaths') : 0;
                $kd = $deaths > 0 ? round($kills / $deaths, 2) : (float)$kills;
                $displayStatus = $m->user ? $m->user->getDisplayStatus() : null;
                $enriched[] = [
                    'kills' => $kills,
                    'deaths' => $deaths,
                    'kd' => $kd,
                    'user' => $m->user ? $this->serializeUser($m->user) : null,
                    /** как в UserTop / tops: null если VIP скрыл статус */
                    'status' => $displayStatus === null ? null : (bool) $displayStatus,
                    'is_hidden' => $m->user !== null && $displayStatus === null,
                ];
            }
            usort($enriched, static function ($a, $b) {
                return $b['kills'] <=> $a['kills'];
            });
            $topMembers = array_slice($enriched, 0, 8);

            $items[] = [
                'position' => (int)$r->position,
                'score' => (float)$r->score,
                'clan' => $this->serializeClanListItem($clan),
                'top_members' => $topMembers,
            ];
        }

        return $this->successResponse([
            'items' => $items,
            'current_wipe' => $wipe,
        ]);
    }

    /**
     * GET /v1/clans/my-invites — входящие приглашения (текущий пользователь)
     */
    public function actionMyInvites()
    {
        $user = $this->getCurrentUser();
        $invites = ClanInvite::find()
            ->where(['invited_user_id' => $user->id, 'status' => ClanInvite::STATUS_PENDING])
            ->with(['clan.server', 'inviterUser.userProfile'])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        $items = [];
        foreach ($invites as $inv) {
            $items[] = $this->serializeInvite($inv);
        }

        return $this->successResponse(['items' => $items]);
    }

    /**
     * GET /v1/clans/my-memberships — активные членства текущего пользователя (для быстрых ссылок в UI)
     */
    public function actionMyMemberships()
    {
        $user = $this->getCurrentUser();
        $members = ClanMember::find()
            ->where(['user_id' => $user->id])
            ->andWhere(['IS', 'leave_date', null])
            ->with(['clan.server', 'clan.leaderUser.userProfile'])
            ->all();

        $items = [];
        foreach ($members as $m) {
            if (!$m->clan) {
                continue;
            }
            $items[] = [
                'member_id' => (int)$m->id,
                'role' => $m->role,
                'clan' => $this->serializeClanListItem($m->clan),
            ];
        }

        return $this->successResponse(['items' => $items]);
    }

    /**
     * GET /v1/clans/{serverTag}/{id}` — карточка клана
     */
    public function actionView($serverTag, $id)
    {
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $currentMember = $this->getActiveMember($clan);

        return $this->successResponse($this->serializeClanDetail($clan, $currentMember));
    }

    /**
     * GET /v1/clans/{serverTag}/lookup?slug=my-clan-12 — карточка по полному URL-slug (как в списке кланов).
     */
    public function actionLookupBySlug($serverTag)
    {
        $slug = trim((string)Yii::$app->request->get('slug', ''));
        if ($slug === '') {
            throw new BadRequestHttpException('slug is required');
        }

        $server = Servers::find()
            ->where('LOWER(tag) = :tag', [':tag' => mb_strtolower(trim($serverTag), 'UTF-8')])
            ->one();
        if (!$server) {
            throw new NotFoundHttpException('Server not found');
        }

        $clans = Clan::find()
            ->where(['server_id' => $server->id])
            ->with(['leaderUser.userProfile', 'server'])
            ->all();

        foreach ($clans as $clan) {
            if ($this->getClanUrlSlug($clan) === $slug) {
                $currentMember = $this->getActiveMember($clan);

                return $this->successResponse($this->serializeClanDetail($clan, $currentMember));
            }
        }

        throw new NotFoundHttpException('Clan not found');
    }

    /**
     * GET /v1/clans/lookup-global?slug=my-clan-12 — карточка по ЧПУ без указания сервера (slug оканчивается на -{id}).
     */
    public function actionLookupGlobal()
    {
        $slug = trim((string)Yii::$app->request->get('slug', ''));
        if ($slug === '') {
            throw new BadRequestHttpException('slug is required');
        }

        if (preg_match('/^\d+$/', $slug)) {
            $clan = Clan::find()
                ->where(['id' => (int)$slug])
                ->with(['leaderUser.userProfile', 'server'])
                ->one();
            if ($clan) {
                $currentMember = $this->getActiveMember($clan);

                return $this->successResponse($this->serializeClanDetail($clan, $currentMember));
            }
            throw new NotFoundHttpException('Clan not found');
        }

        if (preg_match('/-(\d+)$/', $slug, $m)) {
            $id = (int)$m[1];
            $clan = Clan::find()
                ->where(['id' => $id])
                ->with(['leaderUser.userProfile', 'server'])
                ->one();
            if ($clan && $this->getClanUrlSlug($clan) === $slug) {
                $currentMember = $this->getActiveMember($clan);

                return $this->successResponse($this->serializeClanDetail($clan, $currentMember));
            }
        }

        throw new NotFoundHttpException('Clan not found');
    }

    /**
     * PATCH/PUT /v1/clans/{serverTag}/{id}
     */
    public function actionUpdate($serverTag, $id)
    {
        $user = $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $member = $this->requireClanMember($clan);
        if (!$member->canEditClan()) {
            throw new ForbiddenHttpException('No permission to edit clan');
        }

        $body = $this->getJsonBody();
        if (isset($body['name'])) {
            $clan->name = trim((string)$body['name']);
        }
        if (isset($body['tag'])) {
            $clan->tag = trim((string)$body['tag']);
        }
        if (array_key_exists('motto', $body)) {
            $clan->motto = $body['motto'] !== null && $body['motto'] !== '' ? (string)$body['motto'] : null;
        }
        if (array_key_exists('description', $body)) {
            $clan->description = $body['description'] !== null ? (string)$body['description'] : null;
        }
        if (isset($body['level'])) {
            $clan->level = max(1, (int)$body['level']);
        }
        if (isset($body['experience'])) {
            $clan->experience = max(0, (int)$body['experience']);
        }
        if (isset($body['color_tag'])) {
            $clan->color_tag = trim((string)$body['color_tag']);
        }

        if (!$clan->save()) {
            return $this->validationErrorResponse($clan);
        }

        $clan->addEvent('clan_updated', Yii::t('common', 'Информация клана обновлена'), $user->id);

        $clan->refresh();
        $clan = Clan::find()->where(['id' => $clan->id])->with(['leaderUser.userProfile', 'server'])->one();

        return $this->successResponse($this->serializeClanDetail($clan, $this->getActiveMember($clan)));
    }

    /**
     * DELETE /v1/clans/{serverTag}/{id}
     */
    public function actionDelete($serverTag, $id)
    {
        $user = $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        if ((int)$clan->leader_user_id !== (int)$user->id) {
            throw new ForbiddenHttpException('Only the leader can delete the clan');
        }

        $clan->delete();

        return $this->successResponse(['deleted' => true]);
    }

    /**
     * PATCH /v1/clans/{serverTag}/{id}/privacy — только лидер
     */
    public function actionPrivacy($serverTag, $id)
    {
        $user = $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        if ((int)$clan->leader_user_id !== (int)$user->id) {
            throw new ForbiddenHttpException('Only the leader can change privacy');
        }

        $body = $this->getJsonBody();
        $privacy = isset($body['privacy']) ? (string)$body['privacy'] : null;
        if ($privacy === null) {
            throw new BadRequestHttpException('privacy is required');
        }

        $clan->privacy = $privacy;
        if (!$clan->save()) {
            return $this->validationErrorResponse($clan);
        }

        $clan->addEvent('privacy_changed', Yii::t('common', 'Приватность клана изменена на: {privacy}', ['privacy' => $clan->getPrivacyLabel()]), $user->id);

        $clan = Clan::find()->where(['id' => $clan->id])->with(['leaderUser.userProfile', 'server'])->one();

        return $this->successResponse($this->serializeClanDetail($clan, $this->getActiveMember($clan)));
    }

    /**
     * GET /v1/clans/{serverTag}/{id}/player-kills
     * История убийств по всем активным участникам за вайп (как вкладка «Убийства» у игрока, без дуэлей).
     */
    public function actionPlayerKills($serverTag, $id)
    {
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $server = $clan->server;
        if (!$server) {
            throw new NotFoundHttpException('Сервер не найден');
        }

        $wipeParam = Yii::$app->request->get('wipe');
        $resolvedWipe = ($wipeParam !== null && $wipeParam !== '')
            ? (string)$wipeParam
            : $server->currentWipe();

        $members = $clan->getMembers()
            ->andWhere(['IS', 'leave_date', null])
            ->with('user')
            ->all();
        $steamIds = [];
        foreach ($members as $m) {
            $u = $m->user;
            if ($u && !empty($u->steam_id) && strlen((string)$u->steam_id) === 17) {
                $steamIds[] = (string)$u->steam_id;
            }
        }
        $steamIds = array_values(array_unique($steamIds));

        $wipeKey = str_replace('/', '_', (string)($resolvedWipe ?? 'current'));
        $cacheKey = 'api_clan_player_kills_' . $serverTag . '_' . (int)$id . '_' . $wipeKey . '_v1';
        $cached = Yii::$app->cache->get($cacheKey);
        if ($cached === false) {
            $killsList = KillsStats::getKillsForSteamIds($server, $steamIds, 30, $resolvedWipe);
            $cached = [
                'kills' => $this->mapClanKillsForApi($killsList),
                'medical' => [],
            ];
            Yii::$app->cache->set($cacheKey, $cached, 300);
        }

        return $this->successResponse($cached);
    }

    /**
     * @param array<int, array<string, mixed>> $killsList
     * @return array<int, array<string, mixed>>
     */
    private function mapClanKillsForApi(array $killsList): array
    {
        return array_map(function ($k) {
            return [
                'id' => (int)($k['id'] ?? 0),
                'type' => $k['type'] ?? 'kill',
                'steam_id' => $k['steam_id'] ?? '',
                'dead' => $k['dead'] ?? '',
                'weapon' => $k['weapon'] ?? null,
                'weapon_name' => $k['weapon_name'] ?? null,
                'weapon_image' => $k['weapon_image'] ?? null,
                'distance' => (int)($k['distance'] ?? 0),
                'name' => $k['name'] ?? null,
                'link' => $k['link'] ?? null,
                'avatar' => $k['avatar'] ?? null,
                'dead_name' => $k['dead_name'] ?? null,
                'dead_link' => $k['dead_link'] ?? null,
                'dead_avatar' => $k['dead_avatar'] ?? null,
                'deadLink' => $k['dead_link'] ?? null,
                'signs' => $k['signs'] ?? null,
                'wears' => $k['wears'] ?? null,
                'bot' => !empty($k['bot']),
                'animal' => $k['animal'] ?? null,
                'animal2' => $k['animal2'] ?? null,
                'created_at' => $k['created_at'] ?? '',
            ];
        }, $killsList);
    }

    /**
     * GET /v1/clans/{serverTag}/{id}/statistics
     */
    public function actionStatistics($serverTag, $id)
    {
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $wipeParam = Yii::$app->request->get('wipe');
        $server = $clan->server;
        $resolvedWipe = ($wipeParam !== null && $wipeParam !== '')
            ? (string)$wipeParam
            : ($server ? $server->currentWipe() : null);
        $stats = $resolvedWipe ? $clan->getClanStatistics($resolvedWipe) : $clan->getClanStatistics(null);

        $raidWidget = $this->buildClanRaidsWidget($clan, $resolvedWipe);

        // loot_widget и loot_crafts всегда с каталогом и image; без строки clan_statistics — нули
        return $this->successResponse([
            'wipe' => $resolvedWipe,
            'statistics' => $stats ? $stats->getStatisticsForApi() : null,
            'loot_widget' => $this->buildClanLootWidget($stats),
            'raid_widget' => $raidWidget,
            'loot_crafts' => $this->buildClanLootCraftsData($stats),
        ]);
    }

    /**
     * Полный виджет рейдов из user_raid:
     * - последние 8 рейдов клана по другим кланам (на том же сервере, в рамках wipe),
     * - total / more_count для подписи "и еще N".
     *
     * Рейд считается "по клану", если среди owners есть steam_id участника другого активного клана.
     *
     * @return array{
     *   raids_against_clans:int,
     *   total:int,
     *   more_count:int,
     *   items:array<int, array<string,mixed>>
     * }
     */
    private function buildClanRaidsWidget(Clan $clan, ?string $wipe): array
    {
        $attackerIds = ClanMember::find()
            ->select('user_id')
            ->where(['clan_id' => $clan->id])
            ->column();
        $attackerIds = array_map('intval', $attackerIds);

        $membershipRows = ClanMember::find()
            ->alias('m')
            ->select(['m.user_id', 'm.clan_id', 'u.steam_id'])
            ->innerJoin(['u' => User::tableName()], 'u.id = m.user_id')
            ->andWhere(['not', ['u.steam_id' => null]])
            ->asArray()
            ->all();

        // Для входящих рейдов ("атаковали наш клан") учитываем всех участников клана,
        // включая бывших: в owners могут быть steam_id игроков, которые уже вышли.
        $thisClanSteamIds = User::find()
            ->alias('u')
            ->select('u.steam_id')
            ->innerJoin(['m' => ClanMember::tableName()], 'm.user_id = u.id')
            ->where(['m.clan_id' => (int)$clan->id])
            ->andWhere(['not', ['u.steam_id' => null]])
            ->column();
        $thisClanSteamIdSet = array_fill_keys(array_map('strval', $thisClanSteamIds), true);

        $targetClanIdsBySteam = [];
        $userToClanIds = [];
        $allClanIds = [(int)$clan->id => true];
        foreach ($membershipRows as $row) {
            $sid = (string)($row['steam_id'] ?? '');
            $cid = (int)($row['clan_id'] ?? 0);
            $uid = (int)($row['user_id'] ?? 0);
            if ($sid === '' || $cid <= 0) {
                continue;
            }
            if (!isset($targetClanIdsBySteam[$sid])) {
                $targetClanIdsBySteam[$sid] = [];
            }
            $targetClanIdsBySteam[$sid][$cid] = true;
            if ($uid > 0) {
                if (!isset($userToClanIds[$uid])) {
                    $userToClanIds[$uid] = [];
                }
                $userToClanIds[$uid][$cid] = true;
            }
            $allClanIds[$cid] = true;
        }

        $targetClans = Clan::find()
            ->where(['id' => array_keys($allClanIds)])
            ->with(['server', 'leaderUser'])
            ->all();
        $targetClanMap = [];
        foreach ($targetClans as $c) {
            $targetClanMap[(int)$c->id] = $c;
        }

        $raidsQuery = UserRaid::find()
            ->select(['id', 'user_id', 'owners', 'created_at', 'location', 'type'])
            ->andWhere(['server_id' => (int)$clan->server_id])
            // В прод-данных type может быть "cupboard" или содержать префиксы/суффиксы.
            ->andWhere(['like', 'type', 'cupboard']);
        if ($wipe !== null && $wipe !== '') {
            $raidsQuery->andWhere(['wipe' => $wipe]);
        }
        $relatedOr = ['or'];
        if ($attackerIds !== []) {
            $relatedOr[] = ['user_id' => $attackerIds];
        }
        foreach (array_keys($thisClanSteamIdSet) as $steamId) {
            if ($steamId !== '') {
                $relatedOr[] = ['like', 'owners', $steamId];
            }
        }
        if (count($relatedOr) > 1) {
            $raidsQuery->andWhere($relatedOr);
        } else {
            return [
                'raids_against_clans' => 0,
                'outgoing_count' => 0,
                'incoming_count' => 0,
                'total' => 0,
                'more_count' => 0,
                'items' => [],
            ];
        }
        $raidsQuery->orderBy(['created_at' => SORT_DESC]);

        $rows = $raidsQuery->asArray()->all();
        if ($rows === []) {
            return [
                'raids_against_clans' => 0,
                'outgoing_count' => 0,
                'incoming_count' => 0,
                'total' => 0,
                'more_count' => 0,
                'items' => [],
            ];
        }

        $attackerClanPayload = [
            'id' => (int)$clan->id,
            'name' => (string)$clan->name,
            'tag' => (string)$clan->tag,
            'logo_url' => (string)$clan->getLogoUrl(),
            'server_tag' => $clan->server ? (string)$clan->server->tag : null,
            'leader_country_code' => $clan->leaderUser ? strtoupper((string)$clan->leaderUser->getCountryByIp()) : null,
        ];
        $attackerUsers = User::find()
            ->select(['id', 'username'])
            ->where(['id' => array_values(array_unique(array_map(static function ($r) {
                return (int)($r['user_id'] ?? 0);
            }, $rows)))])
            ->indexBy('id')
            ->all();

        $items = [];
        $total = 0;
        $outgoingCount = 0;
        $incomingCount = 0;
        foreach ($rows as $row) {
            $ownersRaw = $row['owners'] ?? null;
            if (!is_string($ownersRaw) || $ownersRaw === '') {
                continue;
            }
            $owners = json_decode($ownersRaw, true);
            if (!is_array($owners) || $owners === []) {
                continue;
            }
            $targetIdsSet = [];
            foreach ($owners as $ownerSteamId) {
                $sid = (string)$ownerSteamId;
                if ($sid === '' || !isset($targetClanIdsBySteam[$sid])) {
                    continue;
                }
                foreach (array_keys($targetClanIdsBySteam[$sid]) as $targetId) {
                    $targetIdsSet[(int)$targetId] = true;
                }
            }
            if ($targetIdsSet === []) {
                $targetIdsSet = [];
            }

            $attackerClanIds = isset($userToClanIds[(int)$row['user_id']])
                ? array_map('intval', array_keys($userToClanIds[(int)$row['user_id']]))
                : [];
            $isIncomingForThisClan = false;
            foreach ($owners as $ownerSteamId) {
                $sid = (string)$ownerSteamId;
                if ($sid !== '' && isset($thisClanSteamIdSet[$sid])) {
                    $isIncomingForThisClan = true;
                    break;
                }
            }
            $isOutgoingForThisClan = in_array((int)$clan->id, $attackerClanIds, true);
            $isRelated = $isOutgoingForThisClan
                || isset($targetIdsSet[(int)$clan->id])
                || $isIncomingForThisClan;
            if (!$isRelated) {
                continue;
            }

            $targets = [];
            foreach (array_keys($targetIdsSet) as $targetId) {
                $targetClan = $targetClanMap[(int)$targetId] ?? null;
                if ($targetClan === null) {
                    continue;
                }
                $targets[] = [
                    'id' => (int)$targetClan->id,
                    'name' => (string)$targetClan->name,
                    'tag' => (string)$targetClan->tag,
                    'logo_url' => (string)$targetClan->getLogoUrl(),
                    'server_tag' => $targetClan->server ? (string)$targetClan->server->tag : null,
                    'leader_country_code' => $targetClan->leaderUser ? strtoupper((string)$targetClan->leaderUser->getCountryByIp()) : null,
                ];
            }
            // Если рейд входящий по нашему клану, но в targets наш клан не попал (например владелец уже не активен),
            // добавляем наш клан явно в цели для корректного отображения.
            if ($isIncomingForThisClan) {
                $hasThisClanTarget = false;
                foreach ($targets as $tgt) {
                    if ((int)($tgt['id'] ?? 0) === (int)$clan->id) {
                        $hasThisClanTarget = true;
                        break;
                    }
                }
                if (!$hasThisClanTarget) {
                    array_unshift($targets, $attackerClanPayload);
                }
            }

            $attackerClan = null;
            foreach ($attackerClanIds as $cid) {
                if ($cid !== (int)$clan->id && isset($targetClanMap[$cid])) {
                    $attackerClan = $targetClanMap[$cid];
                    break;
                }
            }
            if ($attackerClan === null) {
                foreach ($attackerClanIds as $cid) {
                    if (isset($targetClanMap[$cid])) {
                        $attackerClan = $targetClanMap[$cid];
                        break;
                    }
                }
            }
            if ($attackerClan === null) {
                $attackerClan = $clan;
            }
            $attackerPayload = [
                'id' => (int)$attackerClan->id,
                'name' => (string)$attackerClan->name,
                'tag' => (string)$attackerClan->tag,
                'logo_url' => (string)$attackerClan->getLogoUrl(),
                'server_tag' => $attackerClan->server ? (string)$attackerClan->server->tag : null,
                'leader_country_code' => $attackerClan->leaderUser ? strtoupper((string)$attackerClan->leaderUser->getCountryByIp()) : null,
            ];

            // Бэкенд-фильтр "неизвестный клан": оставляем только валидные цели и исключаем атакующего из списка целей.
            $targets = array_values(array_filter($targets, static function ($tgt) use ($attackerPayload) {
                return isset($tgt['id']) && (int)$tgt['id'] > 0 && (int)$tgt['id'] !== (int)$attackerPayload['id'];
            }));
            if ($targets === []) {
                continue;
            }
            // Исключаем некорректные случаи "клан атаковал сам себя".
            $hasOpponent = false;
            foreach ($targets as $tgt) {
                if ((int)($tgt['id'] ?? 0) !== (int)$attackerPayload['id']) {
                    $hasOpponent = true;
                    break;
                }
            }
            if (!$hasOpponent) {
                continue;
            }

            if ($isOutgoingForThisClan) {
                $outgoingCount++;
            }
            if ($isIncomingForThisClan) {
                $incomingCount++;
            }
            $total++;
            $items[] = [
                'id' => (int)$row['id'],
                'created_at' => (string)($row['created_at'] ?? ''),
                'location' => (string)($row['location'] ?? ''),
                'type' => (string)($row['type'] ?? ''),
                'raider_user' => isset($attackerUsers[(int)$row['user_id']]) ? [
                    'id' => (int)$attackerUsers[(int)$row['user_id']]->id,
                    'username' => (string)$attackerUsers[(int)$row['user_id']]->username,
                    'avatar' => (string)$attackerUsers[(int)$row['user_id']]->getAvatar(),
                ] : null,
                'attacker_clan' => $attackerPayload,
                'target_clans' => $targets,
            ];
        }

        return [
            'raids_against_clans' => $total,
            'outgoing_count' => $outgoingCount,
            'incoming_count' => $incomingCount,
            'total' => $total,
            'more_count' => max(0, $total - 8),
            'items' => $items,
        ];
    }

    /**
     * Виджет лута на карточке клана: 4 позиции, image_large как в сводках.
     * Без строки clan_statistics — count = 0, картинки те же.
     *
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function buildClanLootWidget(?ClanStatistics $stats): array
    {
        $images = Statistics::productsImages();
        $v = static function (?ClanStatistics $s, string $key): int {
            return $s ? (int) $s->getStatValue($key) : 0;
        };
        $crateCombined = $v($stats, 'total_codelockedhackablecrate')
            + $v($stats, 'total_codelockedhackablecrate_oilrig');

        return [
            'items' => [
                [
                    'key' => 'crate_combined',
                    'name' => Yii::t('common', 'Крейт'),
                    'image' => Statistics::getImageLarge($images, 'codelockedhackablecrate'),
                    'count' => $crateCombined,
                ],
                [
                    'key' => 'crate_elite',
                    'name' => Yii::t('common', 'Элитный ящик'),
                    'image' => Statistics::getImageLarge($images, 'crate_elite'),
                    'count' => $v($stats, 'total_crate_elite'),
                ],
                [
                    'key' => 'crate_normal',
                    'name' => Yii::t('common', 'Армейский ящик'),
                    'image' => Statistics::getImageLarge($images, 'crate_normal'),
                    'count' => $v($stats, 'total_crate_normal'),
                ],
                [
                    'key' => 'supply_drop',
                    'name' => Yii::t('common', 'Аирдроп'),
                    'image' => Statistics::getImageLarge($images, 'supply_drop'),
                    'count' => $v($stats, 'total_supply_drop'),
                ],
            ],
        ];
    }

    /**
     * Тот же формат, что GET /v1/stats/player-loot-crafts: лут, карты, чертежи — из сумм total_* по клану.
     * Если записи clan_statistics за вайп ещё нет — отдаём тот же каталог с count = 0 и теми же image (как у игрока без данных).
     *
     * @return array{loot: array<int, array<string, mixed>>, access_cards: array<int, array<string, mixed>>, blueprints: array<int, array<string, mixed>>}
     */
    private function buildClanLootCraftsData(?ClanStatistics $clanStats): array
    {
        $images = Statistics::productsImages();
        $total = static function (string $suffix) use ($clanStats): int {
            return $clanStats ? (int) $clanStats->getStatValue('total_' . $suffix) : 0;
        };

        $lootKeys = [
            'codelockedhackablecrate_oilrig' => Yii::t('common', 'Крейт на нефтевышке'),
            'codelockedhackablecrate' => Yii::t('common', 'Крейт'),
            'crate_elite' => Yii::t('common', 'Элитный ящик'),
            'crate_normal' => Yii::t('common', 'Армейский ящик'),
            'crate_underwater_advanced' => Yii::t('common', 'Подводный ящик (продвинутый)'),
            'crate_underwater_basic' => Yii::t('common', 'Подводный ящик (базовый)'),
            'supply_drop' => Yii::t('common', 'Аирдроп'),
            'barrel' => Yii::t('common', 'Разбито бочек'),
            'crate_open' => Yii::t('common', 'Обычный ящик'),
            'bradleys' => Yii::t('common', 'Взорванные танки'),
            'helicopters' => Yii::t('common', 'Патрульные вертолёты'),
        ];

        $loot = [];
        foreach ($lootKeys as $key => $name) {
            $count = $total($key);
            $loot[] = [
                'key' => $key,
                'name' => $name,
                'image' => Statistics::getImage($images, $key),
                'image_large' => Statistics::getImageLarge($images, $key),
                'count' => $count,
            ];
        }

        $accessCardKeys = [
            ['key' => 'card_level_1', 'name' => Yii::t('common', 'Зелёная карта доступа'), 'imageKey' => 'card_level_1'],
            ['key' => 'card_level_2', 'name' => Yii::t('common', 'Синяя карта доступа'), 'imageKey' => 'card_level_2'],
            ['key' => 'card_level_3', 'name' => Yii::t('common', 'Красная карта доступа'), 'imageKey' => 'card_level_3'],
        ];
        $access_cards = [];
        foreach ($accessCardKeys as $item) {
            $count = $total($item['key']);
            $access_cards[] = [
                'key' => $item['key'],
                'name' => $item['name'],
                'image' => Statistics::getImage($images, $item['imageKey']),
                'count' => $count,
            ];
        }

        $blueprintKeys = [
            'basicblueprintfragment' => Yii::t('common', 'Фрагмент простого чертежа'),
            'advancedblueprintfragment' => Yii::t('common', 'Фрагмент продвинутого чертежа'),
        ];
        $blueprints = [];
        foreach ($blueprintKeys as $key => $name) {
            $count = $total($key);
            if ($count > 0) {
                $blueprints[] = [
                    'key' => $key,
                    'name' => $name,
                    'image' => Statistics::getImage($images, $key),
                    'count' => $count,
                ];
            }
        }

        return [
            'loot' => $loot,
            'access_cards' => $access_cards,
            'blueprints' => $blueprints,
        ];
    }

    /**
     * GET /v1/clans/{serverTag}/{id}/members
     */
    public function actionMembers($serverTag, $id)
    {
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $viewer = $this->getActiveMember($clan);

        $includeFormer = (int)Yii::$app->request->get('include_former', 0) === 1;

        $query = $clan->getMembers()
            ->with(['user.userProfile', 'permissions.permission']);

        if (!$includeFormer) {
            $query->andWhere(['IS', 'leave_date', null]);
        }

        $members = $query
            ->orderBy(['leave_date' => SORT_ASC, 'role' => SORT_ASC, 'join_date' => SORT_ASC])
            ->all();

        $statsByMemberId = [];
        if ($clan->server) {
            $wipe = $clan->server->currentWipe();
            if ($wipe !== null && $wipe !== '') {
                $statRows = ClanMemberStatistics::find()
                    ->where([
                        'clan_id' => $clan->id,
                        'server_id' => $clan->server_id,
                        'wipe' => $wipe,
                    ])
                    ->with('statValues')
                    ->indexBy('clan_member_id')
                    ->all();
                $statsByMemberId = $statRows;
            }

            $missingMemberIds = [];
            foreach ($members as $m) {
                if (!isset($statsByMemberId[$m->id])) {
                    $missingMemberIds[] = (int)$m->id;
                }
            }
            if ($missingMemberIds !== []) {
                $candidates = ClanMemberStatistics::find()
                    ->where([
                        'clan_id' => $clan->id,
                        'server_id' => $clan->server_id,
                        'clan_member_id' => $missingMemberIds,
                    ])
                    ->with('statValues')
                    ->all();
                $bestByMember = [];
                foreach ($candidates as $statRow) {
                    $mid = (int)$statRow->clan_member_id;
                    if (!isset($bestByMember[$mid]) || (int)$statRow->updated_at > (int)$bestByMember[$mid]->updated_at) {
                        $bestByMember[$mid] = $statRow;
                    }
                }
                foreach ($bestByMember as $mid => $statRow) {
                    $statsByMemberId[$mid] = $statRow;
                }
            }
        }

        $items = [];
        foreach ($members as $m) {
            $row = $statsByMemberId[$m->id] ?? null;
            $items[] = $this->serializeMember($m, $clan, $viewer, $row);
        }

        if ($includeFormer) {
            $items = $this->mergeClanMemberItemsByUserId($items);
        }

        return $this->successResponse([
            'items' => $items,
            'include_former' => $includeFormer,
            'current_wipe' => $clan->server ? $clan->server->currentWipe() : null,
        ]);
    }

    /**
     * GET /v1/clans/{serverTag}/{id}/statistics/member/{memberId}
     */
    public function actionMemberStatistics($serverTag, $id, $memberId)
    {
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $member = ClanMember::findOne((int)$memberId);
        if (!$member || (int)$member->clan_id !== (int)$clan->id) {
            throw new NotFoundHttpException('Member not found');
        }

        $wipeParam = Yii::$app->request->get('wipe');
        $server = $clan->server;
        $resolvedWipe = ($wipeParam !== null && $wipeParam !== '')
            ? (string)$wipeParam
            : ($server ? $server->currentWipe() : null);

        $stats = $resolvedWipe
            ? ClanMemberStatistics::getMemberStatistics($member->id, $clan->server_id, $resolvedWipe)
            : null;

        $viewer = $this->getActiveMember($clan);

        return $this->successResponse([
            'member' => $this->serializeMember($member, $clan, $viewer, $stats),
            'wipe' => $resolvedWipe,
            'statistics' => $stats ? $this->serializeMemberStatistics($stats) : null,
        ]);
    }

    /**
     * GET /v1/clans/{serverTag}/{id}/history
     */
    public function actionHistory($serverTag, $id)
    {
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $limit = min(100, max(1, (int)Yii::$app->request->get('limit', 50)));

        $events = $clan->getEvents()->limit($limit)->all();
        $items = [];
        foreach ($events as $e) {
            $items[] = $this->serializeEvent($e);
        }

        return $this->successResponse(['items' => $items]);
    }

    /**
     * GET /v1/clans/{serverTag}/{id}/achievements
     */
    public function actionAchievements($serverTag, $id)
    {
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $unlocked = $clan->getAchievements()->all();
        $defaults = ClanAchievement::getDefaultAchievements();

        $unlockedMap = [];
        foreach ($unlocked as $a) {
            $unlockedMap[$a->achievement_key] = [
                'achievement_key' => $a->achievement_key,
                'name' => $a->name,
                'description' => $a->description,
                'icon' => $a->icon,
                'unlocked_at' => (int)$a->unlocked_at,
                'metadata' => $a->metadata,
            ];
        }

        return $this->successResponse([
            'unlocked' => array_values($unlockedMap),
            'definitions' => $defaults,
        ]);
    }

    /**
     * GET /v1/clans/{serverTag}/{id}/invites — исходящие приглашения (только с правом)
     */
    public function actionInvitesList($serverTag, $id)
    {
        $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $member = $this->requireClanMember($clan);
        if (!$member->canInvite()) {
            throw new ForbiddenHttpException('No permission to view invites');
        }

        $invites = ClanInvite::find()
            ->where(['clan_id' => $clan->id, 'status' => ClanInvite::STATUS_PENDING])
            ->with(['invitedUser.userProfile', 'inviterUser.userProfile'])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        $items = [];
        foreach ($invites as $inv) {
            $items[] = $this->serializeInvite($inv);
        }

        return $this->successResponse(['items' => $items]);
    }

    /**
     * POST /v1/clans/{serverTag}/{id}/invite
     */
    public function actionInvite($serverTag, $id)
    {
        $user = $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $member = $this->requireClanMember($clan);
        if (!$member->canInvite()) {
            throw new ForbiddenHttpException('No permission to invite');
        }

        $body = $this->getJsonBody();
        $invitedUserId = isset($body['user_id']) ? (int)$body['user_id'] : 0;
        if (! $invitedUserId) {
            throw new BadRequestHttpException('user_id is required');
        }

        $invitedUser = User::findOne($invitedUserId);
        if (!$invitedUser) {
            throw new BadRequestHttpException('User not found');
        }

        if ($this->hasActiveClanOnServer($invitedUserId, $clan->server_id)) {
            return $this->errorResponse('USER_IN_CLAN', 'User is already in a clan on this server', [], 409);
        }

        $invite = new ClanInvite();
        $invite->clan_id = $clan->id;
        $invite->inviter_user_id = $user->id;
        $invite->invited_user_id = $invitedUserId;

        if (!$invite->save()) {
            return $this->validationErrorResponse($invite);
        }

        $invite->refresh();
        $invite = ClanInvite::find()->where(['id' => $invite->id])->with(['clan.server', 'invitedUser.userProfile', 'inviterUser.userProfile'])->one();

        $clan->addEvent('member_invited', Yii::t('common', 'Пользователь {username} приглашен в клан', ['username' => $invite->invitedUser->username]), $user->id);

        return $this->successResponse($this->serializeInvite($invite), [], 201);
    }

    /**
     * POST /v1/clans/invites/{inviteId}/accept
     */
    public function actionAcceptInvite($inviteId)
    {
        $user = $this->getCurrentUser();
        $invite = ClanInvite::find()
            ->where(['id' => (int)$inviteId])
            ->with(['clan'])
            ->one();
        if (!$invite || (int)$invite->invited_user_id !== (int)$user->id) {
            throw new NotFoundHttpException('Invite not found');
        }

        if ($this->hasActiveClanOnServer($user->id, $invite->clan->server_id)) {
            return $this->errorResponse('ALREADY_IN_CLAN', 'You already have an active clan on this server', [], 409);
        }

        if (!$invite->accept()) {
            return $this->errorResponse('INVITE_FAILED', 'Could not accept invite', [], 400);
        }

        return $this->successResponse(['accepted' => true, 'clan_id' => (int)$invite->clan_id]);
    }

    /**
     * POST /v1/clans/invites/{inviteId}/decline
     */
    public function actionDeclineInvite($inviteId)
    {
        $user = $this->getCurrentUser();
        $invite = ClanInvite::findOne((int)$inviteId);
        if (!$invite || (int)$invite->invited_user_id !== (int)$user->id) {
            throw new NotFoundHttpException('Invite not found');
        }

        if (!$invite->decline()) {
            return $this->errorResponse('INVITE_FAILED', 'Could not decline invite', [], 400);
        }

        return $this->successResponse(['declined' => true]);
    }

    /**
     * POST /v1/clans/{serverTag}/{id}/leave
     */
    public function actionLeave($serverTag, $id)
    {
        $user = $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $member = $this->requireClanMember($clan);
        if ($member->isLeader()) {
            return $this->errorResponse('LEADER_CANNOT_LEAVE', 'Transfer leadership before leaving', [], 400);
        }

        if (!$clan->removeMember($user->id)) {
            return $this->errorResponse('LEAVE_FAILED', 'Could not leave clan', [], 400);
        }

        return $this->successResponse(['left' => true]);
    }

    /**
     * POST /v1/clans/{serverTag}/{id}/kick
     */
    public function actionKick($serverTag, $id)
    {
        $user = $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $member = $this->requireClanMember($clan);
        if (!$member->canKick()) {
            throw new ForbiddenHttpException('No permission to kick members');
        }

        $body = $this->getJsonBody();
        $targetUserId = isset($body['user_id']) ? (int)$body['user_id'] : 0;
        if (! $targetUserId) {
            throw new BadRequestHttpException('user_id is required');
        }

        $targetMember = ClanMember::find()
            ->where(['clan_id' => $clan->id, 'user_id' => $targetUserId])
            ->andWhere(['IS', 'leave_date', null])
            ->one();

        if (!$targetMember) {
            throw new NotFoundHttpException('Member not found');
        }
        if ($targetMember->isLeader()) {
            return $this->errorResponse('CANNOT_KICK_LEADER', 'Cannot kick the leader', [], 400);
        }

        if (!$clan->removeMember($targetUserId)) {
            return $this->errorResponse('KICK_FAILED', 'Kick failed', [], 400);
        }

        $clan->addEvent('member_kicked', Yii::t('common', 'Пользователь {username} исключен из клана', ['username' => $targetMember->user->username]), $user->id);

        return $this->successResponse(['kicked' => true]);
    }

    /**
     * POST /v1/clans/{serverTag}/{id}/promote
     */
    public function actionPromote($serverTag, $id)
    {
        return $this->errorResponse(
            'OFFICER_ROLE_REMOVED',
            'Officer role is removed. Manage member capabilities using permission keys.',
            [],
            410
        );
    }

    /**
     * POST /v1/clans/{serverTag}/{id}/demote
     */
    public function actionDemote($serverTag, $id)
    {
        return $this->errorResponse(
            'OFFICER_ROLE_REMOVED',
            'Officer role is removed. Manage member capabilities using permission keys.',
            [],
            410
        );
    }

    /**
     * POST /v1/clans/{serverTag}/{id}/transfer-leadership
     */
    public function actionTransferLeadership($serverTag, $id)
    {
        $user = $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        if ((int)$clan->leader_user_id !== (int)$user->id) {
            throw new ForbiddenHttpException('Only the leader can transfer leadership');
        }

        $body = $this->getJsonBody();
        $newLeaderId = isset($body['user_id']) ? (int)$body['user_id'] : 0;
        if (! $newLeaderId) {
            throw new BadRequestHttpException('user_id is required');
        }

        if (!$clan->transferLeadership($newLeaderId)) {
            return $this->errorResponse('TRANSFER_FAILED', 'Could not transfer leadership', [], 400);
        }

        return $this->successResponse(['transferred' => true]);
    }

    /**
     * GET /v1/clans/{serverTag}/{id}/members/{memberId}/permissions — текущие права
     * POST /v1/clans/{serverTag}/{id}/members/{memberId}/permissions — обновить (permission_keys)
     */
    public function actionMemberPermissions($serverTag, $id, $memberId)
    {
        $user = $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $actor = $this->requireClanMember($clan);

        $targetMember = ClanMember::findOne((int)$memberId);
        if (!$targetMember || (int)$targetMember->clan_id !== (int)$clan->id) {
            throw new NotFoundHttpException('Member not found');
        }

        if (Yii::$app->request->isGet) {
            $canView = $actor->isLeader()
                || $actor->canManagePermissions()
                || (int)$actor->user_id === (int)$targetMember->user_id;
            if (!$canView) {
                throw new ForbiddenHttpException('No permission to view member permissions');
            }

            $targetMember = ClanMember::find()
                ->where(['id' => $targetMember->id])
                ->with(['user.userProfile', 'permissions.permission'])
                ->one();

            $wipeStat = null;
            if ($clan->server) {
                $w = $clan->server->currentWipe();
                if ($w) {
                    $wipeStat = ClanMemberStatistics::find()
                        ->where([
                            'clan_member_id' => $targetMember->id,
                            'server_id' => $clan->server_id,
                            'wipe' => $w,
                        ])
                        ->with('statValues')
                        ->one();
                }
            }

            return $this->successResponse([
                'member' => $this->serializeMember($targetMember, $clan, $actor, $wipeStat),
            ]);
        }

        if (!$actor->isLeader() && !$actor->canManagePermissions()) {
            throw new ForbiddenHttpException('No permission to manage permissions');
        }

        $body = $this->getJsonBody();
        $keys = isset($body['permission_keys']) && is_array($body['permission_keys']) ? $body['permission_keys'] : [];

        if (!$targetMember->syncPermissions($keys)) {
            return $this->errorResponse(
                'PERMISSIONS_SYNC_FAILED',
                'Could not update permissions (leader row, invalid keys, or DB error — check clan_permissions / migrations)',
                [],
                400
            );
        }

        // Плагин Rust сравнивает clan.update_at — без bump не подхватит auth_* для игры
        $clan->updateAttributes(['updated_at' => time()]);

        $clan->addEvent('permissions_updated', Yii::t('common', 'Разрешения участника обновлены'), $user->id);

        $targetMember->refresh();
        $targetMember = ClanMember::find()->where(['id' => $targetMember->id])->with(['user.userProfile', 'permissions.permission'])->one();

        return $this->successResponse($this->serializeMember($targetMember, $clan, $actor));
    }

    /**
     * GET /v1/clans/{serverTag}/{id}/members/{memberId}/trust-review — траст и проверки участника (как в заявках).
     */
    public function actionMemberTrustReview($serverTag, $id, $memberId)
    {
        $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $actor = $this->requireClanMember($clan);

        if (!$actor->canKick()) {
            throw new ForbiddenHttpException('No permission to view member trust review');
        }

        $targetMember = ClanMember::find()
            ->where(['id' => (int)$memberId])
            ->with(['user.userProfile'])
            ->one();

        if (!$targetMember || (int)$targetMember->clan_id !== (int)$clan->id) {
            throw new NotFoundHttpException('Member not found');
        }
        if (!$targetMember->isActive()) {
            throw new BadRequestHttpException('Member is not active');
        }
        if ($targetMember->isLeader()) {
            throw new BadRequestHttpException('Trust review is not available for the clan leader');
        }

        $targetUser = $targetMember->user;
        if (!$targetUser) {
            throw new NotFoundHttpException('User not found');
        }

        $trust = ApplicantTrustHelper::summarize($targetUser, $clan);
        $combat = $this->buildApplicationLifetimeCombat($targetUser, $clan);

        $payload = [
            'user' => $this->serializeUser($targetUser),
            'trust' => $trust,
        ];
        if ($combat !== null) {
            $payload['lifetime_combat'] = $combat;
        }

        return $this->successResponse($payload);
    }

    /**
     * GET /v1/clans/invite-link/{token} — превью клана по ссылке (без JWT).
     */
    public function actionInviteLinkPreview($token)
    {
        $token = preg_replace('/[^a-fA-F0-9]/', '', (string)$token);
        if (strlen($token) < 16) {
            throw new NotFoundHttpException('Invalid invite link');
        }

        $link = ClanInviteLink::find()
            ->where(['token' => $token])
            ->with(['clan.server', 'clan.leaderUser.userProfile', 'inviterUser.userProfile'])
            ->one();
        if (!$link || !$link->clan) {
            throw new NotFoundHttpException('Invite link not found');
        }
        if ($link->isExpired() || $link->isUseLimitReached()) {
            return $this->errorResponse('INVITE_EXPIRED', 'Invite link expired or limit reached', [], 410);
        }

        $clan = $link->clan;
        $extras = $this->inviteLinkPreviewExtras($link, $clan);
        if ($clan->privacy === Clan::PRIVACY_CLOSED) {
            return $this->successResponse(array_merge([
                'valid' => false,
                'reason' => 'closed',
                'message' => 'This clan is closed — submit an application instead.',
                'clan' => $this->serializeClanListItem($clan),
                'server_tag' => $clan->server ? $clan->server->tag : null,
            ], $extras));
        }

        return $this->successResponse(array_merge([
            'valid' => true,
            'token' => $link->token,
            'expires_at' => $link->expires_at,
            'clan' => $this->serializeClanListItem($clan),
            'server_tag' => $clan->server ? $clan->server->tag : null,
        ], $extras));
    }

    /**
     * POST /v1/clans/invite-link/{token}/join — вступить по ссылке (JWT).
     */
    public function actionInviteLinkJoin($token)
    {
        $user = $this->getCurrentUser();
        $token = preg_replace('/[^a-fA-F0-9]/', '', (string)$token);
        $link = ClanInviteLink::find()->where(['token' => $token])->with('clan.server')->one();
        if (!$link || !$link->clan) {
            throw new NotFoundHttpException('Invite link not found');
        }
        if ($link->isExpired() || $link->isUseLimitReached()) {
            return $this->errorResponse('INVITE_EXPIRED', 'Invite link expired or limit reached', [], 410);
        }

        $clan = $link->clan;
        if ($clan->privacy === Clan::PRIVACY_CLOSED) {
            return $this->errorResponse('CLAN_CLOSED', 'Clan is closed — use application', [], 400);
        }

        if ($this->hasActiveClanOnServer($user->id, $clan->server_id)) {
            return $this->errorResponse('ALREADY_IN_CLAN', 'Already in a clan on this server', [], 400);
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            $link->uses_count = (int)$link->uses_count + 1;
            if (!$link->save(false)) {
                throw new \RuntimeException('Could not update link');
            }
            $member = $clan->addMember($user->id, ClanMember::ROLE_MEMBER, [
                'invite_link_id' => (int)$link->id,
                'via' => 'invite_link',
            ]);
            if (!$member) {
                $tx->rollBack();
                return $this->errorResponse('JOIN_FAILED', 'Could not join clan', [], 400);
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error($e->getMessage(), 'clan');

            return $this->errorResponse('JOIN_FAILED', 'Could not join clan', [], 500);
        }

        return $this->successResponse(['joined' => true, 'clan_id' => (int)$clan->id]);
    }

    /**
     * GET /v1/clans/{serverTag}/{id}/invite-links — список ссылек (с правом invite).
     */
    public function actionInviteLinksList($serverTag, $id)
    {
        $user = $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $member = $this->requireClanMember($clan);
        if (!$member->canInvite()) {
            throw new ForbiddenHttpException('No permission');
        }

        $links = ClanInviteLink::find()
            ->where(['clan_id' => $clan->id])
            ->with('inviterUser')
            ->orderBy(['id' => SORT_DESC])
            ->limit(50)
            ->all();

        $linkIds = array_map(static function (ClanInviteLink $l) {
            return (int)$l->id;
        }, $links);
        $joinersByLink = $this->inviteLinkJoinerUserIdsByLinkId((int)$clan->id, $linkIds);
        $allJoinerIds = [];
        foreach ($joinersByLink as $uids) {
            foreach ($uids as $uid) {
                $allJoinerIds[(int)$uid] = true;
            }
        }
        $joinerModels = [];
        if ($allJoinerIds !== []) {
            $joinerModels = User::find()
                ->where(['id' => array_keys($allJoinerIds)])
                ->indexBy('id')
                ->all();
        }

        $items = [];
        foreach ($links as $l) {
            $items[] = $this->serializeInviteLink($l, $joinersByLink[(int)$l->id] ?? [], $joinerModels);
        }

        return $this->successResponse(['items' => $items]);
    }

    /**
     * POST /v1/clans/{serverTag}/{id}/invite-links — создать ссылку.
     */
    public function actionInviteLinksCreate($serverTag, $id)
    {
        $user = $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $member = $this->requireClanMember($clan);
        if (!$member->canInvite()) {
            throw new ForbiddenHttpException('No permission');
        }

        $body = $this->getJsonBody();
        $days = isset($body['expires_in_days']) ? (int)$body['expires_in_days'] : 7;
        $maxUses = isset($body['max_uses']) ? (int)$body['max_uses'] : 0;

        $link = new ClanInviteLink();
        $link->clan_id = $clan->id;
        $link->inviter_user_id = $user->id;
        $link->token = ClanInviteLink::generateToken();
        $link->created_at = time();
        $link->max_uses = max(0, $maxUses);
        $link->uses_count = 0;
        if ($days > 0) {
            $link->expires_at = date('Y-m-d H:i:s', time() + $days * 86400);
        } else {
            $link->expires_at = null;
        }

        if (!$link->save()) {
            return $this->validationErrorResponse($link);
        }

        $link->refresh();
        $link->populateRelation('inviterUser', $user);

        return $this->successResponse($this->serializeInviteLink($link), [], 201);
    }

    /**
     * DELETE /v1/clans/{serverTag}/{id}/invite-links/{linkId}
     */
    public function actionInviteLinksDelete($serverTag, $id, $linkId)
    {
        $user = $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $member = $this->requireClanMember($clan);
        if (!$member->canInvite()) {
            throw new ForbiddenHttpException('No permission');
        }

        $link = ClanInviteLink::findOne(['id' => (int)$linkId, 'clan_id' => $clan->id]);
        if (!$link) {
            throw new NotFoundHttpException('Link not found');
        }
        $link->delete();

        return $this->successResponse(['deleted' => true]);
    }

    /**
     * POST /v1/clans/{serverTag}/{id}/apply — заявка в клан.
     */
    public function actionApply($serverTag, $id)
    {
        $user = $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);

        if ($clan->privacy === Clan::PRIVACY_INVITE_ONLY) {
            return $this->errorResponse('INVITE_ONLY', 'This clan accepts invites only', [], 400);
        }

        if ($this->hasActiveClanOnServer($user->id, $clan->server_id)) {
            return $this->errorResponse('ALREADY_IN_CLAN', 'Already in a clan on this server', [], 400);
        }

        $pending = ClanApplication::find()
            ->where(['clan_id' => $clan->id, 'user_id' => $user->id, 'status' => ClanApplication::STATUS_PENDING])
            ->exists();
        if ($pending) {
            return $this->errorResponse('APPLICATION_PENDING', 'Application already pending', [], 400);
        }

        $body = $this->getJsonBody();
        $message = isset($body['message']) ? (string)$body['message'] : null;

        $app = new ClanApplication();
        $app->clan_id = $clan->id;
        $app->user_id = $user->id;
        $app->message = $message ? mb_substr($message, 0, 2000) : null;
        $app->status = ClanApplication::STATUS_PENDING;
        $app->created_at = time();

        if (!$app->save()) {
            return $this->validationErrorResponse($app);
        }

        $clan->addEvent('application_submitted', Yii::t('common', 'Новая заявка в клан'), $user->id);

        return $this->successResponse($this->serializeApplication($app, false, null), [], 201);
    }

    /**
     * GET /v1/clans/{serverTag}/{id}/applications — заявки (лидер/офицер).
     */
    public function actionApplicationsList($serverTag, $id)
    {
        $user = $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $member = $this->requireClanMember($clan);
        if (!$member->isLeader() && !$member->canPromoteDemote()) {
            throw new ForbiddenHttpException('No permission');
        }

        $apps = ClanApplication::find()
            ->where(['clan_id' => $clan->id, 'status' => ClanApplication::STATUS_PENDING])
            ->with(['user.userProfile'])
            ->orderBy(['id' => SORT_DESC])
            ->limit(100)
            ->all();

        $items = [];
        foreach ($apps as $a) {
            $items[] = $this->serializeApplication($a, true, $clan);
        }

        return $this->successResponse(['items' => $items]);
    }

    /**
     * POST /v1/clans/{serverTag}/{id}/applications/{appId}/accept
     */
    public function actionApplicationAccept($serverTag, $id, $appId)
    {
        $user = $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $member = $this->requireClanMember($clan);
        if (!$member->isLeader() && !$member->canPromoteDemote()) {
            throw new ForbiddenHttpException('No permission');
        }

        $app = ClanApplication::findOne(['id' => (int)$appId, 'clan_id' => $clan->id]);
        if (!$app || $app->status !== ClanApplication::STATUS_PENDING) {
            throw new NotFoundHttpException('Application not found');
        }

        if ($this->hasActiveClanOnServer($app->user_id, $clan->server_id)) {
            $app->status = ClanApplication::STATUS_REJECTED;
            $app->resolved_at = time();
            $app->resolved_by_user_id = $user->id;
            $app->save(false);

            return $this->errorResponse('USER_BUSY', 'User already in a clan', [], 400);
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            $app->status = ClanApplication::STATUS_ACCEPTED;
            $app->resolved_at = time();
            $app->resolved_by_user_id = $user->id;
            $app->save(false);

            $m = $clan->addMember($app->user_id);
            if (!$m) {
                throw new \RuntimeException('addMember failed');
            }
            $clan->addEvent('application_accepted', Yii::t('common', 'Заявка принята'), $user->id);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error($e->getMessage(), 'clan');

            return $this->errorResponse('ACCEPT_FAILED', 'Could not accept', [], 500);
        }

        return $this->successResponse(['accepted' => true]);
    }

    /**
     * POST /v1/clans/{serverTag}/{id}/applications/{appId}/reject
     */
    public function actionApplicationReject($serverTag, $id, $appId)
    {
        $user = $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $member = $this->requireClanMember($clan);
        if (!$member->isLeader() && !$member->canPromoteDemote()) {
            throw new ForbiddenHttpException('No permission');
        }

        $app = ClanApplication::findOne(['id' => (int)$appId, 'clan_id' => $clan->id]);
        if (!$app || $app->status !== ClanApplication::STATUS_PENDING) {
            throw new NotFoundHttpException('Application not found');
        }

        $app->status = ClanApplication::STATUS_REJECTED;
        $app->resolved_at = time();
        $app->resolved_by_user_id = $user->id;
        $app->save(false);

        return $this->successResponse(['rejected' => true]);
    }

    /**
     * GET /v1/clans/{serverTag}/{id}/posts
     */
    public function actionPostsList($serverTag, $id)
    {
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $viewer = $this->getActiveMember($clan);

        $q = ClanPost::find()->where(['clan_id' => $clan->id, 'is_published' => 1]);
        if (!$viewer) {
            $q->andWhere(['visibility' => ClanPost::VIS_PUBLIC]);
        } elseif ($viewer->isLeader()) {
            // лидер видит скрытые посты
        } else {
            $q->andWhere(['in', 'visibility', [ClanPost::VIS_PUBLIC, ClanPost::VIS_MEMBERS]]);
        }

        $posts = $q->with(['author.userProfile'])->orderBy(['published_at' => SORT_DESC])->limit(50)->all();
        $items = [];
        foreach ($posts as $p) {
            $items[] = $this->serializePost($p);
        }

        return $this->successResponse(['items' => $items]);
    }

    /**
     * POST /v1/clans/{serverTag}/{id}/posts
     */
    public function actionPostCreate($serverTag, $id)
    {
        $user = $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $member = $this->requireClanMember($clan);
        if (!$member->canEditClan() && !$member->isLeader()) {
            throw new ForbiddenHttpException('No permission');
        }

        $body = $this->getJsonBody();
        $post = new ClanPost();
        $post->clan_id = $clan->id;
        $post->author_user_id = $user->id;
        $post->type = isset($body['type']) && $body['type'] === ClanPost::TYPE_PAGE ? ClanPost::TYPE_PAGE : ClanPost::TYPE_NEWS;
        $post->visibility = isset($body['visibility']) ? (string)$body['visibility'] : ClanPost::VIS_PUBLIC;
        if (!in_array($post->visibility, [ClanPost::VIS_PUBLIC, ClanPost::VIS_MEMBERS, ClanPost::VIS_HIDDEN], true)) {
            $post->visibility = ClanPost::VIS_PUBLIC;
        }
        $post->title = isset($body['title']) ? mb_substr((string)$body['title'], 0, 255) : '';
        $post->body = isset($body['body']) ? (string)$body['body'] : '';
        $post->is_published = isset($body['is_published']) ? (int)(bool)$body['is_published'] : 1;
        $now = time();
        $post->published_at = $now;
        $post->created_at = $now;
        $post->updated_at = $now;

        if ($post->title === '') {
            return $this->errorResponse('VALIDATION', 'Title required', [], 400);
        }

        if (!$post->save()) {
            return $this->validationErrorResponse($post);
        }

        return $this->successResponse($this->serializePost($post), [], 201);
    }

    /**
     * PATCH /v1/clans/{serverTag}/{id}/posts/{postId}
     */
    public function actionPostUpdate($serverTag, $id, $postId)
    {
        $user = $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $member = $this->requireClanMember($clan);
        if (!$member->canEditClan() && !$member->isLeader()) {
            throw new ForbiddenHttpException('No permission');
        }

        $post = ClanPost::findOne(['id' => (int)$postId, 'clan_id' => $clan->id]);
        if (!$post) {
            throw new NotFoundHttpException('Post not found');
        }

        $body = $this->getJsonBody();
        if (isset($body['title'])) {
            $post->title = mb_substr((string)$body['title'], 0, 255);
        }
        if (array_key_exists('body', $body)) {
            $post->body = (string)$body['body'];
        }
        if (isset($body['visibility'])) {
            $v = (string)$body['visibility'];
            if (in_array($v, [ClanPost::VIS_PUBLIC, ClanPost::VIS_MEMBERS, ClanPost::VIS_HIDDEN], true)) {
                $post->visibility = $v;
            }
        }
        if (isset($body['is_published'])) {
            $post->is_published = (int)(bool)$body['is_published'];
        }
        $post->updated_at = time();

        if (!$post->save()) {
            return $this->validationErrorResponse($post);
        }

        return $this->successResponse($this->serializePost($post));
    }

    /**
     * DELETE /v1/clans/{serverTag}/{id}/posts/{postId}
     */
    public function actionPostDelete($serverTag, $id, $postId)
    {
        $user = $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $member = $this->requireClanMember($clan);
        if (!$member->canEditClan() && !$member->isLeader()) {
            throw new ForbiddenHttpException('No permission');
        }

        $post = ClanPost::findOne(['id' => (int)$postId, 'clan_id' => $clan->id]);
        if (!$post) {
            throw new NotFoundHttpException('Post not found');
        }
        $post->delete();

        return $this->successResponse(['deleted' => true]);
    }

    /**
     * POST /v1/clans/{serverTag}/{id}/logo — multipart logo → S3.
     */
    public function actionUploadLogo($serverTag, $id)
    {
        $user = $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $member = $this->requireClanMember($clan);
        if (!$member->canEditClan() && !$member->isLeader()) {
            throw new ForbiddenHttpException('No permission');
        }

        if (!Yii::$app->has('s3Api')) {
            return $this->errorResponse('S3_UNAVAILABLE', 'Storage not configured', [], 503);
        }

        $file = UploadedFile::getInstanceByName('file');
        if ($file === null) {
            return $this->errorResponse('NO_FILE', 'File required (field name: file)', [], 400);
        }
        if ($file->hasError) {
            $phpErr = (int)$file->error;
            if ($phpErr === UPLOAD_ERR_INI_SIZE || $phpErr === UPLOAD_ERR_FORM_SIZE) {
                return $this->errorResponse(
                    'FILE_TOO_LARGE',
                    'File exceeds PHP upload limits (upload_max_filesize / post_max_size). Max image size after processing is 5MB.',
                    ['php_upload_error' => $phpErr],
                    400
                );
            }
            if ($phpErr === UPLOAD_ERR_PARTIAL) {
                return $this->errorResponse('UPLOAD_ERROR', 'File partially uploaded', ['php_upload_error' => $phpErr], 400);
            }

            return $this->errorResponse(
                'UPLOAD_ERROR',
                'Upload failed (PHP code ' . $phpErr . ')',
                ['php_upload_error' => $phpErr],
                400
            );
        }
        if ($file->tempName === '' || $file->tempName === null) {
            return $this->errorResponse('NO_FILE', 'File required (field name: file)', [], 400);
        }

        $ext = strtolower($file->extension ?: 'png');
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            return $this->errorResponse('BAD_TYPE', 'Allowed: png, jpg, webp, gif', [], 400);
        }

        $content = file_get_contents($file->tempName);
        if ($content === false || strlen($content) > 5 * 1024 * 1024) {
            return $this->errorResponse('TOO_LARGE', 'Max 5MB', [], 400);
        }

        try {
            $img = Image::getImagine()->load($content);
            $size = $img->getSize();
            if ($size->getWidth() > 512 || $size->getHeight() > 512) {
                $img = Image::thumbnail($img, 512, 512);
            }
            $pngData = $img->get('png');
        } catch (\Throwable $e) {
            return $this->errorResponse('IMAGE_ERROR', 'Invalid image', [], 400);
        }

        $key = 'uploads/clans/logo_' . $clan->id . '_' . bin2hex(random_bytes(6)) . '.png';
        if (Yii::$app->s3Api->putFile($key, $pngData, 'image/png') === false) {
            return $this->errorResponse('UPLOAD_FAILED', 'S3 upload failed', [], 500);
        }

        $clan->logo = $key;
        if (!$clan->save(false)) {
            return $this->errorResponse('SAVE_FAILED', 'Could not save clan', [], 500);
        }

        return $this->successResponse([
            'logo_url' => $clan->getLogoUrl(),
        ]);
    }

    /**
     * user_id из событий member_joined с metadata.invite_link_id, сгруппированные по id ссылки.
     *
     * @param int[] $linkIds
     * @return array<int, int[]>
     */
    protected function inviteLinkJoinerUserIdsByLinkId(int $clanId, array $linkIds): array
    {
        $linkIds = array_values(array_unique(array_map('intval', $linkIds)));
        $out = [];
        foreach ($linkIds as $id) {
            $out[$id] = [];
        }
        if ($linkIds === []) {
            return $out;
        }
        $set = array_flip($linkIds);

        $rows = ClanEvent::find()
            ->select(['user_id', 'metadata'])
            ->where(['clan_id' => $clanId, 'event_type' => ClanEvent::EVENT_MEMBER_JOINED])
            ->andWhere(['not', ['metadata' => null]])
            ->andWhere(['<>', 'metadata', ''])
            ->asArray()
            ->all();

        foreach ($rows as $row) {
            $meta = json_decode((string)$row['metadata'], true);
            if (!is_array($meta) || empty($meta['invite_link_id'])) {
                continue;
            }
            $lid = (int)$meta['invite_link_id'];
            if (!isset($set[$lid])) {
                continue;
            }
            $uid = (int)$row['user_id'];
            if ($uid > 0) {
                $out[$lid][] = $uid;
            }
        }

        foreach ($linkIds as $lid) {
            $out[$lid] = array_values(array_unique($out[$lid]));
        }

        return $out;
    }

    /**
     * @param array<int, User> $usersById предзагруженные пользователи по id
     * @param int[] $joinerUserIds
     */
    protected function serializeInviteLink(ClanInviteLink $l, array $joinerUserIds = [], array $usersById = []): array
    {
        $joined = [];
        foreach ($joinerUserIds as $uid) {
            $uid = (int)$uid;
            if ($uid > 0 && !empty($usersById[$uid])) {
                $joined[] = $this->serializeUser($usersById[$uid]);
            }
        }

        return [
            'id' => (int)$l->id,
            'token' => $l->token,
            'expires_at' => $l->expires_at,
            'max_uses' => (int)$l->max_uses,
            'uses_count' => (int)$l->uses_count,
            'created_at' => (int)$l->created_at,
            'created_by' => $l->inviterUser ? $this->serializeUser($l->inviterUser) : null,
            'joined_users' => $joined,
        ];
    }

    protected function serializeApplication(ClanApplication $a, bool $includeReviewerTrust, ?Clan $clan): array
    {
        $data = [
            'id' => (int)$a->id,
            'clan_id' => (int)$a->clan_id,
            'user_id' => (int)$a->user_id,
            'message' => $a->message,
            'status' => $a->status,
            'created_at' => (int)$a->created_at,
            'resolved_at' => $a->resolved_at !== null ? (int)$a->resolved_at : null,
            'user' => $a->user ? $this->serializeUser($a->user) : null,
        ];

        if ($includeReviewerTrust && $clan !== null && $a->user) {
            $data['trust'] = ApplicantTrustHelper::summarize($a->user, $clan);
            $combat = $this->buildApplicationLifetimeCombat($a->user, $clan);
            if ($combat !== null) {
                $data['lifetime_combat'] = $combat;
            }
        }

        return $data;
    }

    /**
     * Боевая статистика заявителя на сервере клана за всё время (все вайпы), те же поля что metrics боя в профиле.
     *
     * @return array<string, int|float>|null
     */
    protected function buildApplicationLifetimeCombat(User $user, Clan $clan): ?array
    {
        $steamId = trim((string) $user->steam_id);
        if ($steamId === '') {
            return null;
        }
        $server = $clan->server;
        if ($server === null || $server->tag === null || $server->tag === '') {
            return null;
        }

        $agg = Statistics::getPlayerStatsAllTimeForServerTag($steamId, (string) $server->tag);
        $kills = (int) Statistics::getParam($agg, 'kills');
        $deaths = (int) Statistics::getParam($agg, 'deaths');
        $kd = $deaths > 0 ? round($kills / $deaths, 2) : $kills;

        return [
            'kills' => $kills,
            'deaths' => $deaths,
            'kd' => $kd,
            'nude_kills' => (int) Statistics::getParam($agg, 'nude_kills'),
            'wounded' => (int) Statistics::getParam($agg, 'wounded'),
            'scientists' => (int) Statistics::getParam($agg, 'scientists'),
            'tcs_destroyed' => (int) Statistics::getParam($agg, 'tcsdestroyed'),
        ];
    }

    protected function serializePost(ClanPost $p): array
    {
        return [
            'id' => (int)$p->id,
            'clan_id' => (int)$p->clan_id,
            'type' => $p->type,
            'visibility' => $p->visibility,
            'title' => $p->title,
            'body' => $p->body,
            'is_published' => (int)$p->is_published,
            'published_at' => (int)$p->published_at,
            'created_at' => (int)$p->created_at,
            'updated_at' => (int)$p->updated_at,
            'author' => $p->author ? $this->serializeUser($p->author) : null,
        ];
    }

    // --- helpers ---

    protected function getJsonBody(): array
    {
        $raw = Yii::$app->request->getBodyParams();
        return is_array($raw) ? $raw : [];
    }

    protected function findClanByServerTag(string $serverTag, int $id): Clan
    {
        $server = Servers::find()
            ->where('LOWER(tag) = :tag', [':tag' => mb_strtolower(trim($serverTag), 'UTF-8')])
            ->one();
        if (!$server) {
            throw new NotFoundHttpException('Server not found');
        }

        $clan = Clan::find()
            ->where(['id' => $id, 'server_id' => $server->id])
            ->with(['leaderUser.userProfile', 'server'])
            ->one();

        if (!$clan) {
            throw new NotFoundHttpException('Clan not found');
        }

        return $clan;
    }

    protected function getActiveMember(Clan $clan): ?ClanMember
    {
        if (Yii::$app->user->isGuest) {
            return null;
        }

        return ClanMember::find()
            ->where(['clan_id' => $clan->id, 'user_id' => Yii::$app->user->id])
            ->andWhere(['IS', 'leave_date', null])
            ->one();
    }

    protected function requireClanMember(Clan $clan): ClanMember
    {
        if (Yii::$app->user->isGuest) {
            throw new UnauthorizedHttpException('Authentication required');
        }

        $m = $this->getActiveMember($clan);
        if (!$m) {
            throw new ForbiddenHttpException('You are not a member of this clan');
        }

        return $m;
    }

    protected function hasActiveClanOnServer(int $userId, int $serverId): bool
    {
        return ClanMember::find()
            ->alias('m')
            ->innerJoin(['c' => Clan::tableName()], 'c.id = m.clan_id')
            ->where(['m.user_id' => $userId, 'c.server_id' => $serverId])
            ->andWhere(['IS', 'm.leave_date', null])
            ->exists();
    }

    protected function serializeUser(User $user): array
    {
        $countryCode = $user->getCountryByIp();
        $displayStatus = $user->getDisplayStatus();
        $lastVisitAt = null;
        if (!empty($user->last_visit_server_at)) {
            $ts = strtotime($user->last_visit_server_at);
            $lastVisitAt = $ts !== false ? (int)$ts : null;
        }

        return [
            'id' => (int)$user->id,
            'username' => $user->username,
            'steam_id' => $user->steam_id,
            'avatar' => $user->getAvatar(),
            'country_code' => $countryCode ? strtoupper($countryCode) : null,
            'has_vip' => $user->hasVip(),
            'avatar_frame_url' => $user->getAvatarFrameImageUrl(),
            'status' => $displayStatus === null ? null : (bool)$displayStatus,
            'is_hidden' => $displayStatus === null,
            /** Unix time — последний заход на сервер (для активности за неделю в UI кланов и т.п.) */
            'last_visit_server_at' => $lastVisitAt,
        ];
    }

    /** ЧПУ-сегмент URL: как в публичном фронте /clans/{slug} */
    protected function getClanUrlSlug(Clan $clan): string
    {
        $slugBase = Inflector::slug((string)$clan->name);
        if ($slugBase === '') {
            $slugBase = 'clan';
        }

        return $slugBase . '-' . (int)$clan->id;
    }

    protected function serializeClanListItem(Clan $clan): array
    {
        $memberCount = (int)ClanMember::find()
            ->where(['clan_id' => $clan->id])
            ->andWhere(['IS', 'leave_date', null])
            ->count();

        return [
            'id' => (int)$clan->id,
            'name' => $clan->name,
            'tag' => $clan->tag,
            'color_tag' => $clan->color_tag ?? Clan::DEFAULT_TAG_COLOR,
            'slug' => $this->getClanUrlSlug($clan),
            'server_id' => (int)$clan->server_id,
            'server_tag' => $clan->server ? $clan->server->tag : null,
            'leader' => $clan->leaderUser ? $this->serializeUser($clan->leaderUser) : null,
            'motto' => $clan->motto,
            'privacy' => $clan->privacy,
            'level' => (int)$clan->level,
            'experience' => (int)$clan->experience,
            'logo_url' => $clan->getLogoUrl(),
            'created_at' => (int)$clan->created_at,
            'member_count' => $memberCount,
        ];
    }

    protected function serializeClanDetail(Clan $clan, ?ClanMember $currentMember): array
    {
        $data = $this->serializeClanListItem($clan);
        $data['description'] = $clan->description;
        $data['updated_at'] = (int)$clan->updated_at;

        $data['viewer'] = [
            'is_member' => $currentMember !== null,
            'role' => $currentMember ? $currentMember->role : null,
            'permission_keys' => $currentMember ? $currentMember->getPermissionKeys() : [],
        ];

        return $data;
    }

    /**
     * Аватары участников для публичного превью ссылки-приглашения (лидер и офицеры первыми).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function serializeInviteLinkMemberPreview(Clan $clan, int $limit = 12): array
    {
        $limit = max(1, min(20, $limit));
        $members = ClanMember::find()
            ->where(['clan_id' => $clan->id])
            ->andWhere(['IS', 'leave_date', null])
            ->with(['user.userProfile'])
            ->limit($limit + 8)
            ->all();

        usort($members, static function (ClanMember $a, ClanMember $b): int {
            $order = [ClanMember::ROLE_LEADER => 0, ClanMember::ROLE_OFFICER => 1, ClanMember::ROLE_MEMBER => 2];
            $ra = $order[$a->role] ?? 3;
            $rb = $order[$b->role] ?? 3;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return $a->id <=> $b->id;
        });

        $members = array_slice($members, 0, $limit);
        $out = [];
        foreach ($members as $m) {
            if ($m->user) {
                $out[] = $this->serializeUser($m->user);
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    protected function inviteLinkPreviewExtras(ClanInviteLink $link, Clan $clan): array
    {
        return [
            'inviter' => $link->inviterUser ? $this->serializeUser($link->inviterUser) : null,
            'member_preview' => $this->serializeInviteLinkMemberPreview($clan),
        ];
    }

    /**
     * В UI один пользователь = одна строка: при нескольких записях clan_members (вышел и снова вступил)
     * объединяем по user_id. Технически id остаётся от активного членства (кик/права); вклад за вайп — сумма периодов.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    protected function mergeClanMemberItemsByUserId(array $items): array
    {
        $groups = [];
        $withoutUserId = [];
        foreach ($items as $item) {
            $uid = (int)($item['user_id'] ?? 0);
            if ($uid <= 0) {
                $withoutUserId[] = $item;
                continue;
            }
            $groups[$uid][] = $item;
        }

        $out = [];
        foreach ($groups as $uid => $group) {
            if (count($group) === 1) {
                $out[] = $group[0];
                continue;
            }

            usort($group, static function (array $a, array $b): int {
                $ja = strtotime((string)($a['join_date'] ?? '')) ?: 0;
                $jb = strtotime((string)($b['join_date'] ?? '')) ?: 0;

                return $ja <=> $jb;
            });

            $activeRow = null;
            foreach ($group as $row) {
                if (!empty($row['is_active'])) {
                    $activeRow = $row;
                    break;
                }
            }

            $lastSpell = $group[count($group) - 1];
            $primary = $activeRow ?? $lastSpell;

            $firstJoin = $group[0]['join_date'] ?? null;
            $lastLeave = null;
            $lastLeaveTs = 0;
            foreach ($group as $row) {
                if (empty($row['leave_date'])) {
                    continue;
                }
                $t = strtotime((string)$row['leave_date']);
                if ($t !== false && $t >= $lastLeaveTs) {
                    $lastLeaveTs = $t;
                    $lastLeave = $row['leave_date'];
                }
            }

            $statsParts = [];
            foreach ($group as $row) {
                if (!empty($row['wipe_statistics']) && is_array($row['wipe_statistics'])) {
                    $statsParts[] = $row['wipe_statistics'];
                }
            }
            $mergedStats = $this->mergeSerializedMemberStatistics($statsParts);

            $merged = $primary;
            $merged['id'] = (int)$primary['id'];
            $merged['user_id'] = $uid;
            $merged['join_date'] = $firstJoin;
            $merged['leave_date'] = $activeRow ? null : $lastLeave;
            $merged['is_active'] = $activeRow !== null;
            $merged['role'] = $primary['role'];
            $merged['permission_keys'] = $primary['permission_keys'] ?? [];
            $merged['user'] = $primary['user'] ?? null;

            if ($mergedStats !== null) {
                if ($activeRow !== null) {
                    $mergedStats['member_status'] = ClanMemberStatistics::STATUS_ACTIVE;
                    $mergedStats['frozen_at'] = null;
                } else {
                    $mergedStats['member_status'] = ClanMemberStatistics::STATUS_FORMER;
                    if (!isset($mergedStats['frozen_at'])) {
                        $maxFrozen = 0;
                        foreach ($statsParts as $p) {
                            if (isset($p['frozen_at']) && (int)$p['frozen_at'] > $maxFrozen) {
                                $maxFrozen = (int)$p['frozen_at'];
                            }
                        }
                        $mergedStats['frozen_at'] = $maxFrozen > 0 ? $maxFrozen : null;
                    }
                }
                $merged['wipe_statistics'] = $mergedStats;
            } else {
                unset($merged['wipe_statistics']);
            }

            $merged['membership_periods'] = count($group);
            $out[] = $merged;
        }

        usort($out, static function (array $a, array $b): int {
            // Officer rank is deprecated; sort it as a regular member.
            $order = ['leader' => 0, 'member' => 1, 'officer' => 1];
            $ra = $order[$a['role'] ?? ''] ?? 9;
            $rb = $order[$b['role'] ?? ''] ?? 9;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }
            $aa = !empty($a['is_active']);
            $ab = !empty($b['is_active']);
            if ($aa !== $ab) {
                return $aa ? -1 : 1;
            }
            $ja = strtotime((string)($a['join_date'] ?? '')) ?: 0;
            $jb = strtotime((string)($b['join_date'] ?? '')) ?: 0;

            return $ja <=> $jb;
        });

        return array_merge($out, $withoutUserId);
    }

    /**
     * Суммируем счётчики вклада за вайп по нескольким clan_member_id одного user_id; top_* и level/exp — по max.
     *
     * @param array<int, array<string, mixed>> $parts
     * @return array<string, mixed>|null
     */
    protected function mergeSerializedMemberStatistics(array $parts): ?array
    {
        $parts = array_values(array_filter($parts));
        if ($parts === []) {
            return null;
        }
        if (count($parts) === 1) {
            return $parts[0];
        }

        $skip = [
            'id', 'clan_member_id', 'clan_id', 'user_id', 'server_id', 'wipe',
            'created_at', 'updated_at', 'member_status', 'frozen_at',
        ];
        $maxOnlyKeys = ['level', 'experience'];

        $merged = $parts[0];
        for ($i = 1, $n = count($parts); $i < $n; $i++) {
            $s = $parts[$i];
            foreach ($s as $k => $v) {
                if (in_array($k, $skip, true)) {
                    continue;
                }
                if ($v === null || $v === '') {
                    continue;
                }
                if (!is_numeric($v)) {
                    continue;
                }
                $prev = $merged[$k] ?? 0;
                if (strpos((string)$k, 'top_') === 0) {
                    $merged[$k] = max((float)$prev, (float)$v);
                } elseif (in_array($k, $maxOnlyKeys, true)) {
                    $merged[$k] = max((int)$prev, (int)$v);
                } else {
                    $merged[$k] = (int)$prev + (int)$v;
                }
            }
        }

        return $merged;
    }

    protected function serializeMember(ClanMember $member, Clan $clan, ?ClanMember $viewer, ?ClanMemberStatistics $wipeStatistics = null): array
    {
        $user = $member->user;
        $out = [
            'id' => (int)$member->id,
            'user_id' => (int)$member->user_id,
            'role' => $member->role,
            'join_date' => $member->join_date,
            'leave_date' => $member->leave_date,
            'is_active' => $member->isActive(),
            'user' => $user ? $this->serializeUser($user) : null,
        ];

        $showPermissions = false;
        if ($viewer) {
            if ($viewer->isLeader() || $viewer->canManagePermissions()) {
                $showPermissions = true;
            } elseif ((int)$viewer->user_id === (int)$member->user_id) {
                $showPermissions = true;
            }
        }

        $out['permission_keys'] = $showPermissions ? $member->getPermissionKeys() : [];

        if ($wipeStatistics !== null) {
            $out['wipe_statistics'] = $this->serializeMemberStatistics($wipeStatistics);
        }

        return $out;
    }

    /**
     * Статистика участника за вайп (в т.ч. member_status / frozen_at для бывших).
     */
    protected function serializeMemberStatistics(ClanMemberStatistics $row): array
    {
        $data = $row->getStatisticsForApi();
        $data['member_status'] = $row->member_status;
        $data['frozen_at'] = $row->frozen_at !== null ? (int)$row->frozen_at : null;

        return $data;
    }

    protected function serializeEvent(ClanEvent $e): array
    {
        return [
            'id' => (int)$e->id,
            'event_type' => $e->event_type,
            'description' => $e->description,
            'metadata' => $e->metadata,
            'user_id' => $e->user_id !== null ? (int)$e->user_id : null,
            'created_at' => (int)$e->created_at,
        ];
    }

    protected function serializeInvite(ClanInvite $inv): array
    {
        return [
            'id' => (int)$inv->id,
            'clan_id' => (int)$inv->clan_id,
            'status' => $inv->status,
            'expires_at' => $inv->expires_at,
            'created_at' => (int)$inv->created_at,
            'clan' => $inv->clan ? $this->serializeClanListItem($inv->clan) : null,
            'inviter' => $inv->inviterUser ? $this->serializeUser($inv->inviterUser) : null,
            'invited' => $inv->invitedUser ? $this->serializeUser($inv->invitedUser) : null,
        ];
    }
}
