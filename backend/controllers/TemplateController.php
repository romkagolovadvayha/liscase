<?php
namespace backend\controllers;

use Yii;
use yii\web\Controller;
use common\models\template\Template;
use yii\web\NotFoundHttpException;

class TemplateController extends Controller
{
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
