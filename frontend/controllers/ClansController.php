<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\box\SelectImage;
use common\models\clan\ClanSearch;
use common\models\clan\Clan;
use common\models\clan\UserRole;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\user\UserTop;
use frontend\assets\ClanAsset;
use frontend\forms\clans\ClanForm;
use frontend\forms\clans\LeaveForm;
use frontend\forms\clans\QuestionForm;
use frontend\models\banlist\BansSearch;
use yii\base\BaseObject;
use yii\helpers\Html;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use Yii;
use yii\web\UploadedFile;
use common\models\clan\{ClanInvite, ClanPage, ClanQuestion, ClanResource, UserClan};
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

        foreach ($servers as $item) {
            $server = $item;
            break;
        }

        if (!Yii::$app->user->isGuest) {
            $server = Yii::$app->user->identity->getCurrentServer();
        }

        Yii::$app->response->redirect('/clans/' . $server->tag, 301);
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

        $items = Clan::getClans($server);

        $dataProvider = new \yii\data\ArrayDataProvider([
            'allModels' => $items,
            'totalCount' => count($items),
            'pagination' => [
                'pageSize' => 20,
            ],
            'sort'  => [
                'attributes' => ['title', 'rating', 'kills', 'users_count', 'rocket_basic', 'c4thrown', 'scrap', 'sulfur.ore'],
                'defaultOrder' => ['rating' => SORT_DESC],
            ],
        ]);

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
        if (!Yii::$app->user->isGuest) {
            $user = Yii::$app->user->identity;
            $userClans = \common\models\clan\Clan::getUserClansList($server);
            if (!empty($userClans[$user->id])) {
                $clans = \common\models\clan\Clan::getClans($server);
                if (!empty($clans[$userClans[$user->id]])) {
                    $clan = $clans[$userClans[$user->id]];
                }
            }
        }

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

    public function actionProfile($serverTag, $linkHash)
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
            if ($item->tag === $serverTag) {
                $server = $item;
                break;
            }
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

        return $this->render('profile.twig', [
            'SERVER'  => $server,
            'SERVERS'  => $servers,
            'CLAN'  => $model,
            'CLAN_STATS'  => $clanStats,
            'ROLES'  => $roles,
            'IMAGES'  => $images,
            'NAMES'  => $names,
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
                return $this->redirect([$model->getLink('profile', $server->tag)]);
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
                    return $this->redirect([$model->getLink('profile', $server->tag)]);
                } else {
                    Yii::$app->session->addFlash('success', Yii::t('common', 'Клан удален, так как вы единственный участник'));
                    return $this->redirect(['/clans']);
                }
            } else {
                if (!empty($model->getFirstError('global'))) {
                    Yii::$app->session->addFlash('danger', $model->getFirstError('global'));
                }
                return $this->redirect([$model->getLink('profile', $server->tag)]);
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

        $model = new QuestionForm();
        $model->clan_id = $clan->id;
        $model->user_id = $user->id;
        if ($this->request->isPost) {
            $server = $clan->user->getCurrentServer();
            $model->load($this->request->post());
            if ($model->saveRecord()) {
                Yii::$app->session->addFlash('success', Yii::t('common', 'Заявка отправлена'));
                return $this->redirect([$model->clan->getLink('profile', $server->tag)]);
            } else {
                if (!empty($model->getFirstError('global'))) {
                    Yii::$app->session->addFlash('danger', $model->getFirstError('global'));
                    return $this->redirect([$model->clan->getLink('profile', $server->tag)]);
                }
            }
        }

        return $this->renderAjax('question-form', [
            'model' => $model,
        ]);
    }

}
