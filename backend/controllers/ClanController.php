<?php

namespace backend\controllers;

use backend\components\BackendController;
use backend\forms\clan\MemberPermissionsForm;
use backend\models\ClanMemberAddForm;
use backend\models\ClanSearch;
use common\components\helpers\Role;
use common\components\queue\clan\UpdateClanStatisticsJob;
use common\models\clan\Clan;
use common\models\clan\ClanMember;
use common\models\clan\ClanMemberStatistics;
use common\models\clan\ClanPermission;
use common\models\clan\ClanStatistics;
use common\models\servers\Servers;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Полное управление кланами и участниками в админке.
 */
class ClanController extends BackendController
{
    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => [
                            'index',
                            'view',
                            'create',
                            'update',
                            'queue-statistics',
                            'member-create',
                            'member-update',
                            'member-permissions',
                            'member-remove',
                            'transfer-leadership',
                        ],
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR],
                    ],
                    [
                        'allow' => true,
                        'actions' => ['delete'],
                        'roles' => [Role::ROLE_ADMIN],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'queue-statistics' => ['POST'],
                    'member-remove' => ['POST'],
                    'transfer-leadership' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex(): string
    {
        $searchModel = new ClanSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['showFilters'] = true;
        $this->view->params['searchModel'] = $searchModel;
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-plus"></i> ' . Yii::t('common', 'Новый клан'),
                'url' => ['create'],
                'class' => 'bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Создание клана. Лидер добавляется в {@see Clan::afterSave}.
     */
    public function actionCreate()
    {
        $model = new Clan();
        $leaderChoices = [];

        if ($model->load(Yii::$app->request->post())) {
            if ($model->save()) {
                Yii::$app->session->setFlash('success', Yii::t('common', 'Клан создан.'));
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-arrow-left"></i> ' . Yii::t('common', 'К списку'),
                'url' => ['index'],
                'class' => 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];

        return $this->render('create', [
            'model' => $model,
            'leaderChoices' => $leaderChoices,
        ]);
    }

    public function actionView($id): string
    {
        $model = $this->findModel($id);

        $server = $model->server;
        $wipe = $server ? $server->currentWipe() : null;

        $members = ClanMember::find()
            ->where(['clan_id' => $model->id])
            ->with(['user'])
            ->orderBy(['leave_date' => SORT_ASC, 'join_date' => SORT_ASC])
            ->all();

        $statsByMemberId = [];
        if ($wipe && $server) {
            $statsByMemberId = ClanMemberStatistics::find()
                ->where([
                    'clan_id' => $model->id,
                    'server_id' => $model->server_id,
                    'wipe' => $wipe,
                ])
                ->with('statValues')
                ->indexBy('clan_member_id')
                ->all();
        }

        $clanStat = null;
        if ($wipe && $server) {
            $clanStat = ClanStatistics::find()
                ->where([
                    'clan_id' => $model->id,
                    'server_id' => $model->server_id,
                    'wipe' => $wipe,
                ])
                ->one();
        }

        $frontendBase = rtrim((string)Yii::$app->params['baseUrl'], '/');
        $publicUrl = $server
            ? $frontendBase . '/clans/' . rawurlencode($server->tag) . '/' . (int)$model->id
            : null;

        $transferChoices = $this->getTransferLeaderChoices($model);

        return $this->render('view', [
            'model' => $model,
            'members' => $members,
            'statsByMemberId' => $statsByMemberId,
            'clanStat' => $clanStat,
            'wipe' => $wipe,
            'publicUrl' => $publicUrl,
            'transferChoices' => $transferChoices,
        ]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        $leaderChoices = $this->getLeaderDropdownChoices($model);

        if ($model->load(Yii::$app->request->post())) {
            if (!$this->validateLeaderIsActiveMember($model)) {
                Yii::$app->session->setFlash('error', Yii::t('common', 'Лидер должен быть активным участником клана.'));
            } elseif ($model->save()) {
                Yii::$app->session->setFlash('success', Yii::t('common', 'Сохранено.'));
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-arrow-left"></i> ' . Yii::t('common', 'К списку'),
                'url' => ['index'],
                'class' => 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];

        return $this->render('update', [
            'model' => $model,
            'leaderChoices' => $leaderChoices,
        ]);
    }

    public function actionDelete($id): Response
    {
        $model = $this->findModel($id);
        if ($model->logo) {
            $model->deleteLogo();
        }
        if ($model->delete() === false) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Не удалось удалить клан.'));
            return $this->redirect(['view', 'id' => $id]);
        }
        Yii::$app->session->setFlash('success', Yii::t('common', 'Клан удалён.'));
        return $this->redirect(['index']);
    }

    public function actionQueueStatistics($id): Response
    {
        $model = $this->findModel($id);
        $server = Servers::findOne($model->server_id);
        if (!$server) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Сервер не найден.'));
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $wipe = $server->currentWipe();
        try {
            Yii::$app->queueParams->push(new UpdateClanStatisticsJob([
                'serverId' => $server->id,
                'wipe' => $wipe,
            ]));
            Yii::$app->session->setFlash('success', Yii::t('common', 'Пересчёт статистики кланов поставлен в очередь для сервера {tag}.', ['tag' => $server->tag]));
        } catch (\Throwable $e) {
            Yii::error('ClanController::queue-statistics: ' . $e->getMessage(), 'clan');
            Yii::$app->session->setFlash('error', Yii::t('common', 'Ошибка очереди: {msg}', ['msg' => $e->getMessage()]));
        }

        return $this->redirect(['view', 'id' => $model->id]);
    }

    /**
     * Добавить участника (роль member или officer).
     */
    public function actionMemberCreate($clanId)
    {
        $clan = $this->findModel((int)$clanId);
        $form = new ClanMemberAddForm();

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            $member = $clan->addMember((int)$form->user_id, $form->role);
            if ($member) {
                Yii::$app->session->setFlash('success', Yii::t('common', 'Участник добавлен.'));
                return $this->redirect(['view', 'id' => $clan->id]);
            }
            Yii::$app->session->setFlash('error', Yii::t('common', 'Не удалось добавить: пользователь уже в клане или ошибка сохранения.'));
        }

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-arrow-left"></i> ' . Yii::t('common', 'К клану'),
                'url' => ['view', 'id' => $clan->id],
                'class' => 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];

        return $this->render('member-create', [
            'clan' => $clan,
            'form' => $form,
        ]);
    }

    /**
     * Смена роли (member / officer), не для лидера.
     */
    public function actionMemberUpdate($id)
    {
        $member = $this->findMemberModel((int)$id);
        $clan = $member->clan;

        if ($member->isLeader()) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Роль лидера меняется через передачу лидерства.'));
            return $this->redirect(['view', 'id' => $clan->id]);
        }

        if ($member->load(Yii::$app->request->post())) {
            if (!in_array($member->role, [ClanMember::ROLE_MEMBER, ClanMember::ROLE_OFFICER], true)) {
                $member->role = ClanMember::ROLE_MEMBER;
            }
            if ($member->save(false)) {
                Yii::$app->session->setFlash('success', Yii::t('common', 'Сохранено.'));
                return $this->redirect(['view', 'id' => $clan->id]);
            }
        }

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-arrow-left"></i> ' . Yii::t('common', 'К клану'),
                'url' => ['view', 'id' => $clan->id],
                'class' => 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];

        return $this->render('member-update', [
            'member' => $member,
            'clan' => $clan,
        ]);
    }

    /**
     * Права участника (не лидер).
     */
    public function actionMemberPermissions($id)
    {
        $member = $this->findMemberModel((int)$id);
        $clan = $member->clan;

        if ($member->isLeader()) {
            Yii::$app->session->setFlash('info', Yii::t('common', 'У лидера все разрешения по определению.'));
            return $this->redirect(['view', 'id' => $clan->id]);
        }

        $form = new MemberPermissionsForm();
        $form->permissionKeys = $member->getPermissionKeys();

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            if ($member->syncPermissions($form->permissionKeys ?: [])) {
                Yii::$app->session->setFlash('success', Yii::t('common', 'Разрешения обновлены.'));
                return $this->redirect(['view', 'id' => $clan->id]);
            }
            Yii::$app->session->setFlash('error', Yii::t('common', 'Не удалось сохранить разрешения.'));
        }

        $allPermissions = ClanPermission::find()->orderBy(['name' => SORT_ASC])->all();

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-arrow-left"></i> ' . Yii::t('common', 'К клану'),
                'url' => ['view', 'id' => $clan->id],
                'class' => 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];

        return $this->render('member-permissions', [
            'member' => $member,
            'clan' => $clan,
            'form' => $form,
            'allPermissions' => $allPermissions,
        ]);
    }

    public function actionMemberRemove($id): Response
    {
        $member = $this->findMemberModel((int)$id);
        $clan = $member->clan;

        if ($member->isLeader()) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Нельзя исключить лидера. Сначала передайте лидерство или удалите клан.'));
            return $this->redirect(['view', 'id' => $clan->id]);
        }

        if (!$member->isActive()) {
            Yii::$app->session->setFlash('info', Yii::t('common', 'Участник уже неактивен.'));
            return $this->redirect(['view', 'id' => $clan->id]);
        }

        if ($clan->removeMember($member->user_id)) {
            Yii::$app->session->setFlash('success', Yii::t('common', 'Участник исключён из клана.'));
        } else {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Не удалось исключить участника.'));
        }

        return $this->redirect(['view', 'id' => $clan->id]);
    }

    /**
     * Передача лидерства активному участнику.
     */
    public function actionTransferLeadership($id): Response
    {
        $clan = $this->findModel((int)$id);
        $newLeaderUserId = (int)Yii::$app->request->post('new_leader_user_id');

        if ($newLeaderUserId <= 0) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Выберите нового лидера.'));
            return $this->redirect(['view', 'id' => $clan->id]);
        }

        if ($clan->transferLeadership($newLeaderUserId)) {
            Yii::$app->session->setFlash('success', Yii::t('common', 'Лидерство передано.'));
        } else {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Не удалось передать лидерство (проверьте, что пользователь — активный участник).'));
        }

        return $this->redirect(['view', 'id' => $clan->id]);
    }

    private function findModel($id): Clan
    {
        $model = Clan::find()
            ->where(['id' => (int)$id])
            ->with(['server', 'leaderUser'])
            ->one();
        if ($model === null) {
            throw new NotFoundHttpException(Yii::t('common', 'Клан не найден.'));
        }
        return $model;
    }

    private function findMemberModel(int $id): ClanMember
    {
        $member = ClanMember::find()
            ->where(['id' => $id])
            ->with(['clan', 'user'])
            ->one();
        if ($member === null) {
            throw new NotFoundHttpException(Yii::t('common', 'Участник не найден.'));
        }
        return $member;
    }

    /**
     * @return array<int, string>
     */
    private function getLeaderDropdownChoices(Clan $clan): array
    {
        $members = ClanMember::find()
            ->where(['clan_id' => $clan->id])
            ->andWhere(['IS', 'leave_date', null])
            ->with('user')
            ->all();

        $choices = [];
        foreach ($members as $m) {
            $u = $m->user;
            $label = $u ? ($u->username . ' (#' . $m->user_id . ')') : ('user #' . $m->user_id);
            $choices[$m->user_id] = $label;
        }

        return $choices;
    }

    /**
     * Кандидаты в лидеры (активные участники, кроме текущего лидера).
     *
     * @return array<int, string>
     */
    private function getTransferLeaderChoices(Clan $clan): array
    {
        $choices = $this->getLeaderDropdownChoices($clan);
        unset($choices[$clan->leader_user_id]);

        return $choices;
    }

    private function validateLeaderIsActiveMember(Clan $model): bool
    {
        $exists = ClanMember::find()
            ->where(['clan_id' => $model->id, 'user_id' => $model->leader_user_id])
            ->andWhere(['IS', 'leave_date', null])
            ->exists();

        if (!$exists) {
            $model->addError('leader_user_id', Yii::t('common', 'Лидер должен быть активным участником клана.'));
            return false;
        }
        return true;
    }
}
