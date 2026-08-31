<?php

namespace backend\controllers;

use backend\forms\userProfile\BalanceTransferForm;
use backend\forms\userProfile\BonusForm;
use backend\forms\userProfile\PayoutForm;
use backend\forms\userProfile\RoleForm;
use backend\forms\userProfile\UserDropTransferForm;
use common\components\helpers\Role;
use common\models\rcon\RconTasks;
use common\models\user\UserChecking;
use common\models\user\UserSearch;
use common\models\user\UserTree;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use common\models\user\User;
use backend\components\CrudController;

class UserController extends CrudController
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
                        'actions' => ['run-vip-on-server'],
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR],
                    ],
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'set-user-bool' => ['post'],
                    'run-vip-on-server' => ['post'],
                    'lookup-drop-transfer-recipient' => ['get'],
                ],
            ],
        ]);
    }

    protected function _getSearchClassName()
    {
        return UserSearch::class;
    }

    public function actionIndex()
    {
        $this->_setSearchModel();
        $this->_rememberIndexUrl();

        $this->view->params['showFilters'] = true;
        $this->view->params['searchModel'] = $this->_searchModel;
        $headerActions = [
            [
                'label' => '<i class="fas fa-user-tag"></i> ' . Yii::t('common', 'Предметы пользователей'),
                'url' => ['/user-drop/index'],
                'class' => 'bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];
        if (Yii::$app->user->can(Role::ROLE_ADMIN)) {
            $headerActions[] = [
                'label' => '<i class="fas fa-flag"></i> ' . Yii::t('common', 'Репорты'),
                'url' => ['/reports/index'],
                'class' => 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ];
        }
        $this->view->params['headerActions'] = $headerActions;

        return $this->_renderIndex($this->_getSearchDataProvider());
    }

    public function actionRevoke($parentId, $userId)
    {
        /** @var UserTree $t */
        $t = UserTree::find()
            ->andWhere(['user_id' => $userId])
            ->one();

        $t->parent_user_id = 509;
        $t->save();

        return $this->redirect(['profile', 'userId' => $parentId]);
    }

    /**
     * Список булевых атрибутов user, которые можно менять с профиля (переключатели).
     * @return string[]
     */
    public static function getUserBoolAttributes(): array
    {
        return [
            'status_banned', // виртуальный: переключает status активный/забанен
            'raid_notify',
            'ban_notify',
            'store',
            'is_stats',
            'is_blogger',
            'blocked_support',
        ];
    }

    /**
     * AJAX: установка булевого поля пользователя (переключатель). Сохраняет сразу.
     * POST: userId, attribute, value (0|1)
     */
    public function actionSetUserBool()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $userId = (int) Yii::$app->request->post('userId');
        $attribute = (string) Yii::$app->request->post('attribute');
        $value = (int) Yii::$app->request->post('value');

        if (!in_array($attribute, self::getUserBoolAttributes(), true)) {
            return ['success' => false, 'error' => 'Недоступный атрибут'];
        }
        if (!in_array($value, [0, 1], true)) {
            return ['success' => false, 'error' => 'Значение должно быть 0 или 1'];
        }

        $user = User::findOne($userId);
        if (!$user) {
            return ['success' => false, 'error' => 'Пользователь не найден'];
        }

        // Бан: просто переключаем статус активный/забанен
        if ($attribute === 'status_banned') {
            $user->status = $value ? User::STATUS_BLOCKED : User::STATUS_ACTIVE;
            if ($user->save(false)) {
                return ['success' => true];
            }
            return ['success' => false, 'error' => implode(', ', $user->getFirstErrors())];
        }

        $user->$attribute = $value;
        if ($user->save(false)) {
            // Дублируем постановку джоба: afterSave может не увидеть смену is_blogger в changedAttributes;
            // {@see User::queueDiscordRolesUserJobIfLinked} убирает повтор в том же запросе.
            if ($attribute === 'is_blogger') {
                User::queueDiscordRolesUserJobIfLinked($userId);
            }
            return ['success' => true];
        }
        return ['success' => false, 'error' => implode(', ', $user->getFirstErrors())];
    }

    /**
     * AJAX-поиск получателя перед переносом доступных предметов.
     */
    public function actionLookupDropTransferRecipient($userId, $steamId)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $sender = User::findOne((int) $userId);
        if ($sender === null) {
            return ['success' => false, 'error' => 'Пользователь-отправитель не найден.'];
        }

        $steamId = trim((string) $steamId);
        if (!preg_match('/^\d{8,20}$/', $steamId)) {
            return ['success' => false, 'error' => 'Steam ID должен содержать от 8 до 20 цифр.'];
        }
        if ($steamId === (string) $sender->steam_id) {
            return ['success' => false, 'error' => 'Нельзя перенести предметы на тот же аккаунт.'];
        }

        $recipient = User::find()
            ->with('userProfile')
            ->andWhere(['steam_id' => $steamId])
            ->one();

        if ($recipient === null) {
            return ['success' => false, 'error' => 'Пользователь с таким Steam ID не найден.'];
        }

        return [
            'success' => true,
            'user' => [
                'id' => (int) $recipient->id,
                'username' => (string) $recipient->username,
                'steamId' => (string) $recipient->steam_id,
                'avatar' => (string) $recipient->getAvatar(),
            ],
        ];
    }

    public function actionProfile($userId)
    {
        /** @var User $user */
        $user = User::findOne($userId);
        if (!$user) {
            throw new \yii\web\NotFoundHttpException('Пользователь не найден');
        }

        $forms = [
            'roleForm' => new RoleForm(),
            'bonusForm' => new BonusForm(),
            'payoutForm' => new PayoutForm(),
            'balanceTransferForm' => new BalanceTransferForm(),
            'userDropTransferForm' => new UserDropTransferForm(),
        ];

        foreach ($forms as $form) {
            $form->setUserId($userId);
        }

        $bodyParams = Yii::$app->request->bodyParams;
        $messages = [
            'RoleForm' => 'Роль пользователя успешно изменена!',
            'User' => 'Пользователь успешно изменен!',
            'BonusForm' => 'Бонус успешно начислен!',
            'PayoutForm' => 'Вывод успешно проведен!',
            'BalanceTransferForm' => 'Перевод выполнен.',
            'UserDropTransferForm' => 'Предметы успешно перенесены.',
        ];

        foreach ($messages as $formName => $message) {
            if (!empty($bodyParams[$formName])) {
                if ($formName === 'BalanceTransferForm' && !Yii::$app->user->can(Role::ROLE_ADMIN) && !Yii::$app->user->can(Role::ROLE_MODERATOR)) {
                    continue;
                }
                if ($formName === 'UserDropTransferForm' && !Yii::$app->user->can(Role::ROLE_ADMIN) && !Yii::$app->user->can(Role::ROLE_MODERATOR)) {
                    continue;
                }
                if ($formName === 'PayoutForm' && !Yii::$app->user->can(Role::ROLE_ADMIN)) {
                    continue;
                }
                if ($formName === 'BonusForm' && !Yii::$app->user->can(Role::ROLE_ADMIN) && !Yii::$app->user->can(Role::ROLE_MODERATOR)) {
                    continue;
                }
                $form = $formName === 'User' ? $user : $forms[lcfirst($formName)];
                if ($form->load(Yii::$app->request->post()) && 
                    ($formName === 'User' ? $form->save() : $form->saveRecord())) {
                    if ($formName === 'UserDropTransferForm') {
                        $message = sprintf(
                            'Перенесено записей: %d (суммарное количество: %d).',
                            $form->getTransferredRowsCount(),
                            $form->getTransferredItemsCount()
                        );
                    }
                    Yii::$app->session->addFlash('success', $message);
                    return $this->redirect(['profile', 'userId' => $userId]);
                }
            }
        }

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['showFilters'] = false;
        $btnClass = 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5';
        $headerActions = [
            [
                'label' => '<i class="fas fa-arrow-left"></i> ' . Yii::t('common', 'К списку'),
                'url' => $this->getIndexUrl(),
                'class' => $btnClass,
            ],
        ];
        $isAdmin = Yii::$app->user->can(Role::ROLE_ADMIN);
        $isModerator = Yii::$app->user->can(Role::ROLE_MODERATOR);
        if (($isAdmin || $isModerator) && $user->status === User::STATUS_ACTIVE && !$user->isSwitchIdentityForbidden()) {
            $headerActions[] = [
                'label' => '<i class="fas fa-sign-in-alt"></i> ' . Yii::t('common', 'Войти как пользователь'),
                'url' => ['/user/switch-identity', 'id' => $user->id],
                'class' => 'bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ];
        }
        $this->view->params['headerActions'] = $headerActions;

        return $this->render('profile', array_merge([
            'user' => $user,
        ], $forms));
    }

    /**
     * Выполняет removegroup/addgroup vip_status на сервере, где сейчас играет пользователь.
     * Админы и модераторы. Дни VIP подставляются из оставшегося срока VIP пользователя.
     */
    public function actionRunVipOnServer($userId)
    {
        $user = User::findOne($userId);
        if (!$user) {
            throw new \yii\web\NotFoundHttpException('Пользователь не найден');
        }
        if (!$user->server) {
            Yii::$app->session->addFlash('error', Yii::t('common', 'У пользователя нет текущего сервера.'));
            return $this->redirect(['profile', 'userId' => $userId]);
        }
        $days = $user->getVipDaysLeft();
        $steamId = $user->steam_id;
        $removeCommand = "removegroup {$steamId} vip_status";
        RconTasks::execute($removeCommand, [$user->server->tag]);
        $command = "addgroup {$steamId} vip_status {$days}d";
        $results = RconTasks::executeWithResults($command, [$user->server->tag]);
        $first = reset($results);
        $error = $first && !empty($first['error']) ? $first['error'] : null;
        if ($error) {
            Yii::$app->session->addFlash('error', Yii::t('common', 'Ошибка RCON: {error}', ['error' => $error]));
        } else {
            Yii::$app->session->addFlash('success', Yii::t('common', 'Команда выполнена на сервере: addgroup vip_status {days}d', ['days' => $days]));
        }
        return $this->redirect(['profile', 'userId' => $userId]);
    }

    protected function clearBanlistCache()
    {
        Yii::$app->cache->delete('api_banlist_base');
    }

    public function actionUnban($userId)
    {
        $user = User::findOne($userId);
        if (!$user) {
            throw new \yii\web\NotFoundHttpException('Пользователь не найден');
        }
        $user->unban();
        $this->clearBanlistCache();
        Yii::$app->session->addFlash('success', 'Бан успешно снят!');
        return $this->redirect(['profile', 'userId' => $userId]);
    }

    public function actionCheckingStart($userId)
    {
        $user = User::findOne($userId);
        if (!$user) {
            throw new \yii\web\NotFoundHttpException('Пользователь не найден');
        }

        $moder = Yii::$app->user->identity;
        $model = new UserChecking();
        $model->user_id = $user->id;
        $model->status = UserChecking::STATUS_CHECKING;
        $model->checking_by = $moder->id;
        $model->created_at = date('Y-m-d H:i:s');
        $model->save();

        $command = "iqrs call2 \"{$user->steam_id}\" \"{$moder->discord}\"";
        RconTasks::execute($command);

        Yii::$app->session->addFlash('success', 'Игрок вызван на проверку!');
        return $this->redirect(['profile', 'userId' => $userId]);
    }

    public function actionCheckingStop($userId)
    {
        $user = User::findOne($userId);
        if (!$user) {
            throw new \yii\web\NotFoundHttpException('Пользователь не найден');
        }

        /** @var UserChecking $model */
        $model = UserChecking::find()
            ->andWhere(['user_id' => $user->id])
            ->andWhere(['status' => UserChecking::STATUS_CHECKING])
            ->one();

        if ($model) {
            $model->status = UserChecking::STATUS_DONE;
            $model->done_at = date('Y-m-d H:i:s');
            $model->save();

            $command = "iqrs dismiss2 \"{$user->steam_id}\"";
            RconTasks::execute($command);
        }

        Yii::$app->session->addFlash('success', 'Проверка завершена!');
        return $this->redirect(['profile', 'userId' => $userId]);
    }

    public function actionConfirmPhone($id)
    {
        $user = User::findOne($id);
        $user->userProfile->setPhoneIsConfirmed();

        return $this->redirect($this->getIndexUrl());
    }

    public function actionSwitchIdentity($id)
    {
        $user = User::findOne((int)$id);
        if (!$user) {
            throw new \yii\web\NotFoundHttpException('Пользователь не найден');
        }
        if ($user->isSwitchIdentityForbidden()) {
            Yii::$app->session->addFlash(
                'error',
                Yii::t('common', 'Вход под этим пользователем запрещён.')
            );
            $back = Yii::$app->request->getReferrer();
            return $this->redirect($back ?: $this->getIndexUrl());
        }

        $parentUserId = Yii::$app->user->id;

        if (!$user->getAuthKey()) {
            $user->generateAuthKey();
            $user->save();
        }

        $url = Yii::$app->params['baseUrl'] . '/auth/switch-identity?authKey=' . $user->getAuthKey() . '&parentUser='
               . $parentUserId;

        return $this->redirect($url);
    }

}
