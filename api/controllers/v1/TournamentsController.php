<?php

namespace api\controllers\v1;

use api\components\jwt\JwtAuthFilter;
use common\components\tournament\TournamentRankingCalculator;
use common\components\tournament\TournamentRegistrationService;
use common\models\clan\Clan;
use common\models\clan\ClanMember;
use common\models\servers\Servers;
use common\models\tournament\Tournament;
use common\models\tournament\TournamentParticipant;
use common\models\tournament\TournamentRanking;
use common\models\tournament\TournamentRegistration;
use common\models\tournament\TournamentReward;
use common\models\user\User;
use common\models\user\UserRaid;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * API турниров кланов.
 */
class TournamentsController extends BaseApiController
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
     * GET /v1/tournaments
     */
    public function actionIndex()
    {
        $request = Yii::$app->request;
        $phase = trim((string)$request->get('phase', ''));
        $q = trim((string)$request->get('q', ''));
        $sort = trim((string)$request->get('sort', 'newest'));
        $page = max(1, (int)$request->get('page', 1));
        $pageSize = min(50, max(1, (int)$request->get('pageSize', 20)));

        $query = Tournament::find()
            ->alias('t')
            ->where(['t.status' => Tournament::STATUS_PUBLISHED])
            ->with(['server', 'rewards']);

        if ($q !== '') {
            $query->andWhere([
                'or',
                ['like', 't.title', $q],
                ['like', 't.slug', $q],
            ]);
        }

        $allPublished = Tournament::find()
            ->where(['status' => Tournament::STATUS_PUBLISHED])
            ->all();
        $stats = [
            'total' => count($allPublished),
            'active' => 0,
            'upcoming' => 0,
            'registration_open' => 0,
            'past' => 0,
        ];
        foreach ($allPublished as $t) {
            $p = $t->getPublicPhase();
            if ($p === Tournament::PHASE_ACTIVE) {
                $stats['active']++;
            } elseif ($p === Tournament::PHASE_UPCOMING) {
                $stats['upcoming']++;
            } else {
                $stats['past']++;
            }
            if ($p !== Tournament::PHASE_PAST && $t->isRegistrationOpen()) {
                $stats['registration_open']++;
            }
        }

        if ($phase !== '' && in_array($phase, [Tournament::PHASE_ACTIVE, Tournament::PHASE_UPCOMING, Tournament::PHASE_PAST], true)) {
            $ids = [];
            foreach ($allPublished as $t) {
                if ($t->getPublicPhase() === $phase) {
                    $ids[] = (int)$t->id;
                }
            }
            if ($ids === []) {
                $query->andWhere('0=1');
            } else {
                $query->andWhere(['t.id' => $ids]);
            }
        }

        $order = ['t.sort' => SORT_DESC, 't.starts_at' => SORT_DESC];
        if ($sort === 'oldest') {
            $order = ['t.sort' => SORT_DESC, 't.starts_at' => SORT_ASC];
        }

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['page' => $page - 1, 'pageSize' => $pageSize],
            'sort' => false,
        ]);
        $query->orderBy($order);

        $models = $provider->getModels();
        $items = [];
        foreach ($models as $tournament) {
            $items[] = $this->serializeListItem($tournament, true);
        }

        return $this->successResponse([
            'items' => $items,
            'stats' => $stats,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => (int)$provider->getTotalCount(),
            ],
        ]);
    }

    /**
     * GET /v1/tournaments/{slug}
     */
    public function actionView($slug)
    {
        $tournament = $this->findPublishedBySlug($slug);
        $viewer = $this->buildViewerContext($tournament);

        return $this->successResponse($this->serializeDetail($tournament, $viewer));
    }

    /**
     * GET /v1/tournaments/{slug}/leaderboard
     */
    public function actionLeaderboard($slug)
    {
        $tournament = $this->findPublishedBySlug($slug);
        $limit = min(100, max(1, (int)Yii::$app->request->get('limit', 50)));

        if ($tournament->getPublicPhase() === Tournament::PHASE_ACTIVE) {
            TournamentRankingCalculator::recalculate($tournament);
        }

        $rows = TournamentRanking::getTopForTournament((int)$tournament->id, $limit);
        $items = [];
        foreach ($rows as $row) {
            $clan = $row->clan;
            if (!$clan) {
                continue;
            }
            $items[] = [
                'position' => (int)$row->position,
                'score' => (float)$row->score,
                'clan' => $this->serializeClanBrief($clan),
            ];
        }

        return $this->successResponse(['items' => $items]);
    }

    /**
     * GET /v1/tournaments/{slug}/raids
     */
    public function actionRaids($slug)
    {
        $tournament = $this->findPublishedBySlug($slug);
        $limit = min(50, max(1, (int)Yii::$app->request->get('limit', 20)));

        $clanIds = TournamentRegistration::find()
            ->select('clan_id')
            ->where(['tournament_id' => (int)$tournament->id])
            ->column();
        $clanIds = array_map('intval', $clanIds);
        if ($clanIds === []) {
            return $this->successResponse(['items' => []]);
        }

        $q = UserRaid::find()
            ->alias('ur')
            ->where(['ur.server_id' => (int)$tournament->server_id])
            ->andWhere(['ur.real_raid' => 1])
            ->andWhere(['in', 'ur.raider_clan_id', $clanIds])
            ->andWhere(['in', 'ur.clan_id', $clanIds])
            ->andWhere(['not', ['ur.clan_id' => null]])
            ->andWhere(['>', 'ur.clan_id', 0])
            ->andWhere(['like', 'ur.type', 'cupboard', false]);

        if ($tournament->starts_at) {
            $q->andWhere(['>=', 'ur.created_at', $tournament->starts_at]);
        }
        if ($tournament->ends_at) {
            $q->andWhere(['<=', 'ur.created_at', $tournament->ends_at]);
        }

        $q->innerJoin(
            ['tr' => TournamentRegistration::tableName()],
            'tr.clan_id = ur.raider_clan_id AND tr.tournament_id = :tid',
            [':tid' => (int)$tournament->id]
        );
        $q->andWhere('ur.created_at >= tr.registered_at');

        if ($tournament->max_participants_per_clan) {
            $q->innerJoin(
                ['tp' => TournamentParticipant::tableName()],
                'tp.registration_id = tr.id AND tp.user_id = ur.user_id'
            );
        }

        $raids = $q->orderBy(['ur.created_at' => SORT_DESC])->limit($limit)->all();
        $clansById = Clan::find()
            ->where(['id' => array_unique(array_merge(
                array_map(static fn (UserRaid $r) => (int)$r->raider_clan_id, $raids),
                array_map(static fn (UserRaid $r) => (int)$r->clan_id, $raids)
            ))])
            ->indexBy('id')
            ->all();

        $items = [];
        foreach ($raids as $raid) {
            $attacker = $clansById[(int)$raid->raider_clan_id] ?? null;
            $defender = $clansById[(int)$raid->clan_id] ?? null;
            $items[] = [
                'id' => (int)$raid->id,
                'attacker_clan' => $attacker ? $this->serializeClanBrief($attacker) : null,
                'defender_clan' => $defender ? $this->serializeClanBrief($defender) : null,
                'blocks_wood' => (int)$raid->blocks_wood,
                'blocks_stone' => (int)$raid->blocks_stone,
                'blocks_metal' => (int)$raid->blocks_metal,
                'blocks_hqm' => (int)$raid->blocks_hqm,
                'score' => (int)$raid->score,
                'location' => $raid->location,
                'created_at' => $raid->created_at,
            ];
        }

        return $this->successResponse(['items' => $items]);
    }

    /**
     * GET /v1/tournaments/{slug}/participants
     */
    public function actionParticipants($slug)
    {
        $tournament = $this->findPublishedBySlug($slug);
        $q = trim((string)Yii::$app->request->get('q', ''));

        $regs = TournamentRegistration::find()
            ->where(['tournament_id' => (int)$tournament->id])
            ->with(['clan', 'participants.user'])
            ->orderBy(['registered_at' => SORT_ASC])
            ->all();

        $items = [];
        $position = 1;
        foreach ($regs as $reg) {
            $clan = $reg->clan;
            if (!$clan) {
                continue;
            }
            if ($q !== '') {
                $hay = mb_strtolower($clan->tag . ' ' . $clan->name, 'UTF-8');
                if (mb_strpos($hay, mb_strtolower($q, 'UTF-8')) === false) {
                    continue;
                }
            }
            $participantRows = [];
            foreach ($reg->participants as $p) {
                if ($p->user) {
                    $participantRows[] = $this->serializeUser($p->user);
                }
            }
            $maxP = $tournament->max_participants_per_clan;
            $items[] = [
                'position' => $position++,
                'registered_at' => $reg->registered_at,
                'participant_count' => count($participantRows),
                'participant_limit' => $maxP !== null ? (int)$maxP : null,
                'clan' => $this->serializeClanBrief($clan),
                'participants' => $participantRows,
            ];
        }

        return $this->successResponse([
            'items' => $items,
            'registered_clans' => count($regs),
            'max_clans' => $tournament->max_clans !== null ? (int)$tournament->max_clans : null,
        ]);
    }

    /**
     * POST /v1/tournaments/{slug}/register
     */
    public function actionRegister($slug)
    {
        $user = $this->getCurrentUser();
        $tournament = $this->findPublishedBySlug($slug);
        $body = $this->getJsonBody();
        $ids = isset($body['member_user_ids']) && is_array($body['member_user_ids'])
            ? array_map('intval', $body['member_user_ids'])
            : [];

        $reg = TournamentRegistrationService::registerClan($tournament, $user, $ids);
        $reg->refresh();
        $viewer = $this->buildViewerContext($tournament, $user);

        return $this->successResponse([
            'registration' => $this->serializeRegistration($reg),
            'viewer' => $viewer,
        ]);
    }

    /**
     * POST /v1/tournaments/{slug}/participants
     */
    public function actionAddParticipants($slug)
    {
        $user = $this->getCurrentUser();
        $tournament = $this->findPublishedBySlug($slug);
        $body = $this->getJsonBody();
        $ids = isset($body['member_user_ids']) && is_array($body['member_user_ids'])
            ? array_map('intval', $body['member_user_ids'])
            : [];
        if ($ids === []) {
            throw new BadRequestHttpException('member_user_ids required');
        }

        $reg = TournamentRegistrationService::addParticipants($tournament, $user, $ids);
        $viewer = $this->buildViewerContext($tournament, $user);

        return $this->successResponse([
            'registration' => $this->serializeRegistration($reg),
            'viewer' => $viewer,
        ]);
    }

    protected function findPublishedBySlug(string $slug): Tournament
    {
        $t = Tournament::findPublishedBySlug($slug);
        if (!$t) {
            throw new NotFoundHttpException('Tournament not found');
        }
        return $t;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getJsonBody(): array
    {
        $raw = Yii::$app->request->getBodyParams();
        return is_array($raw) ? $raw : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildViewerContext(Tournament $tournament, ?User $user = null): array
    {
        if ($user === null && !Yii::$app->user->isGuest) {
            $user = Yii::$app->user->identity;
        }
        $out = [
            'is_authenticated' => $user !== null,
            'clan_id' => null,
            'clan_role' => null,
            'is_registered' => false,
            'can_register' => false,
            'can_add_participants' => false,
            'registration' => null,
            'participant_user_ids' => [],
        ];
        if (!$user) {
            $out['can_register'] = $tournament->getPublicPhase() !== Tournament::PHASE_PAST
                && $tournament->isRegistrationOpen()
                && $tournament->canAcceptMoreClans();
            return $out;
        }

        $member = ClanMember::find()
            ->alias('m')
            ->innerJoin(['c' => Clan::tableName()], 'c.id = m.clan_id')
            ->where([
                'm.user_id' => (int)$user->id,
                'c.server_id' => (int)$tournament->server_id,
            ])
            ->andWhere(['IS', 'm.leave_date', null])
            ->one();

        if ($member) {
            $out['clan_id'] = (int)$member->clan_id;
            $out['clan_role'] = $member->role;
            $isOfficer = $member->isLeader() || $member->isOfficer();
            $reg = TournamentRegistration::find()
                ->where(['tournament_id' => (int)$tournament->id, 'clan_id' => (int)$member->clan_id])
                ->one();
            if ($reg) {
                $out['is_registered'] = true;
                $out['registration'] = $this->serializeRegistration($reg);
                $out['participant_user_ids'] = $reg->getParticipantUserIds();
                if ($isOfficer) {
                    $max = $tournament->max_participants_per_clan;
                    $count = count($out['participant_user_ids']);
                    $out['can_add_participants'] = $tournament->getPublicPhase() !== Tournament::PHASE_PAST
                        && ($max === null || $count < (int)$max);
                }
            } elseif ($isOfficer) {
                $out['can_register'] = $tournament->getPublicPhase() !== Tournament::PHASE_PAST
                    && $tournament->isRegistrationOpen()
                    && $tournament->canAcceptMoreClans();
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeListItem(Tournament $tournament, bool $withLeaderboardPreview = false): array
    {
        $data = $this->serializeTournamentBase($tournament);
        $data['registered_clans'] = $tournament->getRegisteredClansCount();
        if ($withLeaderboardPreview && $tournament->getPublicPhase() === Tournament::PHASE_ACTIVE) {
            $top = TournamentRanking::getTopForTournament((int)$tournament->id, 5);
            $preview = [];
            foreach ($top as $row) {
                if ($row->clan) {
                    $preview[] = [
                        'position' => (int)$row->position,
                        'score' => (float)$row->score,
                        'clan' => $this->serializeClanBrief($row->clan),
                    ];
                }
            }
            $data['leaderboard_preview'] = $preview;
        }
        if ($tournament->getPublicPhase() === Tournament::PHASE_PAST) {
            $podium = TournamentRanking::getTopForTournament((int)$tournament->id, 3);
            $winners = [];
            foreach ($podium as $row) {
                if ($row->clan) {
                    $winners[] = [
                        'position' => (int)$row->position,
                        'score' => (float)$row->score,
                        'clan' => $this->serializeClanBrief($row->clan),
                    ];
                }
            }
            $data['winners'] = $winners;
        }
        return $data;
    }

    /**
     * @param array<string, mixed> $viewer
     * @return array<string, mixed>
     */
    protected function serializeDetail(Tournament $tournament, array $viewer): array
    {
        $data = $this->serializeListItem($tournament, true);
        $data['rules_text'] = $tournament->rules_text;
        $data['rewards'] = array_map([$this, 'serializeReward'], $tournament->rewards);
        $data['viewer'] = $viewer;
        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeTournamentBase(Tournament $tournament): array
    {
        $server = $tournament->server;
        return [
            'id' => (int)$tournament->id,
            'slug' => $tournament->slug,
            'title' => $tournament->title,
            'description' => $tournament->description,
            'phase' => $tournament->getPublicPhase(),
            'registration_open' => $tournament->isRegistrationOpen(),
            'starts_at' => $tournament->starts_at,
            'ends_at' => $tournament->ends_at,
            'registration_ends_at' => $tournament->registration_ends_at,
            'max_clans' => $tournament->max_clans !== null ? (int)$tournament->max_clans : null,
            'max_participants_per_clan' => $tournament->max_participants_per_clan !== null
                ? (int)$tournament->max_participants_per_clan
                : null,
            'prize_pool_label' => $tournament->prize_pool_label,
            'cover_url' => $tournament->getCoverUrl(),
            'format_label' => $tournament->format_label,
            'tags' => $tournament->getTagsArray(),
            'server_id' => (int)$tournament->server_id,
            'server_tag' => $server ? $server->tag : null,
            'server_name' => $server ? ($server->monitoring_name ?: $server->name) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeReward(TournamentReward $reward): array
    {
        return [
            'place' => (int)$reward->place,
            'title' => $reward->title,
            'subtitle' => $reward->subtitle,
            'image_url' => $reward->getImageUrl(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeClanBrief(Clan $clan): array
    {
        return [
            'id' => (int)$clan->id,
            'name' => $clan->name,
            'tag' => $clan->tag,
            'logo_url' => $clan->getLogoUrl(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeRegistration(TournamentRegistration $reg): array
    {
        return [
            'id' => (int)$reg->id,
            'clan_id' => (int)$reg->clan_id,
            'registered_at' => $reg->registered_at,
            'participant_count' => $reg->getParticipantCount(),
            'participant_user_ids' => $reg->getParticipantUserIds(),
        ];
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
}
