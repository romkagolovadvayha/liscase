<?php

namespace backend\controllers;

use backend\components\BackendController;
use common\components\helpers\Role;
use common\models\rcon\RconTasks;
use common\models\servers\Servers;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;

/**
 * RconController для выполнения RCON команд на всех серверах
 */
class RconController extends BackendController
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'execute' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Отображает форму для ввода команды и результаты выполнения
     * @return string
     */
    public function actionIndex()
    {
        $command = Yii::$app->request->post('command', '');
        $selectedServers = Yii::$app->request->post('servers', []);
        $results = [];

        // Получаем список всех активных серверов
        /** @var Servers[] $allServers */
        $allServers = Servers::find()
            ->andWhere(['status' => Servers::STATUS_ACTIVE])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        // Если серверы не выбраны, выбираем все по умолчанию
        if (empty($selectedServers) && !empty($allServers)) {
            $selectedServers = array_map(function($server) {
                return $server->tag;
            }, $allServers);
        }

        if (!empty($command) && Yii::$app->request->isPost && !empty($selectedServers)) {
            $results = RconTasks::executeWithResults($command, $selectedServers);
        }

        return $this->render('index', [
            'command' => $command,
            'results' => $results,
            'allServers' => $allServers,
            'selectedServers' => $selectedServers,
        ]);
    }

    /**
     * Выполняет команду через AJAX и возвращает JSON
     * @return \yii\web\Response
     */
    public function actionExecute()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $command = Yii::$app->request->post('command', '');
        $selectedServers = Yii::$app->request->post('servers', []);
        
        if (empty($command)) {
            return [
                'success' => false,
                'message' => 'Команда не может быть пустой',
            ];
        }

        // Если серверы не выбраны, выбираем все активные
        if (empty($selectedServers)) {
            $allServers = Servers::find()
                ->andWhere(['status' => Servers::STATUS_ACTIVE])
                ->all();
            $selectedServers = array_map(function($server) {
                return $server->tag;
            }, $allServers);
        }

        $results = RconTasks::executeWithResults($command, $selectedServers);
        
        // Форматируем результаты для JSON ответа
        $formattedResults = [];
        foreach ($results as $tag => $result) {
            $formattedResults[] = [
                'server' => $result['server']->name . ' (' . $tag . ')',
                'result' => $result['result'],
                'error' => $result['error'],
            ];
        }

        return [
            'success' => true,
            'results' => $formattedResults,
        ];
    }
}

