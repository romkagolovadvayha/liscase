<?php

namespace backend\controllers;

use backend\components\BackendController;
use common\components\helpers\Role;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Просмотр файлов в S3 и установка заголовков кэширования для уже загруженных объектов.
 */
class S3StorageController extends BackendController
{
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
                'class' => VerbFilter::class,
                'actions' => [
                    'set-headers' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Список «папок» и файлов по префиксу.
     * @param string $path Префикс (каталог), например "uploads/tasks-v2" или пусто — корень
     */
    public function actionIndex(string $path = ''): string
    {
        $path = trim($path, '/');
        if (!Yii::$app->has('s3Api')) {
            return $this->render('index', [
                'path' => $path,
                'prefixes' => [],
                'objects' => [],
                's3Api' => null,
                'error' => 'S3 не настроен (s3Api недоступен).',
            ]);
        }
        $s3Api = Yii::$app->s3Api;
        $data = $s3Api->listObjects($path);
        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['showFilters'] = false;
        return $this->render('index', [
            'path' => $path,
            'prefixes' => $data['prefixes'],
            'objects' => $data['objects'],
            's3Api' => $s3Api,
            'error' => null,
        ]);
    }

    /**
     * Установить заголовки кэша (Cache-Control: 30 дней) для всех объектов в каталоге.
     * POST: prefix — префикс (каталог).
     */
    public function actionSetHeaders(): Response
    {
        set_time_limit(600); // 10 минут — операция может обрабатывать много объектов S3
        if (!Yii::$app->request->isPost) {
            throw new BadRequestHttpException('Только POST.');
        }
        $prefix = trim((string) Yii::$app->request->post('prefix', ''), '/');
        if (!Yii::$app->has('s3Api')) {
            Yii::$app->session->setFlash('error', 'S3 не настроен.');
            return $this->redirect(['index', 'path' => $prefix]);
        }
        $s3Api = Yii::$app->s3Api;
        $keys = $s3Api->listAllKeysUnderPrefix($prefix);
        $result = $s3Api->setObjectCacheHeadersBulk($keys, 25);
        $ok = $result['ok'];
        $fail = $result['fail'];
        if ($fail > 0) {
            Yii::$app->session->setFlash('error', "Заголовки установлены: {$ok}. Ошибок: {$fail}.");
        } else {
            Yii::$app->session->setFlash('success', "Заголовки кэша (30 дней) установлены для {$ok} файлов.");
        }
        return $this->redirect(['index', 'path' => $prefix]);
    }
}
