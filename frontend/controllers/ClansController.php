<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\box\SelectImage;
use common\models\clan\ClanSearch;
use common\models\clan\Clan;
use common\models\clan\UserRole;
use common\models\clan\UserClan;
use common\models\clan\ClanQuestion;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\user\UserTop;
use frontend\assets\ClanAsset;
use frontend\forms\clans\ClanForm;
use frontend\forms\clans\LeaveForm;
use frontend\forms\clans\QuestionForm;
use frontend\forms\clans\ClanPageForm;
use frontend\forms\clans\ClanSettingsForm;
use frontend\models\banlist\BansSearch;
use yii\base\BaseObject;
use yii\helpers\Html;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use Yii;
use yii\web\UploadedFile;
use common\models\clan\ClanInvite;
use common\models\clan\ClanPage;
use common\models\clan\ClanResource;
use common\models\user\User;
use yii\web\ForbiddenHttpException;
use yii\helpers\Url;

class ClansController extends WebController
{

    public function actionIndex()
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        $server = null;
        foreach ($servers as $item) {
            $server = $item;
            break;
        }

        if (!Yii::$app->user->isGuest) {
            $server = Yii::$app->user->identity->getCurrentServer();
        }

        Yii::$app->response->redirect('/clans/stats/' . $server->tag, 301);
        Yii::$app->end();
    }

    public function actionClans($serverTag)
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        $server = null;
        foreach ($servers as $item) {
            if ($item->tag === $serverTag) {
                $server = $item;
                break;
            }
        }
        if (empty($server)) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $this->view->params['page'] = 'clans';
        $this->view->params['clan_profile_block'] = true;

        // Создаем Search модель
        $searchModel = new ClanSearch();
        
        // Получаем DataProvider с кэшированием
        $dataProvider = $searchModel->searchClans(Yii::$app->request->queryParams, $server);
        
        // Получаем отфильтрованные данные для дополнительной обработки
        $items = $dataProvider->getModels();

        $avatar = function ($model) {
            return Html::img($model['logo'], ['class' => 'w-48 h-48 min-w-48 min-h-48 rounded-6 object-cover']);
        };
        $title = function ($model) {
            return "<a href=\"{$model['link']}\" class=\"item_param_content\"><div class=\"item_param_content_title\">{$model['title']}</div><div class=\"item_param_content_description\">{$model['description_short']}</div></a>";
        };
        $kills = function ($model) {
            $str = number_format($model['kills'] ?? 0, 0, '.', ' ');
            return "<div class=\"item_param\"><span>{$str}</span></div>";
        };
        $rating = function ($model) {
            $str = number_format($model['rating'] ?? 0, 0, '.', ' ');
            return "<div class=\"item_param\"><span>{$str}</span></div>";
        };
        $usersCount = function ($model) {
            $str = number_format($model['users_count'] ?? 0, 0, '.', ' ');
            return "<div class=\"item_param\"><span>{$str}</span></div>";
        };
        $rocket = function ($model) {
            $str = number_format($model['rocket_basic'] ?? 0, 0, '.', ' ');
            return "<div class=\"item_param\"><span>{$str}</span></div>";
        };
        $c4 = function ($model) {
            $str = number_format($model['c4thrown'] ?? 0, 0, '.', ' ');
            return "<div class=\"item_param\"><span>{$str}</span></div>";
        };
        $scrap = function ($model) {
            $str = number_format($model['scrap'] ?? 0, 0, '.', ' ');
            return "<div class=\"item_param\"><span>{$str}</span></div>";
        };
        $sulfur = function ($model) {
            $str = number_format($model['sulfur.ore'] ?? 0, 0, '.', ' ');
            return "<div class=\"item_param\"><span>{$str}</span></div>";
        };
        $clan = null;
//        if (!Yii::$app->user->isGuest) {
//            $user = Yii::$app->user->identity;
//            $userClans = \common\models\clan\Clan::getUserClansList($server);
//            if (!empty($userClans[$user->id])) {
//                $clan = $userClans[$user->id]->clan;
//            }
//        }

        $values = array_values($items);
        $goldClan = null;
        $silverClan = null;
        $bronzeClan = null;
        if (!empty($values[0])) {
            $goldClan = $values[0];
        }
        if (!empty($values[1])) {
            $silverClan = $values[1];
        }
        if (!empty($values[2])) {
            $bronzeClan = $values[2];
        }
        unset($values);

        return $this->render('clans.twig', [
            'SERVER'  => $server,
            'CLAN'  => $clan,
            'SERVERS'  => $servers,
            'GOLD_CLAN'  => $goldClan,
            'SILVER_CLAN'  => $silverClan,
            'BRONZE_CLAN'  => $bronzeClan,
            'SETTINGS' => Yii::$app->settings,
            'ITEMS' => $items,
            'DATA_PROVIDER' => $dataProvider,
            'SEARCH_MODEL' => $searchModel,
            'func' => [
                'avatar' => $avatar,
                'title' => $title,
                'kills' => $kills,
                'rating' => $rating,
                'users_count' => $usersCount,
                'rocket' => $rocket,
                'c4' => $c4,
                'scrap' => $scrap,
                'sulfur' => $sulfur,
            ],
        ]);
    }

    public function actionProfile($linkHash)
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var Clan $model */
        $model = Clan::find()
            ->andWhere(['link_hash' => $linkHash])
            ->one();

        if (empty($model)) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        $server = null;
        foreach ($servers as $item) {
            $server = $item;
            break;
        }

        if (!Yii::$app->user->isGuest) {
            $server = Yii::$app->user->identity->getCurrentServer();
        }

        if (empty($server)) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $this->view->params['page'] = 'clans';
        $this->view->params['clan_menu_block'] = true;
        $this->view->params['clan'] = $model;
        ClanAsset::register($this->view);

        $user = Yii::$app->user->identity;
        $roles = [];
        if (!empty($user)) {
            $roles = $user->getRolesClan($model->user_id, $model->id);
            $this->view->params['roles'] = $roles;
        }

        $clanStats = null;
        if (!Yii::$app->user->isGuest) {
            $clans = \common\models\clan\Clan::getClans($server);
            if (!empty($clans[$model->id])) {
                $clanStats = $clans[$model->id];
            }
        }

        $images = Statistics::productsImages();
        $names = Statistics::productsNames();

        // Получаем страницы клана
        $pages = ClanPage::find()
            ->andWhere(['clan_id' => $model->id])
            ->orderBy(['sort' => SORT_ASC, 'created_at' => SORT_DESC])
            ->all();

        return $this->render('profile.twig', [
            'SERVER'  => $server,
            'SERVERS'  => $servers,
            'CLAN'  => $model,
            'CLAN_STATS'  => $clanStats,
            'ROLES'  => $roles,
            'IMAGES'  => $images,
            'NAMES'  => $names,
            'PAGES'  => $pages,
            'SETTINGS' => Yii::$app->settings,
        ]);
    }

    public function actionUpload($hash, $type)
    {
        if (!Yii::$app->request->isPut || !Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException( 'The requested page does not exist.');
        }
        $putData = Yii::$app->request->getRawBody();
        $fileSize = strlen($putData); // Размер файла в байтах
        $maxFileSize = 3 * 1024 * 1024; // 3 МБ в байтах

        if ($fileSize > $maxFileSize) {
            Yii::$app->response->statusCode = 415;
            echo Yii::t('common', 'Файл превышает допустимый размер (3 МБ)');
            return null;
        }

        // Получаем имя файла из заголовка
        $fileName = $_SERVER['HTTP_X_FILE_NAME'] ?? 'uploaded_file';

        // Проверяем, является ли файл изображением
        $imageInfo = getimagesizefromstring($putData);
        if ($imageInfo === false) {
            Yii::$app->response->statusCode = 415;
            echo Yii::t('common', 'Файл не является изображением');
            return null;
        }

        // Допустимые MIME-типы изображений
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($imageInfo['mime'], $allowedMimeTypes)) {
            Yii::$app->response->statusCode = 415;
            echo Yii::t('common', 'Недопустимый тип изображения. Разрешены только JPEG, PNG и GIF');
            return null;
        }

        /** @var Clan $model */
        $model = Clan::find()
            ->andWhere(['link_hash' => $hash])
            ->one();
        if (empty($model)) {
            Yii::$app->response->statusCode = 403;
            echo Yii::t('common', 'Нет доступа');
            return null;
        }

        $user = Yii::$app->user->identity;
        $roles = $user->getRolesClan($model->user_id, $model->id);
        if (!in_array(UserRole::ROLE_EDIT_INFO, array_keys($roles))) {
            Yii::$app->response->statusCode = 403;
            echo Yii::t('common', 'Нет доступа');
            return null;
        }

        $fileUrl = $this->_loadImage($putData, $fileName, $model->id);

        if ($type == 'background') {
            $model->background_image = $fileUrl;
            $model->save();
        }
        if ($type == 'logo') {
            $model->logo_image = $fileUrl;
            $model->save();
        }

        return $fileUrl;
    }

    private function _loadImage($putData, $fileName, $id) {
        if (empty($putData)) {
            return null;
        }
        $exp = explode('.', $fileName);
        $exp = $exp[count($exp) - 1];
        if (!in_array($exp, ['svg', 'png', 'jpg', 'jpeg', 'webp', 'gif'])) {
            Yii::$app->response->statusCode = 415;
            echo Yii::t('common', 'Недопустимый тип изображения. Разрешены только JPEG, PNG и GIF');
            return null;
        }
        $uploadDir = Yii::getAlias('@frontend/web');
        $fileUrl = "/uploads/clans/" . $id . "_" . md5(time()) . ".{$exp}";
        $filePath = $uploadDir . $fileUrl;
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath));
            chmod(dirname($filePath), 0777);
        }
        file_put_contents($filePath, $putData);
        return $fileUrl;
    }

    public function actionCreate()
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Вы не авторизованы!'));
            return $this->redirect(['index']);
        }

        $model = new ClanForm();
        if ($this->request->isPost) {
            $server = $user->getCurrentServer();
            if ($model->load($this->request->post()) && $model->saveRecord()) {
                Yii::$app->session->addFlash('success', Yii::t('common', 'Клан успешно создан!'));
                return $this->redirect([$model->getLink('profile')]);
            }
        } else {
            $model->loadDefaultValues();
        }

        return $this->renderAjax('clan-form', [
            'model' => $model,
        ]);
    }

    public function actionLeave($hash)
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Вы не авторизованы!'));
            return $this->redirect(['index']);
        }

        /** @var LeaveForm $model */
        $model = LeaveForm::find()
            ->andWhere(['link_hash' => $hash])
            ->one();

        if (empty($model)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Клан не найден!'));
            return $this->redirect(['index']);
        }

        if ($this->request->isPost) {
            $server = $model->user->getCurrentServer();
            if ($model->saveRecord()) {
                if (!empty($model->id)) {
                    Yii::$app->session->addFlash('success', Yii::t('common', 'Вы вышли из клана'));
                    return $this->redirect([$model->getLink('profile')]);
                } else {
                    Yii::$app->session->addFlash('success', Yii::t('common', 'Клан удален, так как вы единственный участник'));
                    return $this->redirect(['/clans']);
                }
            } else {
                if (!empty($model->getFirstError('global'))) {
                    Yii::$app->session->addFlash('danger', $model->getFirstError('global'));
                }
                return $this->redirect([$model->getLink('profile')]);
            }
        }

        return $this->renderAjax('leave-form', [
            'model' => $model,
        ]);
    }

    public function actionQuestion($hash)
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Вы не авторизованы!'));
            return $this->redirect(['index']);
        }

        /** @var Clan $clan = */
        $clan = Clan::find()
            ->andWhere(['link_hash' => $hash])
            ->one();

        if (empty($clan)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Клан не найден!'));
            return $this->redirect(['index']);
        }

        // Проверяем, открыт ли набор в клан
        if (!$clan->is_open) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Набор в этот клан закрыт! Вступить можно только по приглашению.'));
            return $this->redirect([$clan->getLink('profile')]);
        }

        $model = new QuestionForm();
        $model->clan_id = $clan->id;
        $model->user_id = $user->id;
        if ($this->request->isPost) {
            $server = $clan->user->getCurrentServer();
            $model->load($this->request->post());
            if ($model->saveRecord()) {
                Yii::$app->session->addFlash('success', Yii::t('common', 'Заявка отправлена'));
                return $this->redirect([$model->clan->getLink('profile')]);
            } else {
                if (!empty($model->getFirstError('global'))) {
                    Yii::$app->session->addFlash('danger', $model->getFirstError('global'));
                    return $this->redirect([$model->clan->getLink('profile')]);
                }
            }
        }

        return $this->renderAjax('question-form', [
            'model' => $model,
        ]);
    }

    public function actionInvite($hash)
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Вы не авторизованы!'));
            return $this->redirect(['index']);
        }

        /** @var Clan $clan */
        $clan = Clan::find()
            ->andWhere(['link_hash' => $hash])
            ->one();

        if (empty($clan)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Клан не найден!'));
            return $this->redirect(['index']);
        }

        // Проверяем права на создание приглашений
        $roles = $user->getRolesClan($clan->user_id, $clan->id);
        if (!in_array(UserRole::ROLE_INVITE, array_keys($roles)) && $clan->user_id != $user->id) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'У вас нет прав для создания приглашений!'));
            return $this->redirect([$clan->getLink('profile')]);
        }

        $this->view->params['page'] = 'clan-invites';
        // Получаем или создаем приглашение для текущего пользователя
        $invite = ClanInvite::createOrGetInvite($clan->id, $user->id);

        // Получаем статистику приглашений
        $inviteStats = $this->getInviteStats($clan->id);

        return $this->render('invites.twig', [
            'CLAN' => $clan,
            'INVITE' => $invite,
            'INVITE_STATS' => $inviteStats,
            'roles' => $roles,
        ]);
    }

    public function actionAcceptInvite($inviteHash)
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Вы не авторизованы!'));
            return $this->redirect(['index']);
        }

        /** @var ClanInvite $invite */
        $invite = ClanInvite::findByHash($inviteHash);
        if (empty($invite)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Приглашение не найдено или недействительно!'));
            return $this->redirect(['index']);
        }

        // Проверяем, не состоит ли уже пользователь в клане
        $existingMembership = UserClan::find()
            ->andWhere(['user_id' => $user->id])
            ->andWhere(['status' => 1])
            ->exists();

        if ($existingMembership) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Вы уже состоите в клане!'));
            return $this->redirect([$invite->clan->getLink('profile')]);
        }

        // Показываем страницу подтверждения
        $this->view->params['page'] = 'clan-invite-confirm';
        
        return $this->render('invite-confirm.twig', [
            'CLAN' => $invite->clan,
            'INVITE' => $invite,
            'SERVER' => $invite->clan->user->getCurrentServer(),
        ]);
    }

    public function actionConfirmInvite($inviteHash)
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Вы не авторизованы!')]);
        }

        /** @var ClanInvite $invite */
        $invite = ClanInvite::findByHash($inviteHash);
        if (empty($invite)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Приглашение не найдено или недействительно!')]);
        }

        // Проверяем, не состоит ли уже пользователь в клане
        $existingMembership = UserClan::find()
            ->andWhere(['user_id' => $user->id])
            ->andWhere(['status' => 1])
            ->exists();

        if ($existingMembership) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Вы уже состоите в клане!')]);
        }

        // Добавляем пользователя в клан через приглашение
        $userClan = new UserClan();
        $userClan->user_id = $user->id;
        $userClan->steam_id = $user->steam_id;
        $userClan->clan_id = $invite->clan_id;
        $userClan->clan_invite_id = $invite->id;
        $userClan->status = 1;
        $userClan->created_at = date('Y-m-d H:i:s');
        $userClan->save();

        // Назначаем роль ROLE_MEMBER новому участнику
        $userRole = new UserRole();
        $userRole->user_id = $user->id;
        $userRole->clan_id = $invite->clan_id;
        $userRole->role = UserRole::codes()[UserRole::ROLE_MEMBER];
        $userRole->created_at = date('Y-m-d H:i:s');
        $userRole->save();

        // Увеличиваем счетчик участников
        $invite->clan->user_count += 1;
        $invite->clan->save();

        return $this->asJson([
            'success' => true, 
            'message' => Yii::t('common', 'Вы успешно вступили в клан по приглашению!'),
            'redirect_url' => $invite->clan->getLink('profile')
        ]);
    }

    public function actionRegenerateInvite($hash)
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Вы не авторизованы!')]);
        }

        /** @var Clan $clan */
        $clan = Clan::find()
            ->andWhere(['link_hash' => $hash])
            ->one();

        if (empty($clan)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Клан не найден!')]);
        }

        // Проверяем права на создание приглашений
        $roles = $user->getRolesClan($clan->user_id, $clan->id);
        if (!in_array(UserRole::ROLE_INVITE, array_keys($roles)) && $clan->user_id != $user->id) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'У вас нет прав для создания приглашений!')]);
        }

        // Получаем или создаем приглашение
        $invite = ClanInvite::createOrGetInvite($clan->id, $user->id);
        
        // Генерируем новый хеш
        $invite->invite_hash = md5(uniqid(mt_rand(), true));
        $invite->save();

        return $this->asJson([
            'success' => true,
            'message' => Yii::t('common', 'Ссылка приглашения обновлена!'),
            'invite_link' => $invite->getInviteLink()
        ]);
    }

    /**
     * Получение статистики приглашений для клана
     * @param int $clanId
     * @return array
     */
    private function getInviteStats($clanId)
    {
        // Получаем всех приглашенных пользователей, отсортированных по дате вступления
        $invitedUsers = UserClan::find()
            ->andWhere(['clan_id' => $clanId])
            ->andWhere(['not', ['clan_invite_id' => null]])
            ->with(['user', 'clanInvite.user'])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        $stats = [];
        $totalInvited = count($invitedUsers);
        
        // Группируем по приглашениям для статистики
        $invites = ClanInvite::find()
            ->andWhere(['clan_id' => $clanId])
            ->andWhere(['status' => ClanInvite::STATUS_ACTIVE])
            ->with(['user', 'userClans.user'])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        foreach ($invites as $invite) {
            $stats[] = [
                'invite' => $invite,
                'created_by' => $invite->user,
                'invited_users' => $invite->userClans,
                'stats' => $invite->getInviteStats(),
                'invite_link' => $invite->getInviteLink(),
            ];
        }

        // Добавляем общую статистику
        $stats['total_stats'] = [
            'total_invites' => count($invites),
            'total_invited_users' => $totalInvited,
            'recent_invited' => UserClan::find()
                ->andWhere(['clan_id' => $clanId])
                ->andWhere(['not', ['clan_invite_id' => null]])
                ->andWhere(['>=', 'created_at', date('Y-m-d H:i:s', strtotime('-7 days'))])
                ->count(),
        ];

        // Добавляем отсортированный список приглашенных пользователей
        $stats['invited_users_sorted'] = $invitedUsers;

        return $stats;
    }

    /**
     * Страница заявок на вступление в клан
     */
    public function actionApplications($hash)
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Вы не авторизованы!'));
            return $this->redirect(['index']);
        }

        /** @var Clan $clan */
        $clan = Clan::find()
            ->andWhere(['link_hash' => $hash])
            ->one();

        if (empty($clan)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Клан не найден!'));
            return $this->redirect(['index']);
        }

        // Проверяем права на просмотр заявок
        $roles = $user->getRolesClan($clan->user_id, $clan->id);
        if (!in_array(UserRole::ROLE_QUESTION, array_keys($roles)) && $clan->user_id != $user->id) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'У вас нет прав для просмотра заявок!'));
            return $this->redirect([$clan->getLink('profile')]);
        }

        $this->view->params['page'] = 'clan-applications';

        // Получаем заявки на вступление
        $applications = ClanQuestion::find()
            ->andWhere(['clan_id' => $clan->id])
            ->andWhere(['status' => ClanQuestion::STATUS_WAIT])
            ->with(['user'])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        return $this->render('applications.twig', [
            'CLAN' => $clan,
            'APPLICATIONS' => $applications,
            'ROLES' => $roles,
        ]);
    }

    /**
     * Принятие заявки на вступление в клан
     */
    public function actionAcceptApplication($id)
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Вы не авторизованы!')]);
        }

        /** @var ClanQuestion $application */
        $application = ClanQuestion::find()
            ->andWhere(['id' => $id])
            ->andWhere(['status' => ClanQuestion::STATUS_WAIT])
            ->one();

        if (empty($application)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Заявка не найдена!')]);
        }

        // Проверяем права на принятие заявок
        $roles = $user->getRolesClan($application->clan->user_id, $application->clan_id);
        if (!in_array(UserRole::ROLE_QUESTION, array_keys($roles)) && $application->clan->user_id != $user->id) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'У вас нет прав для принятия заявок!')]);
        }

        // Проверяем, не состоит ли уже пользователь в клане
        $existingMembership = UserClan::find()
            ->andWhere(['user_id' => $application->user_id])
            ->andWhere(['status' => 1])
            ->exists();

        if ($existingMembership) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Пользователь уже состоит в клане!')]);
        }

        // Принимаем заявку
        $application->status = ClanQuestion::STATUS_SUCCESS;
        $application->save();

        // Добавляем пользователя в клан
        $userClan = new UserClan();
        $userClan->user_id = $application->user_id;
        $userClan->steam_id = $application->user->steam_id;
        $userClan->clan_id = $application->clan_id;
        $userClan->status = 1;
        $userClan->created_at = date('Y-m-d H:i:s');
        $userClan->save();

        // Назначаем роль ROLE_MEMBER новому участнику
        $userRole = new UserRole();
        $userRole->user_id = $application->user_id;
        $userRole->clan_id = $application->clan_id;
        $userRole->role = UserRole::codes()[UserRole::ROLE_MEMBER];
        $userRole->created_at = date('Y-m-d H:i:s');
        $userRole->save();

        // Увеличиваем счетчик участников
        $application->clan->user_count += 1;
        $application->clan->save();

        return $this->asJson(['success' => true, 'message' => Yii::t('common', 'Заявка принята!')]);
    }

    /**
     * Отклонение заявки на вступление в клан
     */
    public function actionRejectApplication($id)
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Вы не авторизованы!')]);
        }

        /** @var ClanQuestion $application */
        $application = ClanQuestion::find()
            ->andWhere(['id' => $id])
            ->andWhere(['status' => ClanQuestion::STATUS_WAIT])
            ->one();

        if (empty($application)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Заявка не найдена!')]);
        }

        // Проверяем права на отклонение заявок
        $roles = $user->getRolesClan($application->clan->user_id, $application->clan_id);
        if (!in_array(UserRole::ROLE_QUESTION, array_keys($roles)) && $application->clan->user_id != $user->id) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'У вас нет прав для отклонения заявок!')]);
        }

        // Отклоняем заявку
        $application->status = ClanQuestion::STATUS_REJECT;
        $application->save();

        return $this->asJson(['success' => true, 'message' => Yii::t('common', 'Заявка отклонена!')]);
    }

    /**
     * Проверка банов пользователя
     */
    public function actionCheckBans()
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $userId = Yii::$app->request->post('user_id');
        if (empty($userId)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Пользователь не указан!')]);
        }

        /** @var \common\models\user\User $user */
        $user = \common\models\user\User::findOne($userId);
        if (empty($user)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Пользователь не найден!')]);
        }

        // Проверяем баны на разных проектах
        $bans = [];
        
        // GGRust
        $ggrustBans = BansSearch::find()
            ->andWhere(['steam_id' => $user->steam_id])
            ->andWhere(['like', 'reason', '%GGRust%'])
            ->all();
        if (!empty($ggrustBans)) {
            $bans['ggrust'] = $ggrustBans;
        }

        // Rust USSR
        $rustUssrBans = BansSearch::find()
            ->andWhere(['steam_id' => $user->steam_id])
            ->andWhere(['like', 'reason', '%Rust USSR%'])
            ->all();
        if (!empty($rustUssrBans)) {
            $bans['rust_ussr'] = $rustUssrBans;
        }

        // MagicRust
        $magicRustBans = BansSearch::find()
            ->andWhere(['steam_id' => $user->steam_id])
            ->andWhere(['like', 'reason', '%MagicRust%'])
            ->all();
        if (!empty($magicRustBans)) {
            $bans['magic_rust'] = $magicRustBans;
        }

        // BroRust
        $broRustBans = BansSearch::find()
            ->andWhere(['steam_id' => $user->steam_id])
            ->andWhere(['like', 'reason', '%BroRust%'])
            ->all();
        if (!empty($broRustBans)) {
            $bans['bro_rust'] = $broRustBans;
        }

        return $this->asJson([
            'success' => true,
            'bans' => $bans,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'steam_id' => $user->steam_id,
            ]
        ]);
    }

    /**
     * Страница управления участниками клана
     */
    public function actionMembers($hash)
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Вы не авторизованы!'));
            return $this->redirect(['index']);
        }

        /** @var Clan $clan */
        $clan = Clan::find()
            ->andWhere(['link_hash' => $hash])
            ->one();

        if (empty($clan)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Клан не найден!'));
            return $this->redirect(['index']);
        }

        // Проверяем права на управление участниками
        $roles = $user->getRolesClan($clan->user_id, $clan->id);
        if (!in_array(UserRole::ROLE_EDIT_MEMBERS, array_keys($roles)) && $clan->user_id != $user->id) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'У вас нет прав для управления участниками!'));
            return $this->redirect([$clan->getLink('profile')]);
        }

        $this->view->params['page'] = 'clan-members';

        // Получаем всех участников клана с их ролями
        $members = UserClan::find()
            ->andWhere(['clan_id' => $clan->id])
            ->andWhere(['status' => 1])
            ->with(['user', 'userRoles'])
            ->orderBy(['created_at' => SORT_ASC])
            ->all();

        return $this->render('members.twig', [
            'CLAN' => $clan,
            'MEMBERS' => $members,
            'ROLES' => $roles,
            'USER' => $user,
            'AVAILABLE_ROLES' => UserRole::codes(),
            'ROLE_NAMES' => UserRole::getRoleNames(),
            'csrf_token' => Yii::$app->request->csrfToken,
        ]);
    }

    /**
     * Исключение участника из клана
     */
    public function actionKickMember()
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Вы не авторизованы!')]);
        }

        $memberId = Yii::$app->request->post('member_id');
        if (empty($memberId)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Участник не указан!')]);
        }

        /** @var UserClan $member */
        $member = UserClan::find()
            ->andWhere(['id' => $memberId])
            ->andWhere(['status' => 1])
            ->one();

        if (empty($member)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Участник не найден!')]);
        }

        // Проверяем права на исключение участников
        $roles = $user->getRolesClan($member->clan->user_id, $member->clan_id);
        if (!in_array(UserRole::ROLE_EDIT_MEMBERS, array_keys($roles)) && $member->clan->user_id != $user->id) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'У вас нет прав для исключения участников!')]);
        }

        // Нельзя исключить владельца клана
        if ($member->clan->user_id == $member->user_id) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Нельзя исключить владельца клана!')]);
        }

        // Исключаем участника
        $member->status = 0;
        $member->save();

        // Удаляем все роли участника
        UserRole::deleteAll([
            'user_id' => $member->user_id,
            'clan_id' => $member->clan_id
        ]);

        // Уменьшаем счетчик участников
        $member->clan->user_count -= 1;
        $member->clan->save();

        return $this->asJson(['success' => true, 'message' => Yii::t('common', 'Участник исключен из клана!')]);
    }

    /**
     * Назначение роли участнику
     */
    public function actionAssignRole()
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Вы не авторизованы!')]);
        }

        $memberId = Yii::$app->request->post('member_id');
        $role = Yii::$app->request->post('role');

        if (empty($memberId) || empty($role)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Не указаны участник или роль!')]);
        }

        /** @var UserClan $member */
        $member = UserClan::find()
            ->andWhere(['id' => $memberId])
            ->andWhere(['status' => 1])
            ->one();

        if (empty($member)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Участник не найден!')]);
        }

        // Проверяем права на назначение ролей
        $roles = $user->getRolesClan($member->clan->user_id, $member->clan_id);
        if (!in_array(UserRole::ROLE_EDIT_MEMBERS, array_keys($roles)) && $member->clan->user_id != $user->id) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'У вас нет прав для назначения ролей!')]);
        }

        // Проверяем, что роль существует
        $roleCodes = UserRole::codes();
        if (!in_array($role, array_keys($roleCodes))) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Неверная роль!')]);
        }

        // Проверяем, не пытается ли назначить роль владельцу клана (кроме владельца)
        if ($member->clan->user_id == $member->user_id && $member->clan->user_id != $user->id) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Нельзя изменять роли владельца клана!')]);
        }

        // Проверяем, есть ли уже такая роль у участника
        $existingRole = UserRole::find()
            ->andWhere(['user_id' => $member->user_id])
            ->andWhere(['clan_id' => $member->clan_id])
            ->andWhere(['role' => $roleCodes[$role]])
            ->one();

        if ($existingRole) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'У участника уже есть эта роль!')]);
        }

        // Назначаем роль
        $userRole = new UserRole();
        $userRole->user_id = $member->user_id;
        $userRole->clan_id = $member->clan_id;
        $userRole->role = $roleCodes[$role];
        $userRole->created_at = date('Y-m-d H:i:s');
        $userRole->save();

        return $this->asJson(['success' => true, 'message' => Yii::t('common', 'Роль назначена!')]);
    }

    /**
     * Снятие роли с участника
     */
    public function actionRemoveRole()
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Вы не авторизованы!')]);
        }

        $memberId = Yii::$app->request->post('member_id');
        $role = Yii::$app->request->post('role');
        
        if (empty($memberId) || empty($role)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Не указаны участник или роль!')]);
        }

        /** @var UserClan $member */
        $member = UserClan::find()
            ->andWhere(['id' => $memberId])
            ->andWhere(['status' => 1])
            ->one();

        if (empty($member)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Участник не найден!')]);
        }

        // Проверяем права на снятие ролей
        $roles = $user->getRolesClan($member->clan->user_id, $member->clan_id);
        if (!in_array(UserRole::ROLE_EDIT_MEMBERS, array_keys($roles)) && $member->clan->user_id != $user->id) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'У вас нет прав для снятия ролей!')]);
        }

        // Проверяем, что роль существует
        $roleCodes = UserRole::codes();
        if (!in_array($role, array_keys($roleCodes))) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Неверная роль!')]);
        }

        // Проверяем, не пытается ли снять роль с владельца клана (кроме владельца)
        if ($member->clan->user_id == $member->user_id && $member->clan->user_id != $user->id) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Нельзя изменять роли владельца клана!')]);
        }

        // Снимаем роль
        UserRole::deleteAll([
            'user_id' => $member->user_id,
            'clan_id' => $member->clan_id,
            'role' => $roleCodes[$role]
        ]);

        return $this->asJson(['success' => true, 'message' => Yii::t('common', 'Роль снята!')]);
    }

    /**
     * Страница управления страницами клана
     */
    public function actionPages($hash)
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Вы не авторизованы!'));
            return $this->redirect(['index']);
        }

        /** @var Clan $clan */
        $clan = Clan::find()
            ->andWhere(['link_hash' => $hash])
            ->one();

        if (empty($clan)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Клан не найден!'));
            return $this->redirect(['index']);
        }

        // Проверяем права на управление страницами
        $roles = $user->getRolesClan($clan->user_id, $clan->id);
        if (!in_array(UserRole::ROLE_EDIT_PAGES, array_keys($roles)) && $clan->user_id != $user->id) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'У вас нет прав для управления страницами!'));
            return $this->redirect([$clan->getLink('profile')]);
        }

        $this->view->params['page'] = 'clan-pages';
        // Получаем все страницы клана
        $pages = ClanPage::find()
            ->andWhere(['clan_id' => $clan->id])
            ->orderBy(['sort' => SORT_ASC, 'created_at' => SORT_DESC])
            ->all();

        return $this->render('pages.twig', [
            'CLAN' => $clan,
            'PAGES' => $pages,
            'ROLES' => $roles,
            'csrf_token' => Yii::$app->request->csrfToken,
        ]);
    }

    /**
     * Создание новой страницы клана
     */
    public function actionCreatePage($hash)
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Вы не авторизованы!'));
            return $this->redirect(['index']);
        }

        /** @var Clan $clan */
        $clan = Clan::find()
            ->andWhere(['link_hash' => $hash])
            ->one();

        if (empty($clan)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Клан не найден!'));
            return $this->redirect(['index']);
        }

        // Проверяем права на управление страницами
        $roles = $user->getRolesClan($clan->user_id, $clan->id);
        if (!in_array(UserRole::ROLE_EDIT_PAGES, array_keys($roles)) && $clan->user_id != $user->id) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'У вас нет прав для создания страниц!'));
            return $this->redirect([$clan->getLink('profile')]);
        }

        // Проверяем лимит страниц (максимум 20)
        $pagesCount = ClanPage::find()->andWhere(['clan_id' => $clan->id])->count();
        if ($pagesCount >= 20) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Достигнут лимит страниц (20)!'));
            return $this->redirect(['pages', 'hash' => $hash]);
        }

        $form = new ClanPageForm();
        $form->clan_id = $clan->id;
        $form->user_id = $user->id;

        if ($form->load(Yii::$app->request->post()) && $form->save()) {
            Yii::$app->session->addFlash('success', Yii::t('common', 'Страница создана!'));
            return $this->redirect(['pages', 'hash' => $hash]);
        }

        $this->view->params['page'] = 'clan-pages';

        return $this->render('create-page.twig', [
            'CLAN' => $clan,
            'FORM' => $form,
            'ROLES' => $roles,
            'csrf_token' => Yii::$app->request->csrfToken,
        ]);
    }

    /**
     * Редактирование страницы клана
     */
    public function actionEditPage($hash, $id)
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Вы не авторизованы!'));
            return $this->redirect(['index']);
        }

        /** @var Clan $clan */
        $clan = Clan::find()
            ->andWhere(['link_hash' => $hash])
            ->one();

        if (empty($clan)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Клан не найден!'));
            return $this->redirect(['index']);
        }

        /** @var ClanPage $page */
        $page = ClanPage::find()
            ->andWhere(['id' => $id, 'clan_id' => $clan->id])
            ->one();

        if (empty($page)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Страница не найдена!'));
            return $this->redirect(['pages', 'hash' => $hash]);
        }

        // Проверяем права на управление страницами
        $roles = $user->getRolesClan($clan->user_id, $clan->id);
        if (!in_array(UserRole::ROLE_EDIT_PAGES, array_keys($roles)) && $clan->user_id != $user->id) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'У вас нет прав для редактирования страниц!'));
            return $this->redirect([$clan->getLink('profile')]);
        }

        $form = new ClanPageForm();
        $form->loadFromModel($page);

        if ($form->load(Yii::$app->request->post()) && $form->save()) {
            Yii::$app->session->addFlash('success', Yii::t('common', 'Страница обновлена!'));
            return $this->redirect(['pages', 'hash' => $hash]);
        }

        $this->view->params['page'] = 'clan-pages';

        return $this->render('edit-page.twig', [
            'CLAN' => $clan,
            'PAGE' => $page,
            'FORM' => $form,
            'ROLES' => $roles,
            'csrf_token' => Yii::$app->request->csrfToken,
        ]);
    }

    /**
     * Просмотр страницы клана
     */
    public function actionViewPage($linkHash, $linkName)
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var Clan $clan */
        $clan = Clan::find()
            ->andWhere(['link_hash' => $linkHash])
            ->one();

        if (empty($clan)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Клан не найден!'));
            return $this->redirect(['index']);
        }

        /** @var ClanPage $page */
        $page = ClanPage::find()
            ->andWhere(['link_name' => $linkName, 'clan_id' => $clan->id])
            ->one();

        if (empty($page)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Страница не найдена!'));
            return $this->redirect([$clan->getLink('profile')]);
        }

        $this->view->params['page'] = 'clan-page-view';

        return $this->render('view-page.twig', [
            'CLAN' => $clan,
            'PAGE' => $page,
            'csrf_token' => Yii::$app->request->csrfToken,
        ]);
    }

    /**
     * Удаление страницы клана
     */
    public function actionDeletePage($hash, $id)
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Вы не авторизованы!')]);
        }

        /** @var Clan $clan */
        $clan = Clan::find()
            ->andWhere(['link_hash' => $hash])
            ->one();

        if (empty($clan)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Клан не найден!')]);
        }

        /** @var ClanPage $page */
        $page = ClanPage::find()
            ->andWhere(['id' => $id, 'clan_id' => $clan->id])
            ->one();

        if (empty($page)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Страница не найдена!')]);
        }

        // Проверяем права на управление страницами
        $roles = $user->getRolesClan($clan->user_id, $clan->id);
        if (!in_array(UserRole::ROLE_EDIT_PAGES, array_keys($roles)) && $clan->user_id != $user->id) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'У вас нет прав для удаления страниц!')]);
        }

        // Удаляем изображения страницы
        $this->deletePageImages($page);

        // Удаляем страницу
        $page->delete();

        return $this->asJson(['success' => true, 'message' => Yii::t('common', 'Страница удалена!')]);
    }

    /**
     * Обновление сортировки страниц
     */
    public function actionUpdatePageSort($hash)
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Вы не авторизованы!')]);
        }

        /** @var Clan $clan */
        $clan = Clan::find()
            ->andWhere(['link_hash' => $hash])
            ->one();

        if (empty($clan)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Клан не найден!')]);
        }

        // Проверяем права на управление страницами
        $roles = $user->getRolesClan($clan->user_id, $clan->id);
        if (!in_array(UserRole::ROLE_EDIT_PAGES, array_keys($roles)) && $clan->user_id != $user->id) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'У вас нет прав для изменения сортировки!')]);
        }

        $pageIds = Yii::$app->request->post('page_ids', []);
        
        foreach ($pageIds as $sort => $pageId) {
            ClanPage::updateAll(['sort' => $sort + 1], ['id' => $pageId, 'clan_id' => $clan->id]);
        }

        return $this->asJson(['success' => true, 'message' => Yii::t('common', 'Сортировка обновлена!')]);
    }

    /**
     * Загрузка изображений для страниц
     */
    public function actionUploadPageImage($hash)
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Вы не авторизованы!')]);
        }

        /** @var Clan $clan */
        $clan = Clan::find()
            ->andWhere(['link_hash' => $hash])
            ->one();

        if (empty($clan)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Клан не найден!')]);
        }

        // Проверяем права на управление страницами
        $roles = $user->getRolesClan($clan->user_id, $clan->id);
        if (!in_array(UserRole::ROLE_EDIT_PAGES, array_keys($roles)) && $clan->user_id != $user->id) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'У вас нет прав для загрузки изображений!')]);
        }

        $file = UploadedFile::getInstanceByName('file');
        
        if (!$file) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Файл не выбран!')]);
        }

        // Проверяем размер файла (максимум 2MB)
        if ($file->size > 2 * 1024 * 1024) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Размер файла не должен превышать 2MB!')]);
        }

        // Проверяем тип файла
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->type, $allowedTypes)) {
            return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Разрешены только изображения (JPEG, PNG, GIF, WebP)!')]);
        }

        // Создаем директорию для изображений клана
        $clanImagesDir = Yii::getAlias('@frontend/web/images/clan-pages/' . $clan->id);
        if (!is_dir($clanImagesDir)) {
            mkdir($clanImagesDir, 0755, true);
        }

        // Генерируем уникальное имя файла
        $fileName = uniqid() . '.' . $file->extension;
        $filePath = $clanImagesDir . '/' . $fileName;

        if ($file->saveAs($filePath)) {
            $imageUrl = '/images/clan-pages/' . $clan->id . '/' . $fileName;
            return $this->asJson([
                'location' => $imageUrl
            ]);
        }

        return $this->asJson(['success' => false, 'message' => Yii::t('common', 'Ошибка при загрузке файла!')]);
    }

    /**
     * Удаление изображений страницы
     */
    private function deletePageImages($page)
    {
        $clanImagesDir = Yii::getAlias('@frontend/web/images/clan-pages/' . $page->clan_id);
        
        if (is_dir($clanImagesDir)) {
            // Находим все изображения в тексте страницы
            preg_match_all('/\/images\/clan-pages\/' . $page->clan_id . '\/([^"\']+)/', $page->text, $matches);
            
            foreach ($matches[1] as $fileName) {
                $filePath = $clanImagesDir . '/' . $fileName;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        }
    }

    /**
     * Настройки клана
     */
    public function actionSettings($hash)
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Вы не авторизованы!'));
            return $this->redirect(['index']);
        }

        /** @var Clan $clan */
        $clan = Clan::find()
            ->andWhere(['link_hash' => $hash])
            ->one();

        if (empty($clan)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Клан не найден!'));
            return $this->redirect(['index']);
        }

        // Проверяем права на редактирование информации о клане
        $roles = $user->getRolesClan($clan->user_id, $clan->id);
        if (!in_array(UserRole::ROLE_EDIT_INFO, array_keys($roles)) && $clan->user_id != $user->id) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'У вас нет прав для редактирования настроек клана!'));
            return $this->redirect([$clan->getLink('profile')]);
        }

        $form = new ClanSettingsForm();
        $form->loadFromModel($clan);

        if (Yii::$app->request->isPost) {
            if ($form->load(Yii::$app->request->post())) {
                if ($form->save()) {
                    Yii::$app->session->addFlash('success', Yii::t('common', 'Настройки клана обновлены!'));
                    return $this->redirect([$clan->getLink('profile')]);
                } else {
                    Yii::$app->session->addFlash('danger', Yii::t('common', 'Ошибка при сохранении настроек!'));
                }
            } else {
                Yii::$app->session->addFlash('danger', Yii::t('common', 'Ошибка при загрузке данных формы!'));
            }
        }

        $this->view->params['page'] = 'clan-settings';

        return $this->render('settings.twig', [
            'CLAN' => $clan,
            'FORM' => $form,
            'ROLES' => $roles,
            'csrf_token' => Yii::$app->request->csrfToken,
        ]);
    }

}
