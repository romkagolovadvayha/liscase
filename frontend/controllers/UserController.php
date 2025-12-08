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
use common\models\user\UserProfile;
use common\models\user\UserTask;
use common\models\user\UserPayoutSkins;
use common\models\user\UserRaid;
use common\models\user\UserTop;
use common\models\user\UserTree;
use yii\helpers\ArrayHelper;
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
        // Постоянный редирект 301 на /store
        return $this->redirect(['/store'], 301);
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
            $profit->amount = $drop->getRealPrice(false);
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

        if (empty($user->userProfile)) {
            UserProfile::createModel($user, $user->username);
            $user->userProfile->name = $user->username;
            $user->userProfile->avatar = null;
            $user->userProfile->save(false);
        }

        $model = ProfileForm::findOne($user->userProfile->id);
        if (Yii::$app->request->isPost) {
            // Загружаем данные из POST
            $post = Yii::$app->request->post('ProfileForm', []);
            if (!empty($post)) {
                $model->load(Yii::$app->request->post());
                
                // Дополнительно загружаем поля, которые могут не загрузиться через load()
                if (isset($post['youtube_link'])) {
                    $model->youtube_link = $post['youtube_link'];
                }
                if (isset($post['twitch_link'])) {
                    $model->twitch_link = $post['twitch_link'];
                }
                if (isset($post['vk_link'])) {
                    $model->vk_link = $post['vk_link'];
                }
                if (isset($post['telegram_link'])) {
                    $model->telegram_link = $post['telegram_link'];
                }
                // Для чекбоксов: если значение есть в POST, берем его (может быть массивом [0, 1])
                if (isset($post['is_hide_online'])) {
                    $value = $post['is_hide_online'];
                    // Если это массив, берем последнее значение (1 если отмечен)
                    if (is_array($value)) {
                        $model->is_hide_online = (int)end($value);
                    } else {
                        $model->is_hide_online = (int)$value;
                    }
                } else {
                    $model->is_hide_online = 0;
                }
                if (isset($post['is_hide_team'])) {
                    $value = $post['is_hide_team'];
                    // Если это массив, берем последнее значение (1 если отмечен)
                    if (is_array($value)) {
                        $model->is_hide_team = (int)end($value);
                    } else {
                        $model->is_hide_team = (int)$value;
                    }
                } else {
                    $model->is_hide_team = 0;
                }
            }
            
            // Логируем данные перед сохранением для отладки
            Yii::info('ProfileForm data before save: ' . json_encode([
                'youtube_link' => $model->youtube_link,
                'twitch_link' => $model->twitch_link,
                'vk_link' => $model->vk_link,
                'telegram_link' => $model->telegram_link,
                'is_hide_online' => $model->is_hide_online,
                'is_hide_team' => $model->is_hide_team,
            ]));
            
            if ($model->saveRecord()) {
                Yii::$app->session->setFlash('success', Yii::t('common', 'Настройки успешно сохранены'));
            } else {
                $errors = $model->getErrors();
                Yii::error('ProfileForm save failed with errors: ' . json_encode($errors));
                if (!empty($errors['global'])) {
                    Yii::$app->session->setFlash('danger', $errors['global'][0]);
                } else {
                    // Показываем первую ошибку валидации
                    $firstError = reset($errors);
                    if (!empty($firstError[0])) {
                        Yii::$app->session->setFlash('danger', $firstError[0]);
                    } else {
                        Yii::$app->session->setFlash('danger', Yii::t('common', 'Ошибка при сохранении настроек'));
                    }
                }
            }
        }
        return $this->render('profile', [
            'model' => $model
        ]);
    }

    /**
     * Форма модального окна для редактирования социальных ссылок
     * 
     * @return string
     */
    public function actionSocialLinksModal()
    {
        if (Yii::$app->user->isGuest) {
            throw new ForbiddenHttpException(Yii::t('common', 'Доступ запрещен'));
        }

        $user = Yii::$app->user->identity;
        $profile = $user->userProfile;
        
        if (!$profile) {
            $profile = new UserProfile();
            $profile->user_id = $user->id;
        }

        // Проверяем наличие VIP
        $hasVip = $user->hasVip();
        
        // Ищем VIP товар для ссылки на покупку
        $vipDrop = \common\models\box\Drop::find()
            ->where(['drop_type' => \common\models\box\Drop::TYPE_VIP])
            ->andWhere(['market_status' => \common\models\box\Drop::MARKET_STATUS_ACTIVE])
            ->andWhere(['status' => \common\models\box\Drop::STATUS_ACTIVE])
            ->orderBy(['sort' => SORT_ASC])
            ->one();

        return $this->renderAjax('_social_links_form', [
            'profile' => $profile,
            'hasVip' => $hasVip,
            'vipDrop' => $vipDrop,
        ]);
    }

    /**
     * Сохранение социальных ссылок
     * 
     * @return \yii\web\Response
     */
    public function actionSocialLinks()
    {
        if (Yii::$app->user->isGuest) {
            throw new ForbiddenHttpException(Yii::t('common', 'Доступ запрещен'));
        }

        $user = Yii::$app->user->identity;
        $profile = $user->userProfile;
        
        if (!$profile) {
            $profile = new UserProfile();
            $profile->user_id = $user->id;
        }

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            
            $profile->youtube_link = !empty($post['youtube_link']) ? trim($post['youtube_link']) : null;
            $profile->twitch_link = !empty($post['twitch_link']) ? trim($post['twitch_link']) : null;
            $profile->vk_link = !empty($post['vk_link']) ? trim($post['vk_link']) : null;
            $profile->telegram_link = !empty($post['telegram_link']) ? trim($post['telegram_link']) : null;
            
            // is_hide_online можно установить только если есть VIP
            if ($user->hasVip()) {
                $profile->is_hide_online = !empty($post['is_hide_online']) ? 1 : 0;
            } else {
                // Если нет VIP, сбрасываем флаг
                $profile->is_hide_online = 0;
            }
            
            // is_hide_team можно установить только если есть VIP
            if ($user->hasVip()) {
                $profile->is_hide_team = !empty($post['is_hide_team']) ? 1 : 0;
            } else {
                // Если нет VIP, сбрасываем флаг
                $profile->is_hide_team = 0;
            }

            if ($profile->validate() && $profile->save()) {
                if (Yii::$app->request->isAjax) {
                    Yii::$app->response->format = Response::FORMAT_JSON;
                    return ['success' => true, 'message' => Yii::t('common', 'Социальные ссылки успешно сохранены')];
                }
                Yii::$app->session->setFlash('success', Yii::t('common', 'Социальные ссылки успешно сохранены'));
            } else {
                $errors = $profile->getFirstErrors();
                $errorMessage = !empty($errors) ? array_values($errors)[0] : Yii::t('common', 'Ошибка при сохранении');
                if (Yii::$app->request->isAjax) {
                    Yii::$app->response->format = Response::FORMAT_JSON;
                    return ['success' => false, 'message' => $errorMessage];
                }
                Yii::$app->session->setFlash('danger', $errorMessage);
            }
        }

        return $this->redirect(Yii::$app->request->referrer ?: '/user/profile');
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
        if ($type == 'rust') {
            $this->view->params['page'] = 'user-skins-rust';
            $form->market = Yii::$app->rustTm;
        } else {
            $type = 'cs2';
            $this->view->params['page'] = 'user-skins-csgo';
            $form->market = Yii::$app->csGoMarket;
        }
        $form->type = $type;
        if (Yii::$app->request->isPost && $form->load(Yii::$app->request->post())) {
            if ($form->saveRecord()) {
                Yii::$app->session->addFlash('success', Yii::t('common', 'Скин отправляется, ожидайте трейд-обмен'));
                return $this->redirect('/user/skins?type=' . $type);
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
        return $this->render('skins', [
            'providerSkins' => $provider,
            'filterSkins' => $data,
            'form' => $form,
        ]);
    }

    public function actionSkinConfirm($id, $type = 'rust')
    {
        if (!Yii::$app->settings->get('section_skindrops')) {
            throw new NotFoundHttpException(Yii::t('common', "Страница не найдена"));
        }

        $user = Yii::$app->user->identity;
        
        if ($type == 'rust') {
            $market = Yii::$app->rustTm;
        } else {
            $market = Yii::$app->csGoMarket;
        }
        
        $data = $market->items();
        if (empty($data[$id])) {
            throw new NotFoundHttpException(Yii::t('common', "Скин не найден"));
        }
        
        $item = $data[$id];
        $balance = $user->getSkinsBalance();
        
        if ($item['price'] > $balance->balance) {
            throw new NotFoundHttpException(Yii::t('common', "Недостаточно средств"));
        }
        
        $formModel = new SkinsForm();
        $formModel->market = $market;
        $formModel->type = $type;
        $formModel->id = $id;
        $formModel->amount = $item['price'];
        
        if (Yii::$app->request->isPost && $formModel->load(Yii::$app->request->post())) {
            if ($formModel->saveRecord()) {
                Yii::$app->session->addFlash('success', Yii::t('common', 'Скин отправляется, ожидайте трейд-обмен'));
                // Закрываем модальное окно и обновляем страницу через JS
                if (Yii::$app->request->isPjax) {
                    return '<script>
                        if (typeof $ !== "undefined" && $.fn.modal) {
                            $(".modal").modal("hide");
                        }
                        window.location.reload();
                    </script>';
                }
                return $this->redirect('/user/skins?type=' . $type);
            } else {
                if (!empty($formModel->getFirstErrors())) {
                    Yii::$app->session->addFlash('danger', array_values($formModel->getFirstErrors())[0]);
                } else {
                    Yii::$app->session->addFlash('danger', Yii::t('common', 'Ошибка при получении скина'));
                }
            }
        }
        
        return $this->renderAjax('skin-confirm', [
            'item' => $item,
            'formModel' => $formModel,
            'balance' => $balance->balance,
            'type' => $type,
        ]);
    }

    public function actionSkinsOperations()
    {
        if (!Yii::$app->settings->get('section_skindrops')) {
            throw new NotFoundHttpException(Yii::t('common', "Страница не найдена"));
        }

        $user = Yii::$app->user->identity;

        $skinCount = Skindrops::find()
                            ->andWhere(['steam_id' => $user->steam_id])
                            ->count();

        $skins = Skindrops::find()
                                           ->select([
                                                        'name' => 'name',
                                                        'image' => 'image',
                                                        'amount' => 'real_price',
                                                        'created_at' => 'created_at'
                                           ])
                                           ->andWhere(['steam_id' => $user->steam_id])
                                           ->asArray()
                                           ->orderBy(['created_at' => SORT_DESC])
                                           ->all();

        // Добавление текста в поле comment
        foreach ($skins as &$skin) {
            $skin['status'] = Yii::t('common', "Зачислено");
            $skin['statusKey'] = null; // У скинов нет statusKey
        }

        $payouts = UserPayoutSkins::find()
         ->select([
            'statusKey' => 'status',
            'amount',
            'image',
            'image300',
            'name',
            'created_at'
         ])
        ->andWhere(['user_id' => $user->id])
        ->asArray()
        ->orderBy(['created_at' => SORT_DESC])
        ->all();

        // Добавление текста в поле comment
        foreach ($payouts as &$payout) {
            $payout['amount'] = $payout['amount'] * (-1);
            $payout['status'] = ArrayHelper::getValue(UserPayoutSkins::getStatusList(), $payout['statusKey']);
            if ($payout['statusKey'] == UserPayoutSkins::STATUS_REJECT) {
                $payout['amount'] = 0;
            }
        }

        $personalBalance = $user->getPersonalBalance();
        $transfers = Profit::find()
                       ->select([
                            'amount',
                            'created_at'
                       ])
                       ->andWhere(['IN', 'type', [Profit::TYPE_TRANSFER_SKINS]])
                       ->andWhere(['user_balance_id' => $personalBalance->id])
                       ->asArray()
                       ->orderBy(['created_at' => SORT_DESC])
                       ->all();

        // Добавление текста в поле comment
        foreach ($transfers as &$transfer) {
            $transfer['amount'] = $transfer['amount'] * (-1);
            $transfer['status'] = Yii::t('common', "Перевод в магазин");
            $transfer['image'] = null;
            $transfer['name'] = null;
        }

        $items = ArrayHelper::merge($payouts, $skins);
        $items = ArrayHelper::merge($items, $transfers);

        $dataProvider = new \yii\data\ArrayDataProvider([
                                                        'allModels' => $items,
                                                        'totalCount' => count($items),
                                                        'pagination' => [
                                                            'pageSize' => 10,
                                                        ],
                                                        'sort'  => [
                                                            'attributes' => ['created_at', 'amount'],
                                                            'defaultOrder' => ['created_at' => SORT_DESC],
                                                        ],
                                                    ]);

        return $this->renderAjax('operations', [
            'dataProvider' => $dataProvider,
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
     * Редирект 301 со старой версии заданий на новую
     * @return \yii\web\Response
     */
    public function actionTasks()
    {
        // Постоянный редирект 301 на новую версию заданий
        return $this->redirect(['/tasks-v2'], 301);
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
        // Постоянный редирект 301 на новую версию заданий
        return $this->redirect(['/tasks-v2'], 301);
    }

    public function actionGetDailyReward()
    {
        // Постоянный редирект 301 на новую версию заданий
        return $this->redirect(['/tasks-v2'], 301);
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

    /**
     * Генерация изображения итогов года
     * @return Response
     */
    public function actionYearSummary()
    {
        $user = Yii::$app->user->identity;
        if (!$user) {
            throw new NotFoundHttpException(Yii::t('common', 'Пользователь не найден'));
        }

        // Собираем статистику
        $stats = $this->collectYearStats($user);

        // Генерируем изображение
        $image = $this->generateYearSummaryImage($user, $stats);

        // Возвращаем изображение
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'image/png');
        Yii::$app->response->headers->set('Content-Disposition', 'inline; filename="year-summary-' . $user->id . '.png"');
        Yii::$app->response->data = $image;
        
        return Yii::$app->response;
    }

    /**
     * Сбор статистики за все время
     * @param User $user
     * @return array
     */
    private function collectYearStats($user)
    {
        $stats = [];

        // Сумма выигранных скинов
        $skinsBalance = $user->getSkinsBalance();
        $wonSkinsSum = Profit::find()
            ->andWhere(['user_balance_id' => $skinsBalance->id])
            ->andWhere(['type' => Profit::TYPE_WINNER_SKINS])
            ->sum('amount') ?: 0;
        $stats['won_skins_sum'] = (int)$wonSkinsSum;

        // Количество выведенных скинов Rust
        $stats['skins_rust_count'] = UserPayoutSkins::find()
            ->andWhere(['user_id' => $user->id])
            ->andWhere(['type' => 'rust'])
            ->andWhere(['IN', 'status', [UserPayoutSkins::STATUS_SUCCESS, UserPayoutSkins::STATUS_NEW, UserPayoutSkins::STATUS_WAIT]])
            ->count();

        // Количество выведенных скинов CS2
        $stats['skins_cs2_count'] = UserPayoutSkins::find()
            ->andWhere(['user_id' => $user->id])
            ->andWhere(['type' => 'cs2'])
            ->andWhere(['IN', 'status', [UserPayoutSkins::STATUS_SUCCESS, UserPayoutSkins::STATUS_NEW, UserPayoutSkins::STATUS_WAIT]])
            ->count();

        // Количество рейдов (сделал)
        $stats['raids_done'] = UserRaid::find()
            ->andWhere(['user_id' => $user->id])
            ->count();

        // Количество раз, когда зарейдили (steam_id в owners)
        $stats['raids_received'] = UserRaid::find()
            ->andWhere(['LIKE', 'owners', $user->steam_id])
            ->count();

        // Количество ежедневных наград
        $personalBalance = $user->getPersonalBalance();
        $stats['daily_rewards'] = Profit::find()
            ->andWhere(['user_balance_id' => $personalBalance->id])
            ->andWhere(['IN', 'type', [
                Profit::TYPE_DAILY_REWARD_LIST,
                Profit::TYPE_DAILY_REWARD_LIST_BOX_SMALL,
                Profit::TYPE_DAILY_REWARD_LIST_BOX_BIG
            ]])
            ->count();

        // Количество приглашенных людей
        $stats['referrals_count'] = UserTree::find()
            ->andWhere(['parent_user_id' => $user->id])
            ->count();

        // Топы (позиции 1-3)
        // Получаем все топы пользователя
        $userTops = UserTop::find()
            ->andWhere(['user_id' => $user->id])
            ->andWhere(['IN', 'key', array_keys(UserTop::getTopsLabel())])
            ->all();
        
        $topPositions = [];
        $topsLabel = UserTop::getTopsLabel();
        
        foreach ($userTops as $userTop) {
            // Подсчитываем позицию: сколько пользователей имеют значение больше
            $position = UserTop::find()
                ->andWhere(['key' => $userTop->key])
                ->andWhere(['server_id' => $userTop->server_id])
                ->andWhere(['wipe' => $userTop->wipe])
                ->andWhere(['>', 'value', $userTop->value])
                ->count() + 1; // +1 потому что позиция начинается с 1
            
            if ($position <= 3) {
                $topLabel = $topsLabel[$userTop->key] ?? $userTop->key;
                $topPositions[] = [
                    'position' => $position,
                    'type' => $topLabel,
                    'server' => $userTop->server->name ?? '',
                ];
            }
        }
        $stats['top_positions'] = $topPositions;
        $stats['top_count'] = count($topPositions);

        return $stats;
    }

    /**
     * Генерация изображения итогов года
     * @param User $user
     * @param array $stats
     * @return string
     */
    private function generateYearSummaryImage($user, $stats)
    {
        // Размеры изображения
        $width = 1024;
        $height = 768;
        
        // Путь к шрифту Roboto (поддерживает кириллицу)
        $fontPath = Yii::getAlias('@frontend/assets/sources/css/fonts/Roboto-Regular.ttf');
        $fontBoldPath = Yii::getAlias('@frontend/assets/sources/css/fonts/Roboto-Bold.ttf');
        
        // Если шрифт не найден, используем системный
        if (!file_exists($fontPath)) {
            $fontPath = $this->findSystemFont();
        }
        
        // Создаем изображение
        $image = imagecreatetruecolor($width, $height);
        
        // Включаем альфа-канал
        imagealphablending($image, true);
        imagesavealpha($image, false);
        
        // Цвета
        $bgColor = imagecolorallocate($image, 18, 18, 28); // Темный фон
        $cardBgColor = imagecolorallocate($image, 28, 28, 40); // Фон карточек
        $cardBorderColor = imagecolorallocate($image, 50, 50, 65); // Граница карточек
        $textColor = imagecolorallocate($image, 255, 255, 255); // Белый текст
        $accentColor = imagecolorallocate($image, 235, 12, 53); // Акцентный цвет
        $secondaryColor = imagecolorallocate($image, 160, 160, 180); // Вторичный текст
        $goldColor = imagecolorallocate($image, 255, 215, 0); // Золотой
        $silverColor = imagecolorallocate($image, 192, 192, 192); // Серебряный
        $bronzeColor = imagecolorallocate($image, 205, 127, 50); // Бронзовый
        
        // Заливаем фон
        imagefill($image, 0, 0, $bgColor);
        
        // Градиент сверху
        for ($i = 0; $i < 250; $i++) {
            $alpha = (int)(80 * (1 - $i / 250));
            $gradientColor = imagecolorallocatealpha($image, 235, 12, 53, $alpha);
            imageline($image, 0, $i, $width, $i, $gradientColor);
        }
        
        // Функция для вывода текста с поддержкой кириллицы
        $drawText = function($text, $x, $y, $color, $fontSize = 20, $bold = false) use ($image, $fontPath, $fontBoldPath) {
            $font = $bold && file_exists($fontBoldPath) ? $fontBoldPath : $fontPath;
            if (file_exists($font)) {
                imagettftext($image, $fontSize, 0, $x, $y, $color, $font, $text);
            } else {
                imagestring($image, 4, $x, $y - 15, $text, $color);
            }
        };
        
        // Функция для вычисления ширины текста
        $getTextWidth = function($text, $fontSize = 20, $bold = false) use ($fontPath, $fontBoldPath) {
            $font = $bold && file_exists($fontBoldPath) ? $fontBoldPath : $fontPath;
            if (file_exists($font)) {
                $bbox = imagettfbbox($fontSize, 0, $font, $text);
                return $bbox[4] - $bbox[0];
            }
            return strlen($text) * 8;
        };
        
        // Функция для обрезки текста, если он не помещается
        $truncateText = function($text, $maxWidth, $fontSize = 20, $bold = false) use ($getTextWidth) {
            $width = $getTextWidth($text, $fontSize, $bold);
            if ($width <= $maxWidth) {
                return $text;
            }
            while ($width > $maxWidth && mb_strlen($text) > 0) {
                $text = mb_substr($text, 0, -1);
                $width = $getTextWidth($text . '...', $fontSize, $bold);
            }
            return $text . '...';
        };
        
        // Функция для рисования карточки
        $drawCard = function($x, $y, $w, $h, $label, $value, $valueColor = null) use ($image, $cardBgColor, $cardBorderColor, $textColor, $secondaryColor, $drawText, $getTextWidth, $truncateText) {
            // Фон карточки
            imagefilledrectangle($image, $x, $y, $x + $w, $y + $h, $cardBgColor);
            // Граница
            imagerectangle($image, $x, $y, $x + $w, $y + $h, $cardBorderColor);
            
            // Метка
            $labelSize = 14;
            $labelText = $truncateText($label, $w - 20, $labelSize);
            $drawText($labelText, $x + 12, $y + 25, $secondaryColor, $labelSize);
            
            // Значение
            $valueSize = 24;
            $valueColor = $valueColor ?: $textColor;
            $valueText = $truncateText($value, $w - 20, $valueSize);
            $valueWidth = $getTextWidth($valueText, $valueSize);
            $valueX = $x + ($w - $valueWidth) / 2;
            $drawText($valueText, $valueX, $y + 55, $valueColor, $valueSize, true);
        };
        
        // Заголовок
        $title = "Итоги " . date('Y');
        $titleSize = 38;
        $titleWidth = $getTextWidth($title, $titleSize, true);
        $titleX = ($width - $titleWidth) / 2;
        $drawText($title, $titleX, 60, $textColor, $titleSize, true);
        
        // Имя пользователя
        $username = mb_substr($user->username, 0, 25);
        $usernameSize = 22;
        $usernameWidth = $getTextWidth($username, $usernameSize);
        $usernameX = ($width - $usernameWidth) / 2;
        $drawText($username, $usernameX, 95, $accentColor, $usernameSize);
        
        // Разделитель
        imageline($image, 60, 120, $width - 60, 120, $cardBorderColor);
        
        // Статистика в карточках (2 колонки)
        $cardWidth = 280;
        $cardHeight = 85;
        $cardGap = 30;
        $startX = ($width - ($cardWidth * 2 + $cardGap)) / 2;
        $startY = 160;
        $rowGap = 20;
        
        $cards = [
            ['label' => 'Выиграно скинов', 'value' => number_format($stats['won_skins_sum'], 0, '.', ' ') . ' монет', 'color' => $goldColor],
            ['label' => 'Скинов Rust', 'value' => (string)$stats['skins_rust_count'], 'color' => null],
            ['label' => 'Скинов CS2', 'value' => (string)$stats['skins_cs2_count'], 'color' => null],
            ['label' => 'Рейдов сделано', 'value' => (string)$stats['raids_done'], 'color' => null],
            ['label' => 'Раз зарейдили', 'value' => (string)$stats['raids_received'], 'color' => null],
            ['label' => 'Ежедневных наград', 'value' => (string)$stats['daily_rewards'], 'color' => null],
            ['label' => 'Приглашено людей', 'value' => (string)$stats['referrals_count'], 'color' => null],
        ];
        
        $row = 0;
        $col = 0;
        foreach ($cards as $card) {
            $x = $startX + $col * ($cardWidth + $cardGap);
            $y = $startY + $row * ($cardHeight + $rowGap);
            
            $drawCard($x, $y, $cardWidth, $cardHeight, $card['label'], $card['value'], $card['color']);
            
            $col++;
            if ($col >= 2) {
                $col = 0;
                $row++;
            }
        }
        
        // Топы внизу
        $topY = $startY + (ceil(count($cards) / 2) * ($cardHeight + $rowGap)) + 30;
        
        if ($stats['top_count'] > 0) {
            // Заголовок топов
            $topTitle = "Достижения в топах";
            $topTitleSize = 20;
            $topTitleWidth = $getTextWidth($topTitle, $topTitleSize, true);
            $topTitleX = ($width - $topTitleWidth) / 2;
            $drawText($topTitle, $topTitleX, $topY, $accentColor, $topTitleSize, true);
            
            $topY += 35;
            
            // Топы в одну строку (максимум 3)
            $topCards = array_slice($stats['top_positions'], 0, 3);
            $topCardWidth = 300;
            $topCardHeight = 70;
            $topCardGap = 20;
            $topStartX = ($width - (count($topCards) * $topCardWidth + (count($topCards) - 1) * $topCardGap)) / 2;
            
            foreach ($topCards as $index => $top) {
                $topX = $topStartX + $index * ($topCardWidth + $topCardGap);
                
                $positionColor = $textColor;
                $positionText = '';
                if ($top['position'] == 1) {
                    $positionText = "1-е место";
                    $positionColor = $goldColor;
                } elseif ($top['position'] == 2) {
                    $positionText = "2-е место";
                    $positionColor = $silverColor;
                } else {
                    $positionText = "3-е место";
                    $positionColor = $bronzeColor;
                }
                
                $topLabel = $truncateText(Yii::t('common', $top['type']), $topCardWidth - 20, 14);
                $topValue = $positionText;
                
                $drawCard($topX, $topY, $topCardWidth, $topCardHeight, $topLabel, $topValue, $positionColor);
            }
        } else {
            $noTopText = "Достижений в топах пока нет";
            $noTopSize = 16;
            $noTopWidth = $getTextWidth($noTopText, $noTopSize);
            $noTopX = ($width - $noTopWidth) / 2;
            $drawText($noTopText, $noTopX, $topY, $secondaryColor, $noTopSize);
        }
        
        // Год внизу
        $year = date('Y');
        $yearSize = 28;
        $yearWidth = $getTextWidth($year, $yearSize, true);
        $yearX = ($width - $yearWidth) / 2;
        $drawText($year, $yearX, $height - 30, $accentColor, $yearSize, true);
        
        // Выводим изображение
        ob_start();
        imagepng($image, null, 9);
        $imageData = ob_get_contents();
        ob_end_clean();
        
        // Освобождаем память
        imagedestroy($image);
        
        return $imageData;
    }
    
    /**
     * Поиск системного шрифта с поддержкой кириллицы
     * @return string|null
     */
    private function findSystemFont()
    {
        // Список возможных путей к системным шрифтам
        $systemFonts = [
            // Windows
            'C:/Windows/Fonts/arial.ttf',
            'C:/Windows/Fonts/tahoma.ttf',
            // Linux
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/TTF/DejaVuSans.ttf',
        ];
        
        foreach ($systemFonts as $font) {
            if (file_exists($font)) {
                return $font;
            }
        }
        
        return null;
    }
}
