<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\box\Drop;
use common\models\invoice\Deposit;
use common\models\profit\Profit;
use common\models\tasks\Task;
use common\models\user\User;
use common\models\user\UserDrop;
use common\models\user\UserTask;
use frontend\forms\market\PaymentForm;
use frontend\forms\profile\ProfileForm;
use yii\base\BaseObject;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use common\components\web\AuthorizedControllerTrait;
use Yii;
use yii\web\Response;

class UserController extends WebController
{
    use AuthorizedControllerTrait;

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

//    /**
//     * @param $id
//     *
//     * @return \yii\web\Response | string
//     * @throws NotFoundHttpException
//     */
//    public function actionBox($id)
//    {
//        $userBox = UserBox::findOne($id);
//        if (empty($userBox)) {
//            throw new NotFoundHttpException(Yii::t('common', 'Контейнер не найден!'));
//        }
//        return $this->render('box', [
//            'userBox' => $userBox
//        ]);
//    }

    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionInventory()
    {
        if (!Yii::$app->user->isGuest && Yii::$app->user->identity->status === User::STATUS_BLOCKED) {
            throw new ForbiddenHttpException(Yii::t('common', 'Ваш аккаунт заблокирован!'));
        }
        $post = Yii::$app->request->post();
        if (!empty($post['sell'])) {
            $userBalance = Yii::$app->user->identity->getPersonalBalance();
            $userDrop = UserDrop::findOne($post['sell']);
            if (!empty($userDrop->box_id) || !empty($userDrop->sets_id)) {
                throw new HttpException(402, Yii::t('common', 'Не подлежит возврату!'));
            }
            if (empty($userDrop) || $userDrop->status !== UserDrop::STATUS_ACTIVE) {
                throw new HttpException(402, Yii::t('common', 'Не найдена вещь в корзине!'));
            }
            $this->_sellUserDrop($userDrop, $userBalance->id);
            Yii::$app->session->addFlash('success', Yii::t('common', 'Предмет успешно продан!'));
            return $this->refresh();
        }
        return $this->render('inventory');
    }
    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionHistory($depositId = null)
    {
        if (!Yii::$app->user->isGuest && Yii::$app->user->identity->status === User::STATUS_BLOCKED) {
            throw new ForbiddenHttpException(Yii::t('common', 'Ваш аккаунт заблокирован!'));
        }
        $post = Yii::$app->request->post();
        $user = Yii::$app->user->identity;
        if (!empty($depositId)) {
            $deposit = Deposit::findOne($depositId);
            if (!empty($deposit) && $deposit->user_id === $user->id && $deposit->status === Deposit::STATUS_WAIT_CONFIRM) {
                $status = $deposit->check();
                if ($status === Deposit::STATUS_SUCCESS) {
                    Yii::$app->session->addFlash('success', Yii::t('common', 'Платеж успешно зачислен!'));
                } elseif ($status == Deposit::STATUS_CANCELED) {
                    Yii::$app->session->addFlash('danger', Yii::t('common', 'Платеж отменен!'));
                }
            }
        }
        if (!empty($post['sell'])) {
            $userBalance = Yii::$app->user->identity->getPersonalBalance();
            $userDrop = UserDrop::findOne($post['sell']);
            if (!empty($userDrop->box_id) || !empty($userDrop->sets_id)) {
                throw new HttpException(402, Yii::t('common', 'Не подлежит возврату!'));
            }
            if (empty($userDrop) || $userDrop->status !== UserDrop::STATUS_ACTIVE) {
                throw new HttpException(402, Yii::t('common', 'Не найдена вещь в корзине!'));
            }
            $this->_sellUserDrop($userDrop, $userBalance->id);
            Yii::$app->session->addFlash('success', Yii::t('common', 'Предмет успешно продан!'));
        }
        return $this->render('history');
    }

    /**
     * @param UserDrop $userDrop
     */
    private function _sellUserDrop($userDrop, $userBalanceId) {
        /** @var Drop $drop */
        foreach ($userDrop->drop as $drop) {
            $profit = new Profit();
            $profit->status = 1;
            $profit->type = Profit::TYPE_SELL_DROP;
            $profit->amount = $drop->getRealPrice();
            $profit->user_balance_id = $userBalanceId;
            $profit->comment = Yii::t('common', 'Возврат предмета "{PARAMS_PREDNAME}"', [
                'PARAMS_PREDNAME' => Yii::t('database', $drop->name)
            ], 'ru-RU');
            $profit->created_at = date('Y-m-d H:i:s');
            $profit->save(false);
        }
        $userDrop->status = UserDrop::STATUS_SELL;
        $userDrop->save(false);
    }

    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionPartner()
    {
        if (!Yii::$app->user->isGuest && Yii::$app->user->identity->status === User::STATUS_BLOCKED) {
            throw new ForbiddenHttpException(Yii::t('common', 'Ваш аккаунт заблокирован!'));
        }
        return $this->render('partner');
    }

    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionTasks()
    {
        if (!Yii::$app->user->isGuest && Yii::$app->user->identity->status === User::STATUS_BLOCKED) {
            throw new ForbiddenHttpException(Yii::t('common', 'Ваш аккаунт заблокирован!'));
        }
        return $this->render('tasks');
    }

    /**
     * @return string
     */
    public function actionPayment()
    {
        $this->layout = 'service';
//        $tomeApi = Yii::$app->tomeApi->create(100, "Пополнение баланса");
//        print_r($tomeApi);exit;
        $modelForm = new PaymentForm();
        if ($modelForm->load(Yii::$app->request->post())) {
            try {
                $urlConfirm = $modelForm->createOperation();
                if (!empty($urlConfirm)) {
                    return $this->redirect($urlConfirm);
                    //                Yii::$app->session->addFlash('success', 'Успешно');
                }
            } catch (\Exception $e) {
                if ($e->getCode() === 414) {
                    $modelForm->addError('amount', $e->getMessage());
                } else {
                    $modelForm->addError('amount', Yii::t('common', 'Произошла ошибка при оплате!'));
                }
            }
        }
        return $this->renderAjax('payment', [
            'modelForm' => $modelForm
        ]);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();
        return $this->goHome();
    }

    public function actionGetBalance()
    {
        $result = [
            'balanceStr' => Yii::$app->user->identity->getPersonalBalance()->getBalanceFormat(),
            'balance' => Yii::$app->user->identity->getPersonalBalance()->balanceCeil
        ];
        header("Content-Type: application/json");
        return json_encode($result);
    }

    public function actionGetAchievement($type)
    {
        if (!in_array($type, array_keys(Task::getTypeList()))) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Задание не выполнено!'));
            return $this->redirect('tasks');
        }
        $user = Yii::$app->user->identity;
        $tasks = Task::getTasksByUser($user, $type);
        $taskAvailable = null;
        foreach ($tasks as $task) {
            if ($task['status'] === 1) {
                $taskAvailable = $task;
                break;
            }
        }
        if (empty($taskAvailable)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Задание не выполнено!'));
            return $this->redirect('tasks');
        }

        if ($taskAvailable['drop_id'] === 843) {
            UserTask::createRecord($user->id, $taskAvailable['id']);
            $userBalance = $user->getPersonalBalance();
            $model = new Profit();
            $model->user_balance_id   = $userBalance->id;
            $model->amount            = $taskAvailable['count'];
            $model->type              = $type;
            $model->comment           = Yii::t('common', 'Выполнение задания', [], 'ru-RU');
            $model->status            = 1;
            $model->save();
            $userBalance->recalculateBalance();
            Yii::$app->session->addFlash('success', Yii::t('common', 'Награда успешно получена'));
        } else {
            UserTask::createRecord($user->id, $taskAvailable['id']);
            UserDrop::createRecord($user->id, $taskAvailable['drop_id'], 14, null,UserDrop::STATUS_ACTIVE, false, $taskAvailable['count']);
            Yii::$app->session->addFlash('success', Yii::t('common', 'Ежедневная награда успешно получена'));
        }

        return $this->redirect('tasks');
    }

    public function actionGetDailyReward()
    {
        $user = Yii::$app->user->identity;
        $userBalance = $user->getPersonalBalance();
        $exists = Profit::find()
                        ->andWhere(['user_balance_id' => $userBalance->id])
                        ->andWhere(['IN', 'type', [Profit::TYPE_DAILY_REWARD_LIST, Profit::TYPE_DAILY_REWARD_LIST_BOX_SMALL, Profit::TYPE_DAILY_REWARD_LIST_BOX_BIG]])
                        ->andWhere(['>=', 'created_at', (new \DateTime())->format('Y-m-d 00:00:01')])
                        ->andWhere(['<=', 'created_at', (new \DateTime())->format('Y-m-d 23:59:59')])
                        ->exists();

        if ($exists) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Вы уже получали сегодня ежедневный бонус'));
            return $this->redirect('tasks');
        }

        $dailyReward = null;
        foreach (Task::getDailyRewardList($user) as $item) {
            if (!empty($item['status']) && $item['status'] === 'available') {
                $dailyReward = $item;
                break;
            }
        }

        if (empty($dailyReward)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Нет наград для получения'));
            return $this->redirect('tasks');
        }

        $type = Profit::TYPE_DAILY_REWARD_LIST;
        if (!empty($dailyReward['type']) && $dailyReward['type'] === 'gift_small') {
            $type = Profit::TYPE_DAILY_REWARD_LIST_BOX_SMALL;
        } elseif (!empty($dailyReward['type']) && $dailyReward['type'] === 'gift_big') {
            $type = Profit::TYPE_DAILY_REWARD_LIST_BOX_BIG;
        }

        if ($dailyReward['drop_id'] === 843) {
            $model = new Profit();
            $model->user_balance_id   = $userBalance->id;
            $model->amount            = $dailyReward['amount'];
            $model->type              = $type;
            $model->comment           = Yii::t('common', 'Ежедневная награда', [], 'ru-RU');
            $model->status            = 1;
            $model->save();
            $userBalance->recalculateBalance();
            Yii::$app->session->addFlash('success', Yii::t('common', 'Ежедневная награда успешно получена'));
        } else {
            $model = new Profit();
            $model->user_balance_id   = $userBalance->id;
            $model->amount            = 0;
            $model->type              = $type;
            $model->comment           = Yii::t('common', 'Ежедневная награда', [], 'ru-RU') . " \"{$dailyReward['drop_name']}\"";
            $model->status            = 1;
            $model->save();
            $userBalance->recalculateBalance();
            UserDrop::createRecord($user->id, $dailyReward['drop_id'], 14, null,UserDrop::STATUS_ACTIVE, false, $dailyReward['amount']);
            Yii::$app->session->addFlash('success', Yii::t('common', 'Ежедневная награда успешно получена'));
        }

        return $this->redirect('tasks');
    }
}
