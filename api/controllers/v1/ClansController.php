<?php

namespace api\controllers\v1;

use api\components\jwt\JwtAuthFilter;
use common\models\clan\Clan;
use common\models\clan\ClanAchievement;
use common\models\clan\ClanEvent;
use common\models\clan\ClanInvite;
use common\models\clan\ClanMember;
use common\models\clan\ClanMemberStatistics;
use common\models\clan\ClanPermission;
use common\models\clan\ClanRanking;
use common\models\servers\Servers;
use common\models\user\User;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;

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
        $privacy = isset($body['privacy']) ? (string)$body['privacy'] : Clan::PRIVACY_INVITE_ONLY;

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
        $clan->privacy = $privacy;
        $clan->description = isset($body['description']) ? (string)$body['description'] : null;

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

        return $this->successResponse([
            'wipe' => $resolvedWipe,
            'statistics' => $stats ? $stats->getAttributes() : null,
        ]);
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
                    ->indexBy('clan_member_id')
                    ->all();
                $statsByMemberId = $statRows;
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
        $user = $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $member = $this->requireClanMember($clan);
        if (!$member->canPromoteDemote()) {
            throw new ForbiddenHttpException('No permission to promote members');
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

        if (!$targetMember || $targetMember->role === ClanMember::ROLE_OFFICER) {
            throw new NotFoundHttpException('Member not found or already officer');
        }

        $targetMember->role = ClanMember::ROLE_OFFICER;
        if ($targetMember->save()) {
            $clan->addEvent('member_promoted', Yii::t('common', 'Пользователь {username} повышен до офицера', ['username' => $targetMember->user->username]), $user->id);
        }

        return $this->successResponse(['promoted' => true]);
    }

    /**
     * POST /v1/clans/{serverTag}/{id}/demote
     */
    public function actionDemote($serverTag, $id)
    {
        $user = $this->getCurrentUser();
        $clan = $this->findClanByServerTag($serverTag, (int)$id);
        $member = $this->requireClanMember($clan);
        if (!$member->canPromoteDemote()) {
            throw new ForbiddenHttpException('No permission to demote members');
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

        if (!$targetMember || $targetMember->role === ClanMember::ROLE_MEMBER) {
            throw new NotFoundHttpException('Member not found or already member');
        }
        if ($targetMember->isLeader()) {
            return $this->errorResponse('CANNOT_DEMOTE_LEADER', 'Cannot demote the leader', [], 400);
        }

        $targetMember->role = ClanMember::ROLE_MEMBER;
        if ($targetMember->save()) {
            $clan->addEvent('member_demoted', Yii::t('common', 'Пользователь {username} понижен до участника', ['username' => $targetMember->user->username]), $user->id);
        }

        return $this->successResponse(['demoted' => true]);
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
                        ->one();
                }
            }

            return $this->successResponse([
                'member' => $this->serializeMember($targetMember, $clan, $actor, $wipeStat),
            ]);
        }

        if (!$actor->canManagePermissions()) {
            throw new ForbiddenHttpException('No permission to manage permissions');
        }

        $body = $this->getJsonBody();
        $keys = isset($body['permission_keys']) && is_array($body['permission_keys']) ? $body['permission_keys'] : [];

        if (!$targetMember->syncPermissions($keys)) {
            return $this->errorResponse('PERMISSIONS_SYNC_FAILED', 'Could not update permissions (leader permissions are fixed)', [], 400);
        }

        $clan->addEvent('permissions_updated', Yii::t('common', 'Разрешения участника обновлены'), $user->id);

        $targetMember->refresh();
        $targetMember = ClanMember::find()->where(['id' => $targetMember->id])->with(['user.userProfile', 'permissions.permission'])->one();

        return $this->successResponse($this->serializeMember($targetMember, $clan, $actor));
    }

    // --- helpers ---

    protected function getJsonBody(): array
    {
        $raw = Yii::$app->request->getBodyParams();
        return is_array($raw) ? $raw : [];
    }

    protected function findClanByServerTag(string $serverTag, int $id): Clan
    {
        $server = Servers::findOne(['tag' => $serverTag]);
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
        return [
            'id' => (int)$user->id,
            'username' => $user->username,
            'steam_id' => $user->steam_id,
            'avatar' => $user->getAvatar(),
        ];
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
            $order = ['leader' => 0, 'officer' => 1, 'member' => 2];
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
        $data = $row->getAttributes();
        if ($row->hasAttribute('member_status')) {
            $data['member_status'] = $row->member_status;
        }
        if ($row->hasAttribute('frozen_at')) {
            $data['frozen_at'] = $row->frozen_at !== null ? (int)$row->frozen_at : null;
        }

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
