<?php

namespace frontend\controllers;

use common\components\tasks\TaskComponent;
use common\components\web\Cookie;
use common\models\invoice\Invoice;
use common\models\profit\Profit;
use common\models\tasks\Tasks;
use common\models\user\UserTasks;
use common\models\user\UserTree;
use frontend\forms\tasks\TaskCheckForm;
use Yii;
use yii\base\BaseObject;
use yii\db\ActiveQuery;
use yii\db\Query;
use yii\db\StaleObjectException;
use yii\helpers\ArrayHelper;
use yii\helpers\VarDumper;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\widgets\ActiveForm;
use common\controllers\WebController;

class TasksController extends WebController
{
    public $layout = 'tasks';

    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionTask($id)
    {
        $model = TaskCheckForm::findOne($id);
        if (empty($model) || !$model->is_publish) {
            throw new NotFoundHttpException('Task not found');
        }

        $user = Yii::$app->user->identity;

        /** @var UserTasks $userTask */
        $userTask = UserTasks::find()
                             ->andWhere(['user_id' => $user->id])
                             ->andWhere(['task_id' => $model->id])
                             ->orderBy(['id' => SORT_DESC])
                             ->one();

        if ($this->request->isPost && (empty($userTask) || $userTask->status !== UserTasks::STATUS_SUCCESS || $model->is_permanent)) {
            $model->load(Yii::$app->request->post());
            $userTask = $model->check();
            if (!empty($userTask) && $userTask->status === UserTasks::STATUS_SUCCESS) {
                $taskComponent = TaskComponent::getInstance($userTask->task->system_check_code);
                if (!empty($taskComponent->getRedirectSuccess())) {
                    return $this->redirect($taskComponent->getRedirectSuccess());
                }
                if ($model->is_permanent) {
                    return $this->redirect('/tasks');
                } else {
                    return $this->redirect('/tasks/done');
                }
//                Yii::$app->session->addFlash('success', Yii::t('common', 'Задание успешно выполнено!'));
            }
//            if ($userTask->status === UserTasks::STATUS_WAITING) {
//                Yii::$app->session->addFlash('success', Yii::t('common', 'Задание успешно отправлено на проверку!'));
//            }
        }

        return $this->renderAjax('task', [
            'model' => $model,
            'userTask' => $userTask,
        ]);
    }

    public function actionDone()
    {
        return $this->render('done');
    }

    public function actionStartTask($id, $type)
    {
        $model = Tasks::findOne($id);
        if (empty($model) || !$model->is_publish) {
            throw new NotFoundHttpException('Task not found');
        }
        $user = Yii::$app->user->identity;

        $exists = UserTasks::find()
            ->andWhere(['NOT IN', 'status', [UserTasks::STATUS_SUCCESS]])
            ->andWhere(['user_id' => $user->id])
            ->andWhere(['task_id' => $model->id])
            ->exists();
        $statusTask = UserTasks::STATUS_CREATED;
        if($model->is_check_method_auto){
            $statusTask = UserTasks::STATUS_WAITING;
        }
        if (!$exists) {
            $userTasks = new UserTasks();
            $userTasks->task_id = $model->id;
            $userTasks->user_id = $user->id;
            $userTasks->status = $statusTask;
            $userTasks->created_at = date('Y-m-d H:i:s');
            $userTasks->save();
        }

        $link = $model->url_link;
        if ($type === 'button') {
            $link = $model->button_url;
        }

        $link = Tasks::translateLinks($link, Yii::$app->user->identity->current_language);

        return $this->redirect($link);
    }
}
