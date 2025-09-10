<?php

namespace frontend\controllers;

use common\components\helpers\Role;
use common\controllers\WebController;
use common\models\building\Building;
use common\models\building\BuildingLike;
use common\models\serverskin\ServerSkin;
use common\models\serverskin\ServerSkinCategory;
use common\models\serverskin\ServerSkinLike;
use frontend\forms\buildings\BuildingForm;
use frontend\forms\serverskin\ServerSkinForm;
use frontend\models\building\BuildingSearch;
use frontend\models\serverskin\ServerSkinSearch;
use yii\base\BaseObject;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use Yii;

/**
 * BuildingController implements the CRUD actions for Building model.
 */
class CustomSkinsController extends WebController
{

    /**
     * @return array
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['index']
                    ],
                    [
                        'allow' => true,
                        'roles' => ['@'],
                        'actions' => ['create', 'like', 'delete']
                    ]
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ]);
    }

    /**
     * Lists all Building models.
     *
     * @return string
     */
    public function actionIndex()
    {
        if (!Yii::$app->settings->get('section_skins')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        $searchModel = new ServerSkinSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        $categories = ArrayHelper::map(
            ServerSkinCategory::find()->orderBy(['name' => SORT_ASC])->asArray()->all(),
            'id',
            'name'
        );

        $this->view->params['page'] = 'custom-skins';

        // Базовый (универсальный) description
        $desc = Yii::t('common',
                       'Скины из мастерской Rust от игроков: {count} вариантов для SkinBox. Фильтры по категориям, лайки и модерация. Добавьте свой скин и голосуйте.',
                       ['count' => (int)$dataProvider->getTotalCount()]
        );
        // Регистрируем мета-теги
        $this->view->registerMetaTag([
                                         'name'    => 'description',
                                         'content' => $desc,
                                     ], 'description');

        $this->view->registerMetaTag(['property' => 'og:title',       'content' => Yii::t('common', 'Скины из мастерской для SkinBox')],      'og:title');
        $this->view->registerMetaTag(['property' => 'og:description', 'content' => $desc],             'og:description');

        // Канонический адрес раздела
        $this->view->registerLinkTag([
                                         'rel'  => 'canonical',
                                         'href' => Yii::$app->params['homePage'] . '/custom-skins',
                                     ]);

        return $this->render('index', [
            'searchModel'   => $searchModel,
            'dataProvider' => $dataProvider,
            'categories' => $categories,
        ]);
    }

    public function actionLike($id)
    {
        if (!Yii::$app->settings->get('section_skins')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        if (Yii::$app->request->method !== 'POST') {
            return "{'status': 'success'}";
        }
        /** @var ServerSkin $model */
        $model = ServerSkin::findOne($id);
        if (empty($model)) {
            return "{'status': 'success'}";
        }

        $userLike = ServerSkinLike::find()
                                ->andWhere(['user_id' => Yii::$app->user->id])
                                ->andWhere(['server_skin_id' => $model->id])
                                ->one();
        if (!empty($userLike)) {
            $model->likes -= 1;
            $model->save();
            $userLike->delete();
        } else {
            $like = new ServerSkinLike();
            $like->user_id = Yii::$app->user->id;
            $like->server_skin_id = $model->id;
            $like->type = ServerSkinLike::TYPE_LIKE;
            $like->created_at = date('Y-m-d H:i:s');
            $like->save();
            $model->likes += 1;
            $model->save();
        }

        return "{'status': 'success'}";
    }

    /**
     * Creates a new Building model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        if (!Yii::$app->settings->get('section_skins')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user->server)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Добавить скин могут только игроки!'));
            return $this->redirect(['index']);
        }

        $model = new ServerSkinForm();
        if ($this->request->isPost) {
            if ($model->load($this->request->post()) && $model->saveRecord()) {
                Yii::$app->session->addFlash('success', Yii::t('common', 'Скин успешно отправлен на проверку!'));
                return $this->redirect(['index']);
            }
        } else {
            $model->loadDefaultValues();
        }

        $this->view->params['page'] = 'custom-skins';
        return $this->renderAjax('create', [
            'model' => $model,
            'server' => $user->server,
        ]);
    }

    /**
     * Finds the Building model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return ServerSkinForm the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = ServerSkinForm::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
