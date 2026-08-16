<?php

namespace backend\controllers;

use backend\components\BackendController;
use common\components\helpers\Role;
use common\models\medals\Medal;
use common\models\medals\UserMedal;
use common\models\user\User;
use Yii;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

class MedalsController extends BackendController
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [[
                    'allow' => true,
                    'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR],
                ]],
            ],
        ];
    }

    public function actionIndex()
    {
        $medals = Medal::find()->orderBy(['is_active' => SORT_DESC, 'created_at' => SORT_DESC])->all();
        $assignments = UserMedal::find()
            ->with(['medal', 'user'])
            ->orderBy(['awarded_at' => SORT_DESC])
            ->limit(100)
            ->all();
        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['headerActions'] = [[
            'label' => '<i class="fas fa-plus"></i> ' . Yii::t('common', 'Создать медаль'),
            'url' => ['create'],
            'class' => 'ds-btn ds-btn--primary ds-btn--sm',
        ]];
        return $this->render('index', compact('medals', 'assignments'));
    }

    public function actionCreate()
    {
        $model = new Medal();
        $model->is_active = 1;
        return $this->saveModel($model);
    }

    public function actionUpdate($id)
    {
        return $this->saveModel($this->findModel((int)$id));
    }

    public function actionAward()
    {
        $medalId = (int)Yii::$app->request->post('medal_id');
        $userQuery = trim((string)Yii::$app->request->post('user_query'));
        $note = trim((string)Yii::$app->request->post('note'));
        $medal = Medal::findOne(['id' => $medalId, 'is_active' => 1]);
        $user = User::find()
            ->where(['id' => ctype_digit($userQuery) ? (int)$userQuery : 0])
            ->orWhere(['steam_id' => $userQuery])
            ->orWhere(['username' => $userQuery])
            ->one();
        if (!$medal || !$user) {
            Yii::$app->session->setFlash('danger', 'Медаль или пользователь не найдены.');
            return $this->redirect(['index']);
        }

        try {
            UserMedal::award(
                (int)$user->id,
                (int)$medal->id,
                UserMedal::SOURCE_MANUAL,
                null,
                $note !== '' ? $note : null,
                (int)Yii::$app->user->id
            );
            Yii::$app->cache->delete('homepage_medals_' . (int)$user->id);
            Yii::$app->session->setFlash('success', 'Медаль начислена пользователю ' . $user->username . '.');
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('danger', $e->getMessage());
        }
        return $this->redirect(['index']);
    }

    public function actionRevoke($id)
    {
        $model = UserMedal::findOne((int)$id);
        if ($model) {
            $userId = (int)$model->user_id;
            $model->delete();
            Yii::$app->cache->delete('homepage_medals_' . $userId);
            Yii::$app->session->setFlash('success', 'Медаль отозвана.');
        }
        return $this->redirect(['index']);
    }

    private function saveModel(Medal $model)
    {
        if ($model->load(Yii::$app->request->post())) {
            $file = UploadedFile::getInstance($model, 'imageFile');
            $model->imageFile = $file;
            if ($model->validate()) {
                $oldPath = $model->getOldAttribute('image_path');
                $newPath = null;
                if ($file) {
                    $extension = strtolower($file->extension);
                    $newPath = 'uploads/medals/' . uniqid('medal_', true) . '.' . $extension;
                    if (Yii::$app->s3Api->putFile($newPath, file_get_contents($file->tempName), $file->type) === false) {
                        $model->addError('imageFile', 'Не удалось загрузить изображение.');
                    } else {
                        $model->image_path = $newPath;
                    }
                }

                if (!$model->hasErrors() && $model->save(false)) {
                    if ($newPath && $oldPath && strpos($oldPath, 'uploads/medals/') === 0) {
                        Yii::$app->s3Api->deleteFile($oldPath);
                    }
                    Yii::$app->session->setFlash('success', 'Медаль сохранена.');
                    return $this->redirect(['index']);
                }

                // Если запись не сохранилась, не оставляем новый файл без владельца.
                if ($newPath && $model->hasErrors()) {
                    Yii::$app->s3Api->deleteFile($newPath);
                }
            }
        }

        $this->view->params['contentClass'] = 'content-no-padding';
        return $this->render('_form', ['model' => $model]);
    }

    private function findModel(int $id): Medal
    {
        $model = Medal::findOne($id);
        if (!$model) {
            throw new NotFoundHttpException('Медаль не найдена.');
        }
        return $model;
    }
}
