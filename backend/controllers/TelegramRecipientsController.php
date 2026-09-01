<?php

namespace backend\controllers;

use backend\models\TelegramRecipients;
use backend\models\TelegramRecipientsSearch;
use common\components\base\Model;
use common\components\helpers\Role;
use common\models\user\User;
use kartik\form\ActiveForm;
use Yii;
use yii\db\StaleObjectException;
use yii\web\Response;
use yii\web\NotFoundHttpException;
use yii\data\ActiveDataProvider;
use yii\filters\VerbFilter;

class TelegramRecipientsController extends \backend\components\CrudController
{

    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['delete' => ['POST']],
            ],
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return mixed
     */
    protected function _getSearchClassName()
    {
        return TelegramRecipientsSearch::class;
    }

    /**
     * @return string
     */
    protected function _getFormClassName()
    {
        return TelegramRecipients::class;
    }

    /**
     * @param Model $formModel
     * @param string $view
     *
     * @return string|array|\yii\web\Response
     */
    protected function _saveForm($formModel, $view)
    {
        if ($formModel->load(Yii::$app->request->post())) {
            //   \Yii::info('post ' . print_r(Yii::$app->request->post(),1), 'problem');
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return ActiveForm::validate($formModel);
            }
            if ($formModel->saveRecord()) {
                Yii::$app->session->addFlash('success', 'Аудитория сохранена и доступна в новых рассылках.');
                return $this->redirect($this->getIndexUrl());
            }
        }
        return $this->render($view, [
            'model' => $formModel,
        ]);
    }

    /**
     * @throws StaleObjectException
     * @throws \Throwable
     */
    public function actionDelete($id): Response
    {
        $formModel = $this->findRecipientList($id);
        $usedCount = $formModel->getUsageCount();
        if ($usedCount > 0) {
            Yii::$app->session->addFlash('error', "Аудитория используется в рассылках ({$usedCount}) и не может быть удалена.");
            return $this->redirect(['index']);
        }
        $formModel->delete();
        Yii::$app->session->addFlash('success', 'Аудитория удалена.');
        return $this->redirect(['index']);
    }

    public function actionView($id)
    {
        $model = $this->findRecipientList($id);
        $dataProvider = new ActiveDataProvider([
            'query' => User::find()->andWhere(['id' => $model->getResolvedUserIds()]),
            'sort' => ['defaultOrder' => ['id' => SORT_DESC]],
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('view', ['model' => $model, 'dataProvider' => $dataProvider]);
    }

    public function actionSearchUsers($q = null): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $q = trim((string)$q);
        if (mb_strlen($q) < 2) {
            return ['results' => []];
        }

        $query = User::find()->andWhere(['status' => User::STATUS_ACTIVE]);
        $conditions = [
            'or',
            ['like', 'username', $q],
            ['like', 'steam_id', $q],
            ['like', 'ref_code', $q],
        ];
        if (ctype_digit($q)) {
            $conditions[] = ['id' => (int)$q];
        }
        $query->andWhere($conditions)->orderBy(['id' => SORT_DESC])->limit(20);

        $results = [];
        foreach ($query->all() as $user) {
            $results[] = ['id' => (string)$user->id, 'text' => TelegramRecipients::formatUserLabel($user)];
        }
        return ['results' => $results];
    }

    private function findRecipientList($id): TelegramRecipients
    {
        $model = TelegramRecipients::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Аудитория не найдена.');
        }
        return $model;
    }
}
