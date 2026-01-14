<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\models\support\SupportSticker;
use backend\models\support\SupportStickerSearch;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\web\UploadedFile;

/**
 * SupportStickerController implements the CRUD actions for SupportSticker model.
 */
class SupportStickerController extends Controller
{
    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all SupportSticker models.
     *
     * @return string
     */
    public function actionIndex()
    {
        $searchModel = new SupportStickerSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single SupportSticker model.
     * @param int $id ID
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new SupportSticker model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        $model = new SupportSticker();

        if ($this->request->isPost) {
            if ($model->load($this->request->post())) {
                $file = UploadedFile::getInstance($model, 'file');
                
                if ($file) {
                    // Генерируем уникальное имя файла
                    $extension = $file->extension;
                    $fileName = uniqid('sticker_', true) . '_' . time() . '.' . $extension;
                    $s3Key = 'support/stickers/' . $fileName;
                    
                    // Загружаем файл в S3
                    $s3Api = Yii::$app->s3Api;
                    $uploaded = $s3Api->putFile($s3Key, $file->tempName, $file->type);
                    
                    if ($uploaded) {
                        $model->file = $fileName;
                        
                        // Определяем тип стикера
                        if (strpos($file->type, 'image/') === 0) {
                            $model->type = SupportSticker::TYPE_IMAGE;
                        } elseif (strpos($file->type, 'video/') === 0) {
                            $model->type = SupportSticker::TYPE_VIDEO;
                        }
                        
                        if ($model->save()) {
                            Yii::$app->session->setFlash('success', Yii::t('common', 'Стикер успешно создан!'));
                            return $this->redirect(['view', 'id' => $model->id]);
                        }
                    } else {
                        $model->addError('file', 'Ошибка загрузки файла в S3');
                    }
                } else {
                    $model->addError('file', 'Файл обязателен для загрузки');
                }
            }
        } else {
            $model->loadDefaultValues();
            $model->status = SupportSticker::STATUS_ACTIVE;
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing SupportSticker model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $id ID
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        $oldFile = $model->file;

        if ($this->request->isPost && $model->load($this->request->post())) {
            $file = UploadedFile::getInstance($model, 'file');
            
            if ($file) {
                // Генерируем уникальное имя файла
                $extension = $file->extension;
                $fileName = uniqid('sticker_', true) . '_' . time() . '.' . $extension;
                $s3Key = 'support/stickers/' . $fileName;
                
                // Загружаем файл в S3
                $s3Api = Yii::$app->s3Api;
                $uploaded = $s3Api->putFile($s3Key, $file->tempName, $file->type);
                
                if ($uploaded) {
                    // Удаляем старый файл из S3
                    if ($oldFile) {
                        $oldS3Key = 'support/stickers/' . $oldFile;
                        $s3Api->deleteFile($oldS3Key);
                    }
                    
                    $model->file = $fileName;
                    
                    // Определяем тип стикера
                    if (strpos($file->type, 'image/') === 0) {
                        $model->type = SupportSticker::TYPE_IMAGE;
                    } elseif (strpos($file->type, 'video/') === 0) {
                        $model->type = SupportSticker::TYPE_VIDEO;
                    }
                } else {
                    $model->addError('file', 'Ошибка загрузки файла в S3');
                }
            } else {
                // Если файл не загружен, сохраняем старое имя
                $model->file = $oldFile;
            }
            
            if ($model->save()) {
                Yii::$app->session->setFlash('success', Yii::t('common', 'Стикер успешно обновлен!'));
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing SupportSticker model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        
        // Удаляем файл из S3
        if (!empty($model->file)) {
            $s3Api = Yii::$app->s3Api;
            $s3Key = 'support/stickers/' . $model->file;
            $s3Api->deleteFile($s3Key);
        }

        $model->delete();

        Yii::$app->session->setFlash('success', Yii::t('common', 'Стикер успешно удален!'));
        return $this->redirect(['index']);
    }

    /**
     * Finds the SupportSticker model based on its primary key value.
     * If the model cannot be found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return SupportSticker the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = SupportSticker::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}


