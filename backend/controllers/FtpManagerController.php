<?php

namespace backend\controllers;

use backend\components\BackendController;
use backend\components\FtpHelper;
use common\components\helpers\Role;
use common\models\servers\Servers;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * FTP менеджер файлов серверов.
 * Доступен только серверам с заполненными ftp_login и ftp_password.
 */
class FtpManagerController extends BackendController
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
                    'upload' => ['POST'],
                    'upload-all' => ['POST'],
                    'delete' => ['POST'],
                    'save-content' => ['POST'],
                    'create-dir' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Страница FTP менеджера: выбор сервера и вкладки с файловыми менеджерами.
     */
    public function actionIndex()
    {
        $serversWithFtp = Servers::find()
            ->andWhere(['not', ['ftp_login' => null]])
            ->andWhere(['<>', 'ftp_login', ''])
            ->andWhere(['not', ['ftp_password' => null]])
            ->andWhere(['<>', 'ftp_password', ''])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['showFilters'] = false;

        return $this->render('index', [
            'serversWithFtp' => $serversWithFtp,
        ]);
    }

    /**
     * Одна директория на всех серверах: вкладки со списками и загрузка файла сразу на все FTP.
     */
    public function actionBroadcast()
    {
        $serversWithFtp = Servers::find()
            ->andWhere(['not', ['ftp_login' => null]])
            ->andWhere(['<>', 'ftp_login', ''])
            ->andWhere(['not', ['ftp_password' => null]])
            ->andWhere(['<>', 'ftp_password', ''])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['showFilters'] = false;

        return $this->render('broadcast', [
            'serversWithFtp' => $serversWithFtp,
        ]);
    }

    /**
     * API: загрузить один файл в указанную директорию на всех серверах с FTP (или на выбранных).
     */
    public function actionUploadAll()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $path = (string)Yii::$app->request->post('path', '/');
        $file = UploadedFile::getInstanceByName('file');
        if (!$file) {
            return ['success' => false, 'error' => 'Файл не выбран'];
        }

        $rawIds = Yii::$app->request->post('server_ids');
        if (is_string($rawIds)) {
            $decoded = json_decode($rawIds, true);
            $serverIds = is_array($decoded) ? $decoded : [];
        } elseif (is_array($rawIds)) {
            $serverIds = $rawIds;
        } else {
            $serverIds = [];
        }
        $serverIds = array_values(array_unique(array_filter(array_map('intval', $serverIds))));

        $query = Servers::find()
            ->andWhere(['not', ['ftp_login' => null]])
            ->andWhere(['<>', 'ftp_login', ''])
            ->andWhere(['not', ['ftp_password' => null]])
            ->andWhere(['<>', 'ftp_password', ''])
            ->orderBy(['sort' => SORT_ASC]);
        if ($serverIds !== []) {
            $query->andWhere(['id' => $serverIds]);
        }
        /** @var Servers[] $servers */
        $servers = $query->all();
        if ($servers === []) {
            return ['success' => false, 'error' => 'Нет серверов с настроенным FTP'];
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $file->name) ?: 'upload.bin';
        $tmpPath = Yii::getAlias('@runtime') . '/ftp_upload_all_' . uniqid('', true) . '_' . $safeName;
        if (!$file->saveAs($tmpPath)) {
            return ['success' => false, 'error' => 'Не удалось сохранить файл для отправки'];
        }

        $remotePath = rtrim(str_replace('\\', '/', $path), '/') . '/' . $file->name;
        $results = [];
        $okCount = 0;
        try {
            foreach ($servers as $server) {
                $helper = new FtpHelper($server);
                if (!$helper->connect()) {
                    $results[] = [
                        'server_id' => (int)$server->id,
                        'name' => $server->name,
                        'success' => false,
                        'error' => 'Не удалось подключиться к FTP',
                    ];
                    continue;
                }
                try {
                    if ($helper->upload($tmpPath, $remotePath)) {
                        $results[] = [
                            'server_id' => (int)$server->id,
                            'name' => $server->name,
                            'success' => true,
                        ];
                        $okCount++;
                    } else {
                        $results[] = [
                            'server_id' => (int)$server->id,
                            'name' => $server->name,
                            'success' => false,
                            'error' => 'Ошибка загрузки на сервер',
                        ];
                    }
                } finally {
                    $helper->disconnect();
                }
            }
        } finally {
            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }

        $total = count($results);
        $allOk = $okCount === $total;

        return [
            'success' => $allOk,
            'results' => $results,
            'summary' => [
                'ok' => $okCount,
                'fail' => $total - $okCount,
                'total' => $total,
            ],
        ];
    }

    /**
     * API: список файлов в директории.
     */
    public function actionList()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $serverId = (int)Yii::$app->request->get('server_id');
        $path = (string)Yii::$app->request->get('path', '/');

        $server = $this->findServerWithFtp($serverId);
        $helper = new FtpHelper($server);
        if (!$helper->connect()) {
            return ['success' => false, 'error' => 'Не удалось подключиться к FTP'];
        }
        try {
            $list = $helper->listDir($path);
            return ['success' => true, 'items' => $list, 'path' => $path];
        } finally {
            $helper->disconnect();
        }
    }

    /**
     * Скачать файл.
     */
    public function actionDownload()
    {
        $serverId = (int)Yii::$app->request->get('server_id');
        $path = (string)Yii::$app->request->get('path', '');
        if ($path === '') {
            throw new NotFoundHttpException();
        }
        $server = $this->findServerWithFtp($serverId);
        $helper = new FtpHelper($server);
        if (!$helper->connect()) {
            throw new \yii\web\ServerErrorHttpException('Не удалось подключиться к FTP');
        }
        $tmpPath = $helper->download($path, true);
        if ($tmpPath === false) {
            $helper->disconnect();
            throw new NotFoundHttpException('Файл не найден или недоступен');
        }
        $filename = basename($path);
        $mime = $this->getMimeType($filename);
        return Yii::$app->response->sendFile($tmpPath, $filename, [
            'mimeType' => $mime,
            'inline' => false,
        ])->on(Response::EVENT_AFTER_SEND, function () use ($tmpPath) {
            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        });
    }

    /**
     * API: загрузить файл.
     */
    public function actionUpload()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $serverId = (int)Yii::$app->request->post('server_id');
        $path = (string)Yii::$app->request->post('path', '/');
        $file = UploadedFile::getInstanceByName('file');
        if (!$file) {
            return ['success' => false, 'error' => 'Файл не выбран'];
        }
        $server = $this->findServerWithFtp($serverId);
        $helper = new FtpHelper($server);
        if (!$helper->connect()) {
            return ['success' => false, 'error' => 'Не удалось подключиться к FTP'];
        }
        $remotePath = rtrim(str_replace('\\', '/', $path), '/') . '/' . $file->name;
        if ($helper->upload($file->tempName, $remotePath)) {
            return ['success' => true, 'path' => $remotePath];
        }
        return ['success' => false, 'error' => 'Ошибка загрузки'];
    }

    /**
     * API: удалить файл или пустую папку.
     */
    public function actionDelete()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $serverId = (int)Yii::$app->request->post('server_id');
        $path = (string)Yii::$app->request->post('path', '');
        if ($path === '') {
            return ['success' => false, 'error' => 'Путь не указан'];
        }
        $server = $this->findServerWithFtp($serverId);
        $helper = new FtpHelper($server);
        if (!$helper->connect()) {
            return ['success' => false, 'error' => 'Не удалось подключиться к FTP'];
        }
        if ($helper->delete($path)) {
            return ['success' => true];
        }
        return ['success' => false, 'error' => 'Не удалось удалить (возможно, папка не пуста)'];
    }

    /**
     * API: получить содержимое файла для редактирования.
     */
    public function actionGetContent()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $serverId = (int)Yii::$app->request->get('server_id');
        $path = (string)Yii::$app->request->get('path', '');
        if ($path === '') {
            return ['success' => false, 'error' => 'Путь не указан'];
        }
        $server = $this->findServerWithFtp($serverId);
        $helper = new FtpHelper($server);
        if (!$helper->connect()) {
            return ['success' => false, 'error' => 'Не удалось подключиться к FTP'];
        }
        $content = $helper->getFileContent($path);
        if ($content === false) {
            return ['success' => false, 'error' => 'Не удалось прочитать файл'];
        }
        return ['success' => true, 'content' => $content];
    }

    /**
     * API: сохранить содержимое файла.
     */
    public function actionSaveContent()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $serverId = (int)Yii::$app->request->post('server_id');
        $path = (string)Yii::$app->request->post('path', '');
        $content = (string)Yii::$app->request->post('content', '');
        if ($path === '') {
            return ['success' => false, 'error' => 'Путь не указан'];
        }
        $server = $this->findServerWithFtp($serverId);
        $helper = new FtpHelper($server);
        if (!$helper->connect()) {
            return ['success' => false, 'error' => 'Не удалось подключиться к FTP'];
        }
        if ($helper->putFileContent($path, $content)) {
            return ['success' => true];
        }
        return ['success' => false, 'error' => 'Не удалось сохранить файл'];
    }

    /**
     * API: создать папку.
     */
    public function actionCreateDir()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $serverId = (int)Yii::$app->request->post('server_id');
        $path = (string)Yii::$app->request->post('path', '/');
        $name = (string)Yii::$app->request->post('name', '');
        $name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $name);
        if ($name === '') {
            return ['success' => false, 'error' => 'Имя папки не задано'];
        }
        $server = $this->findServerWithFtp($serverId);
        $helper = new FtpHelper($server);
        if (!$helper->connect()) {
            return ['success' => false, 'error' => 'Не удалось подключиться к FTP'];
        }
        $fullPath = rtrim(str_replace('\\', '/', $path), '/') . '/' . $name;
        if ($helper->createDir($fullPath)) {
            return ['success' => true, 'path' => $fullPath];
        }
        return ['success' => false, 'error' => 'Не удалось создать папку'];
    }

    private function findServerWithFtp(int $id): Servers
    {
        $server = Servers::findOne($id);
        if (!$server || !$server->hasFtpCredentials()) {
            throw new NotFoundHttpException('Сервер не найден или FTP не настроен');
        }
        return $server;
    }

    private function getMimeType(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $map = [
            'txt' => 'text/plain',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'zip' => 'application/zip',
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }
}
