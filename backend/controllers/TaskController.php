<?php

namespace backend\controllers;

use backend\forms\box\DropForm;
use common\components\base\Model;
use common\components\helpers\Role;
use common\models\box\Drop;
use common\models\box\DropSearch;
use common\models\tasks\Task;
use yii\web\Response;
use yii\web\Controller;
use Yii;

class TaskController extends Controller
{

    public function behaviors()
    {
        return [
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
     * @throws \yii\db\StaleObjectException
     * @throws \Throwable
     */
    public function actionIndex()
    {
        return $this->redirect('/task/type?id=' . Task::TYPE_FERMER);
    }

    /**
     * @throws \yii\db\StaleObjectException
     * @throws \Throwable
     */
    public function actionType($id)
    {
        /** @var Task[] $drops */
        $items = Task::find()
                     ->andWhere(['type' => $id])
                     ->orderBy(['sort' => SORT_ASC])
                     ->all();
        $tasks = Task::getTypeList();
        unset($tasks[Task::TYPE_All]);
        return $this->render('type', [
            'items' => $items,
            'id' => $id,
            'tasks' => $tasks,
        ]);
    }

    /**
     * @throws \yii\db\StaleObjectException
     * @throws \Throwable
     */
    public function actionUpdate($id)
    {
        $formModel = Task::findOne($id);
        if ($formModel->load(Yii::$app->request->post())) {
            $formModel->save();
            return $this->redirect('/task/type?id=' . $formModel->type);
        }
        $tasks = Task::getTypeList();
        unset($tasks[Task::TYPE_All]);
        return $this->render('update', [
            'model' => $formModel,
            'id' => $id,
            'tasks' => $tasks,
        ]);
    }

    /**
     * @throws \yii\db\StaleObjectException
     * @throws \Throwable
     */
    public function actionSort($id)
    {
        if (!empty($_POST)) {
            $sort = 0;
            foreach ($_POST['items'] as $itemId) {
                $drop = Task::findOne($itemId);
                $drop->sort = $sort;
                $drop->save();
                $sort++;
            }
        }

        /** @var Task[] $drops */
        $items = Task::find()
            ->andWhere(['type' => $id])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        return $this->render('sort', [
            'items' => $items
        ]);
    }
}