<?php
namespace backend\controllers;

use common\components\helpers\Role;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use common\models\template\Template;
use yii\web\NotFoundHttpException;

class TemplateController extends Controller
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $templates = Template::find()->all();
        return $this->render('index', ['templates' => $templates]);
    }

    public function actionEdit($id)
    {
        $template = Template::findOne($id);
        if (!$template) {
            throw new NotFoundHttpException('Template not found');
        }

        return $this->render('edit', ['template' => $template]);
    }

    public function actionSettings($id)
    {
        $template = Template::findOne($id);
        if (!$template) {
            throw new NotFoundHttpException('Template not found');
        }

        if ($template->load(Yii::$app->request->post()) && $template->save()) {
            Yii::$app->session->setFlash('success', 'Template saved successfully');
            return $this->redirect(['index']);
        }

        return $this->render('settings', ['template' => $template]);
    }

    public function actionCreate()
    {
        $template = new Template();

        if ($template->load(Yii::$app->request->post()) && $template->save()) {
            Yii::$app->session->setFlash('success', 'Template created successfully');
            return $this->redirect(['index']);
        }

        return $this->render('create', ['template' => $template]);
    }
}
