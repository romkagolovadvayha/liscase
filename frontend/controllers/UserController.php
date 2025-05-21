<?php

namespace frontend\controllers;

use common\components\helpers\DateHelper;
use common\controllers\WebController;
use common\models\box\Drop;
use common\models\invoice\Deposit;
use common\models\invoice\PaymentBonuses;
use common\models\profit\Profit;
use common\models\skindrops\Skindrops;
use common\models\tasks\Task;
use common\models\user\User;
use common\models\user\UserDrop;
use common\models\user\UserTask;
use frontend\forms\market\PaymentForm;
use frontend\forms\profile\ProfileForm;
use frontend\forms\promocode\UserPromocodeForm;
use frontend\forms\user\SkinsForm;
use frontend\forms\user\TransferForm;
use frontend\modules\user\SkinsSearch;
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
        $this->view->params['_profile'] = true;
        $this->view->params['page'] = 'user-inventory';
        if (!empty($post['sell'])) {
            $userBalance = Yii::$app->user->identity->getPersonalBalance();
            $userDrop = UserDrop::findOne($post['sell']);
            if (!empty($userDrop->box_id) || !empty($userDrop->sets_id) || !empty($userDrop->parent_drop_id)) {
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
        $this->view->params['_profile'] = true;
        $this->view->params['page'] = 'user-history';
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
            if (!empty($userDrop->box_id) || !empty($userDrop->sets_id) || !empty($userDrop->parent_drop_id)) {
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
        if (!Yii::$app->settings->get('section_referral')) {
            throw new NotFoundHttpException(Yii::t('common', "Страница не найдена"));
        }
        $this->view->params['_profile'] = true;
        $this->view->params['page'] = 'user-partner';
        return $this->render('partner');
    }

    public function actionPartnerBonus($id)
    {
        if (!Yii::$app->settings->get('section_referral') || Yii::$app->user->isGuest) {
            throw new NotFoundHttpException(Yii::t('common', "Страница не найдена"));
        }

        /** @var User $user */
        $user = Yii::$app->user->identity;
        $cacheKey = "partnerBonus_{$user->id}";
        if (Yii::$app->cache->get($cacheKey)) {
            $seconds = Yii::$app->cache->get($cacheKey) - time();
            $secondsWord = DateHelper::numDecline($seconds, 'секунда, секунды, секунд', false);
            Yii::$app->session->addFlash('danger', Yii::t('common', "Вы делаете запросы слишком часто, попробуйте через {PARAM_SECOND} {PARAM_SECOND_WORD}.", [
                'PARAM_SECOND' => $seconds,
                'PARAM_SECOND_WORD' => $secondsWord,
            ]));
            return $this->redirect('/user/partner');
        }
        Yii::$app->cache->set($cacheKey, time() + 5, 5);

        $childUser = User::findOne($id);
        if ($childUser->parent_skin_send && $childUser->userProfile->parent_bonus) {
            Yii::$app->session->addFlash('danger', Yii::t('common', "Награда уже получена!"));
            return $this->redirect('/user/partner');
        }

        if ($childUser->getParentUser()->id !== $user->id) {
            Yii::$app->session->addFlash('danger', Yii::t('common', "Вы не приглашали данного игрока!"));
            return $this->redirect('/user/partner');
        }

        if (!$childUser->hasHourInServer()) {
            Yii::$app->session->addFlash('danger', Yii::t('common', "Игрок еще не отыграл час на сервере!"));
            return $this->redirect('/user/partner');
        }

        if (!$childUser->userProfile->parent_bonus) {
            $childUser->userProfile->parent_bonus = 1;
            $childUser->userProfile->save(false);
            $profit = new Profit();
            $profit->status = 1;
            $profit->type = Profit::TYPE_REFERRAL;
            $profit->amount = Yii::$app->settings->get('referral_bonus');
            $profit->user_balance_id = $user->getPersonalBalance()->id;
            $profit->comment = Yii::t('common', 'Бонус за приглашенного пользователя "{PARAMS_USER_NAME}"', [
                'PARAMS_USER_NAME' => $childUser->username
            ],'ru-RU');
            $profit->created_at = date('Y-m-d H:i:s');
            $profit->save(false);
            $user->getPersonalBalance()->recalculateBalance();
        }
        if (!$childUser->parent_skin_send) {
            $childUser->parent_skin_send = 1;
            $childUser->save(false);
            $skin = null;
            $items = Yii::$app->rustTm->items();
            shuffle($items);
            foreach ($items as $item) {
                $minSum = Yii::$app->settings->get('referral_minSum');
                $maxSum = Yii::$app->settings->get('referral_maxSum');
                if ($item['price'] < $minSum) {
                    continue;
                }
                if ($item['price'] > $maxSum) {
                    continue;
                }
                $skin = $item;
                break;
            }

            $price = $skin['price'];
            $name = $skin['name'];
            $image = $skin['image'];

            $model = new Skindrops();
            $model->name = $name;
            $model->steam_id = $user->steam_id;
            $model->player = $user->username;
            $model->price = ceil($price);
            $model->real_price = ceil($price);
            $model->image = $image;
            $model->created_at = date('Y-m-d H:i:s');
            $model->save(false);

            $model = new Profit();
            $model->user_balance_id = $user->getSkinsBalance()->id;
            $model->amount = ceil($price);
            $model->comment = Yii::t('common', 'Выйгрыш скина');
            $model->status = 1;
            $model->type = Profit::TYPE_WINNER_SKINS;
            $model->created_at = date('Y-m-d H:i:s');
            $model->save(false);
            $user->getSkinsBalance()->recalculateBalance();
        }

        Yii::$app->session->addFlash('success', Yii::t('common', "Награда успешно получена"));
        return $this->redirect('/user/skins');
    }

    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionProfile()
    {
        $this->view->params['_profile'] = true;
        $this->view->params['page'] = 'user-profile';
        $user  = Yii::$app->user->identity;
        $model = ProfileForm::findOne($user->userProfile->id);
        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
            if ($model->saveRecord()) {
                Yii::$app->session->addFlash('success', 'Настройки успешно сохранены');
            } else {
                if (!empty($model->getFirstError('global'))) {
                    Yii::$app->session->addFlash('danger', $model->getFirstError('global'));
                }
            }
        }
        return $this->render('profile', [
            'model' => $model
        ]);
    }

    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionSkins($type = 'rust')
    {
        if (!Yii::$app->settings->get('section_skindrops')) {
            throw new NotFoundHttpException(Yii::t('common', "Страница не найдена"));
        }

        $data = new SkinsSearch();
        $provider = $data->search(Yii::$app->request->get(), $type);

        $form = new SkinsForm();
        if (Yii::$app->request->isPost && $form->load(Yii::$app->request->post())) {
            if ($form->saveRecord()) {
                Yii::$app->session->addFlash('success', Yii::t('common', 'Скин отправляется, ожидайте трейд-обмен'));
                return $this->redirect('/user/skins');
            } else {
                if (!empty($form->getFirstErrors())) {
                    Yii::$app->session->addFlash('danger', array_values($form->getFirstErrors())[0]);
                } else {
                    Yii::$app->session->addFlash('danger', Yii::t('common', 'Ошибка при получении скина'));
                }
                return $this->refresh();
            }
        }

        $this->view->params['_profile'] = true;
        $this->view->params['page'] = 'user-skins';
        return $this->render('skins', [
            'providerSkins' => $provider,
            'filterSkins' => $data,
        ]);
    }

    public function actionTransfer($type)
    {
        /** @var User $user */
        $user = Yii::$app->user->identity;

        $form = new TransferForm();
        $form->type = $type;
        if (empty($form->amount) && $form->type === TransferForm::TYPE_REFERRAL) {
            $balance = $user->getReferralBalance();
            $form->amount = $balance;
        }
        if (empty($form->amount) && $form->type === TransferForm::TYPE_SKINS) {
            $balance = $user->getSkinsBalance();
            $form->amount = $balance->balance;
        }
        if (Yii::$app->request->isPost && $form->load(Yii::$app->request->post())) {
            $balance = $form->saveRecord();
            if ($balance) {
                Yii::$app->session->addFlash('success', Yii::t('common', 'Баланс пополнен на {PARAMS_PROMSUM} RUB', [
                    'PARAMS_PROMSUM' => $balance
                ]));
            } else {
                if (!empty($form->getFirstErrors())) {
                    Yii::$app->session->addFlash('danger', array_values($form->getFirstErrors())[0]);
                } else {
                    Yii::$app->session->addFlash('danger', Yii::t('common', 'Ошибка при переводе средств'));
                }
            }
            if ($form->type === TransferForm::TYPE_REFERRAL) {
                return $this->redirect('/user/partner');
            }
            if ($form->type === TransferForm::TYPE_SKINS) {
                return $this->redirect('/user/skins');
            }
        }
        return $this->renderAjax('transfer', [
            'transferForm' => $form
        ]);
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
        $this->view->params['_profile'] = true;
        $this->view->params['page'] = 'user-task';
        return $this->render('tasks');
    }

    /**
     * @return string|Response
     */
    public function actionPayment()
    {
        $this->layout = 'service';

        $modelForm = new PaymentForm();
        if ($modelForm->load(Yii::$app->request->post())) {
            try {
                $response = $modelForm->createOperation();
                if (!empty($response['paymentURL'])) {
                    return $this->redirect($response['paymentURL']);
                }
                if (!empty($response['template'])) {
                    return $this->renderAjax($response['template'], [
                        'response' => $response
                    ]);
                }
            } catch (\Exception $e) {
                if ($e->getCode() === 414) {
                    $modelForm->addError('amount', $e->getMessage());
                } else {
                    Yii::$app->telegramChats->sendMessage($e->getMessage());
                    $modelForm->addError('amount', Yii::t('common', 'Произошла ошибка при оплате!'));
                }
            }
        }

        $bonuses = PaymentBonuses::find()
            ->orderBy(['amount' => SORT_ASC])
            ->all();

        return $this->renderAjax('payment', [
            'modelForm' => $modelForm,
            'bonuses' => $bonuses,
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
        $tasks = Task::getTasksByUser($user)[$type];
        if ($tasks['status'] !== 'wait-get') {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Задание не выполнено!'));
            return $this->redirect('tasks');
        }

        if ($tasks['info']['drop_id'] === 843) {
            UserTask::createRecord($user->id, $tasks['info']['id']);
            $userBalance = $user->getPersonalBalance();
            $model = new Profit();
            $model->user_balance_id   = $userBalance->id;
            $model->amount            = $tasks['info']['count'];
            $model->type              = $type;
            $model->comment           = Yii::t('common', 'Выполнение задания', [], 'ru-RU');
            $model->status            = 1;
            $model->save();
            $userBalance->recalculateBalance();
        } else {
            UserTask::createRecord($user->id, $tasks['info']['id']);
            UserDrop::createRecord($user->id, $tasks['info']['drop_id'], 14, null,UserDrop::STATUS_ACTIVE, false, $tasks['info']['count']);
        }
        Yii::$app->session->addFlash('success', Yii::t('common', 'Награда \"{PARAM_DROP_NAME}\" успешно получена', [
            'PARAM_DROP_NAME' => $tasks['info']['dropName']
        ]));

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
        foreach (Task::getDailyRewardList($user)['items'] as $item) {
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

        if ($dailyReward['drop_id'] == 843) {
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

    public function actionPromocode()
    {
        $promocodeForm = UserPromocodeForm::findOne(Yii::$app->user->id);
        if ($promocodeForm->load(Yii::$app->request->post())) {
            if ($promocodeForm->saveRecord()) {
                Yii::$app->session->addFlash('success', Yii::t('common', 'Баланс пополнен на {PARAMS_PROMSUM} RUB', [
                    'PARAMS_PROMSUM' => 50
                ]));
            } else {
                Yii::$app->session->addFlash('danger', array_values($promocodeForm->getFirstErrors())[0]);
            }
        }
        return $this->renderAjax('promocode', [
            'promocodeForm' => $promocodeForm
        ]);
    }
}
