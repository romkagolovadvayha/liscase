<?php

namespace backend\controllers;

use backend\components\BackendController;
use common\components\helpers\Role;
use common\models\rcon\RconTasks;
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
        $results = [];

        if (!empty($command) && Yii::$app->request->isPost) {
            $results = RconTasks::executeWithResults($command);
        }

        return $this->render('index', [
            'command' => $command,
            'results' => $results,
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
        
        if (empty($command)) {
            return [
                'success' => false,
                'message' => 'Команда не может быть пустой',
            ];
        }

        $results = RconTasks::executeWithResults($command);
        
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

