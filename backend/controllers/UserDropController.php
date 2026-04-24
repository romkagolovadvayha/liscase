<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\models\user\UserDrop;
use common\models\user\UserDropSearch;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\Response;

class UserDropController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'bulk-status' => ['POST'],
                    'set-status' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex(): string
    {
        $searchModel = new UserDropSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['showFilters'] = true;
        $this->view->params['searchModel'] = $searchModel;
        $this->view->params['headerActions'] = [];

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionBulkStatus(): Response
    {
        $ids = Yii::$app->request->post('selection', []);
        if (!is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids, static fn (int $id) => $id > 0);

        $statusRaw = Yii::$app->request->post('bulk_status');
        $status = is_numeric($statusRaw) ? (int) $statusRaw : null;
        $allowed = array_keys(UserDrop::getStatusList());

        if ($ids === []) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Отметьте хотя бы одну запись.'));
            return $this->redirectBackToIndex();
        }
        if ($status === null || !in_array($status, $allowed, true)) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Выберите статус для массового применения.'));
            return $this->redirectBackToIndex();
        }

        $updated = UserDrop::updateAll(['status' => $status], ['id' => $ids]);
        Yii::$app->session->setFlash(
            'success',
            Yii::t('common', 'Обновлено записей: {n}', ['n' => $updated])
        );

        return $this->redirectBackToIndex();
    }

    public function actionSetStatus(): Response
    {
        $id = (int) Yii::$app->request->post('id');
        $statusRaw = Yii::$app->request->post('status');
        $status = is_numeric($statusRaw) ? (int) $statusRaw : null;
        $allowed = array_keys(UserDrop::getStatusList());

        $model = $id > 0 ? UserDrop::findOne($id) : null;
        if ($model === null || !in_array($status, $allowed, true)) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Не удалось изменить статус.'));
            return $this->redirectBackToIndex();
        }

        $model->status = $status;
        if ($model->save(false)) {
            Yii::$app->session->setFlash('success', Yii::t('common', 'Статус обновлён.'));
        } else {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Ошибка сохранения.'));
        }

        return $this->redirectBackToIndex();
    }

    private function redirectBackToIndex(): Response
    {
        $ref = Yii::$app->request->getReferrer();
        if (is_string($ref) && $ref !== '' && str_contains($ref, (string) Yii::$app->request->hostName)) {
            return $this->redirect($ref);
        }

        return $this->redirect(Url::to(array_merge(['index'], Yii::$app->request->getQueryParams())));
    }
}
