<?php

namespace backend\controllers;

use backend\models\avatar\AvatarFrameSearch;
use common\components\helpers\Role;
use common\models\avatar\AvatarFrame;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

class AvatarFrameController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $searchModel = new AvatarFrameSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['showFilters'] = false;
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-plus"></i> ' . Yii::t('common', 'Добавить рамку'),
                'url' => ['create'],
                'class' => 'bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionCreate()
    {
        $model = new AvatarFrame();
        if ($this->request->isPost && $model->load($this->request->post())) {
            $model->name = 'frame_' . uniqid('', false);
            $model->sort = $this->getNextSort();
            if ($this->uploadFrameFile($model, null) && $model->save()) {
                Yii::$app->session->setFlash('success', Yii::t('common', 'Рамка добавлена'));
                return $this->redirect(['index']);
            }
        }

        return $this->render('create', ['model' => $model]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel((int)$id);
        $oldKey = $model->image_key;

        if ($this->request->isPost && $model->load($this->request->post())) {
            if ($this->uploadFrameFile($model, $oldKey) && $model->save()) {
                Yii::$app->session->setFlash('success', Yii::t('common', 'Рамка обновлена'));
                return $this->redirect(['index']);
            }
        }

        return $this->render('update', ['model' => $model]);
    }

    public function actionDelete($id)
    {
        $model = $this->findModel((int)$id);
        if (!empty($model->image_key) && Yii::$app->has('s3Api')) {
            Yii::$app->s3Api->deleteFile($model->image_key);
        }
        $model->delete();
        Yii::$app->session->setFlash('success', Yii::t('common', 'Рамка удалена'));

        return $this->redirect(['index']);
    }

    protected function findModel(int $id): AvatarFrame
    {
        $model = AvatarFrame::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        return $model;
    }

    private function uploadFrameFile(AvatarFrame $model, ?string $oldKey): bool
    {
        $file = UploadedFile::getInstanceByName('frame_file');
        if ($file === null) {
            return true;
        }

        if (!Yii::$app->has('s3Api')) {
            $model->addError('image_key', 'S3 не настроен');
            return false;
        }

        $ext = strtolower((string)$file->extension);
        if (!in_array($ext, ['png', 'webp'], true)) {
            $model->addError('image_key', 'Допустимы только PNG/WebP');
            return false;
        }

        $newKey = 'uploads/avatar-frames/frame_' . uniqid('', true) . '.' . $ext;
        $uploaded = Yii::$app->s3Api->putFile($newKey, $file->tempName, $file->type ?: 'image/png');
        if ($uploaded === false) {
            $model->addError('image_key', 'Не удалось загрузить файл в S3');
            return false;
        }

        $model->image_key = $newKey;
        if (!empty($oldKey) && $oldKey !== $newKey) {
            Yii::$app->s3Api->deleteFile($oldKey);
        }

        return true;
    }

    private function getNextSort(): int
    {
        $maxSort = (int)AvatarFrame::find()->max('sort');
        return $maxSort > 0 ? $maxSort + 1 : 1;
    }
}

