<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ServerYearReviewController extends WebController
{
    /**
     * Метрики сервера за все время
     * @param int $id ID сервера
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionIndex($id)
    {
        $server = Servers::findOne($id);
        if (!$server) {
            throw new NotFoundHttpException('Сервер не найден');
        }

        // Получаем метрики сервера
        $metrics = $server->getServerMetrics();

        $this->view->title = Yii::t('common', 'Итоги года - {server}', ['server' => Yii::t('database', $server->name)]);
        $this->view->params['page'] = 'server-year-review';
        $this->view->params['meta_description'] = Yii::t('common', 'Статистика и метрики сервера {server} за все время', ['server' => Yii::t('database', $server->name)]);

        return $this->render('index', [
            'server' => $server,
            'metrics' => $metrics,
        ]);
    }

    /**
     * Получение метрик для модального окна (AJAX)
     * @param int $id ID сервера
     * @return string|Response
     * @throws NotFoundHttpException
     */
    public function actionModal($id)
    {
        $server = Servers::findOne($id);
        if (!$server) {
            throw new NotFoundHttpException('Сервер не найден');
        }

        // Получаем метрики сервера
        $metrics = $server->getServerMetrics();

        // Получаем список всех активных серверов для выбора
        $allServers = Servers::find()
            ->where(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
            ->orderBy(['sort' => SORT_ASC, 'name' => SORT_ASC])
            ->all();

        // Получаем ID текущего пользователя для кнопки "Мои итоги"
        $currentUserId = !Yii::$app->user->isGuest ? Yii::$app->user->id : null;

        // Получаем картинки и названия для метрик
        $images = Statistics::productsImages();
        $names = Statistics::productsNames();

        // Всегда возвращаем HTML для модального окна
        Yii::$app->response->format = Response::FORMAT_HTML;
        return $this->renderPartial('modal', [
            'server' => $server,
            'metrics' => $metrics,
            'allServers' => $allServers,
            'currentUserId' => $currentUserId,
            'images' => $images,
            'names' => $names,
        ]);
    }
}
