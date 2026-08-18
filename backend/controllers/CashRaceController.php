<?php

namespace backend\controllers;

use backend\components\BackendController;
use backend\forms\tournament\CashRaceForm;
use common\components\helpers\Role;
use common\components\tournament\CashRaceService;
use common\models\tournament\CashRaceTournament;
use common\models\tournament\CashRaceKeyToken;
use common\models\tournament\CashRaceScore;
use common\models\tournament\Tournament;
use common\models\user\User;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;

class CashRaceController extends BackendController
{
    public function behaviors(): array
    {
        return [
            'access' => ['class' => AccessControl::class, 'rules' => [
                ['allow' => true, 'actions' => ['index', 'create', 'update', 'start', 'finish', 'players'], 'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR]],
                ['allow' => true, 'actions' => ['delete', 'score-update'], 'roles' => [Role::ROLE_ADMIN]],
            ]],
            'verbs' => ['class' => VerbFilter::class, 'actions' => [
                'start' => ['POST'],
                'finish' => ['POST'],
                'delete' => ['POST'],
                'score-update' => ['POST'],
            ]],
        ];
    }

    public function actionIndex(): string
    {
        $models = Tournament::find()->where(['type' => Tournament::TYPE_CASH_RACE])->with(['server', 'cashRace'])->orderBy(['id' => SORT_DESC])->all();
        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['headerActions'] = [[
            'label' => '<i class="fas fa-plus"></i> Новая денежная гонка', 'url' => ['create'],
            'class' => 'bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium no-underline inline-flex items-center gap-1.5',
        ]];
        return $this->render('index', ['models' => $models]);
    }

    public function actionCreate()
    {
        $model = new CashRaceForm(['type' => Tournament::TYPE_CASH_RACE, 'title' => 'Денежная гонка', 'slug' => Tournament::generateSlug('denezhnaya-gonka'), 'status' => Tournament::STATUS_DRAFT, 'format_label' => 'Ключи из бочек']);
        if ($model->load(Yii::$app->request->post()) && $model->saveWithUploads()) {
            Yii::$app->session->setFlash('success', 'Денежная гонка создана.');
            return $this->redirect(['update', 'id' => $model->id]);
        }
        $this->view->params['contentClass'] = 'content-no-padding';
        return $this->render('create', ['model' => $model]);
    }

    public function actionUpdate($id)
    {
        $model = CashRaceForm::find()->where(['id' => (int)$id, 'type' => Tournament::TYPE_CASH_RACE])->with('rewards')->one();
        if (!$model) throw new NotFoundHttpException('Cash race not found');
        if ($model->load(Yii::$app->request->post()) && $model->saveWithUploads()) {
            Yii::$app->session->setFlash('success', 'Настройки сохранены.');
            return $this->redirect(['update', 'id' => $model->id]);
        }
        $this->view->params['contentClass'] = 'content-no-padding';
        return $this->render('update', ['model' => $model]);
    }

    public function actionStart($id)
    {
        $model = $this->findMaster($id);
        $model->status = Tournament::STATUS_PUBLISHED;
        $model->starts_at = date('Y-m-d H:i:s');
        if (strtotime((string)$model->ends_at) <= time()) $model->ends_at = date('Y-m-d H:i:s', time() + 86400);
        $model->save(false);
        Yii::$app->session->setFlash('success', 'Гонка запущена. Плагин увидит её при следующем опросе.');
        return $this->redirect(['update', 'id' => $id]);
    }

    public function actionFinish($id)
    {
        $model = $this->findMaster($id);
        $model->ends_at = date('Y-m-d H:i:s', time() - 1);
        $model->save(false);
        $config = CashRaceTournament::findOne(['tournament_id' => $model->id]);
        if ($config) CashRaceService::finalize($config);
        Yii::$app->session->setFlash('success', 'Гонка завершена, места рассчитаны и медали начислены.');
        return $this->redirect(['update', 'id' => $id]);
    }

    public function actionPlayers($id): string
    {
        $model = $this->findMaster($id);
        $config = CashRaceTournament::findOne(['tournament_id' => $model->id]);
        $query = CashRaceScore::find()
            ->alias('score')
            ->where(['score.tournament_id' => (int)$model->id])
            ->andWhere(['>', 'score.keys_found', 0])
            ->with('user');

        $search = trim((string)Yii::$app->request->get('q', ''));
        if ($search !== '') {
            $matchingUsers = User::find()->select('id')->where(['like', 'username', $search]);
            $query->andWhere(['or',
                ['like', 'score.steam_id', $search],
                ['score.user_id' => $matchingUsers],
            ]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 40],
            'sort' => [
                'defaultOrder' => ['keys_found' => SORT_DESC, 'id' => SORT_ASC],
                'attributes' => ['id', 'keys_found', 'keys_lost', 'keys_deposited', 'last_found_at'],
            ],
        ]);
        $scoreModels = $dataProvider->getModels();
        $userIds = array_values(array_unique(array_map(static function (CashRaceScore $score) {
            return (int)$score->user_id;
        }, $scoreModels)));
        $heldByUser = [];
        if ($userIds) {
            $heldRows = CashRaceKeyToken::find()->select([
                'user_id',
                'held_count' => 'COUNT(*)',
            ])->where([
                'tournament_id' => (int)$model->id,
                'user_id' => $userIds,
                'state' => CashRaceKeyToken::STATE_HELD,
            ])->groupBy('user_id')->asArray()->all();
            foreach ($heldRows as $row) $heldByUser[(int)$row['user_id']] = (int)$row['held_count'];
        }
        $totals = CashRaceScore::find()->select([
            'players' => 'COUNT(*)',
            'found' => 'COALESCE(SUM(keys_found), 0)',
            'lost' => 'COALESCE(SUM(keys_lost), 0)',
            'deposited' => 'COALESCE(SUM(keys_deposited), 0)',
        ])->where(['tournament_id' => (int)$model->id])->andWhere(['>', 'keys_found', 0])->asArray()->one();

        $identity = Yii::$app->user->identity;
        $canManageScores = $identity && $identity->canRoles([Role::ROLE_ADMIN]);
        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['headerActions'] = [[
            'label' => '<i class="fas fa-cog"></i> Настройки гонки',
            'url' => ['update', 'id' => $model->id],
            'class' => 'ds-btn ds-btn--secondary ds-btn--sm',
        ]];
        return $this->render('players', compact('model', 'config', 'dataProvider', 'totals', 'search', 'canManageScores', 'heldByUser'));
    }

    public function actionScoreUpdate($id, $scoreId)
    {
        $model = $this->findMaster($id);
        $config = CashRaceTournament::findOne(['tournament_id' => $model->id]);
        $score = CashRaceScore::findOne(['id' => (int)$scoreId, 'tournament_id' => (int)$model->id]);
        if (!$config || !$score) throw new NotFoundHttpException('Статистика игрока не найдена');

        $values = [];
        foreach (['keys_found', 'keys_lost', 'keys_deposited'] as $attribute) {
            $raw = trim((string)Yii::$app->request->post($attribute, ''));
            if (!preg_match('/^\d{1,7}$/', $raw)) {
                Yii::$app->session->setFlash('error', 'Количество ключей должно быть целым неотрицательным числом.');
                return $this->redirectToPlayers($model->id);
            }
            $values[$attribute] = (int)$raw;
        }

        try {
            $before = [
                'keys_found' => (int)$score->keys_found,
                'keys_lost' => (int)$score->keys_lost,
                'keys_deposited' => (int)$score->keys_deposited,
            ];
            $updated = CashRaceService::updateScoreByAdmin(
                $config,
                $score,
                $values['keys_found'],
                $values['keys_lost'],
                $values['keys_deposited']
            );
            Yii::info([
                'tournament_id' => (int)$model->id,
                'score_id' => (int)$updated->id,
                'player_user_id' => (int)$updated->user_id,
                'player_steam_id' => (string)$updated->steam_id,
                'admin_user_id' => (int)Yii::$app->user->id,
                'before' => $before,
                'after' => $values,
            ], 'cash-race-admin-score');
            Yii::$app->session->setFlash('success', 'Количество ключей игрока обновлено. Рейтинг пересчитан.');
        } catch (\DomainException $e) {
            $messages = [
                'TOURNAMENT_ALREADY_AWARDED' => 'Нельзя менять результаты после начисления медалей.',
                'SCORE_TOTAL_EXCEEDS_FOUND' => 'Найденных ключей не может быть меньше суммы потерянных, засчитанных и находящихся у игрока.',
                'INVALID_SCORE_VALUE' => 'Количество должно быть от 0 до 1 000 000.',
            ];
            Yii::$app->session->setFlash('error', $messages[$e->getMessage()] ?? 'Не удалось изменить статистику игрока.');
        }
        return $this->redirectToPlayers($model->id);
    }

    public function actionDelete($id)
    {
        $model = $this->findMaster($id);
        if ($model->status !== Tournament::STATUS_DRAFT) throw new \DomainException('Удалить можно только черновик');
        $model->delete();
        return $this->redirect(['index']);
    }

    private function findMaster($id): Tournament
    {
        $model = Tournament::findOne(['id' => (int)$id, 'type' => Tournament::TYPE_CASH_RACE]);
        if (!$model) throw new NotFoundHttpException('Cash race not found');
        return $model;
    }

    private function redirectToPlayers(int $tournamentId)
    {
        $params = ['players', 'id' => $tournamentId];
        $search = trim((string)Yii::$app->request->post('return_q', ''));
        $page = (int)Yii::$app->request->post('return_page', 1);
        if ($search !== '') $params['q'] = $search;
        if ($page > 1) $params['page'] = $page;
        return $this->redirect($params);
    }
}
