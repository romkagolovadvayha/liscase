<?php

namespace backend\controllers;

use backend\components\BackendController;
use common\components\calendar\RfCalendarHighlightHelper;
use common\components\helpers\Role;
use common\models\servers\Servers;
use common\models\wipe_calendar\WipeCalendarEvent;
use yii\filters\VerbFilter;
use yii\web\Response;
use Yii;

class WipeCalendarController extends BackendController
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'matchCallback' => static function (): bool {
                            $u = Yii::$app->user;
                            return $u->can(Role::ROLE_ADMIN)
                                || $u->can(Role::ROLE_MODERATOR)
                                || $u->can(Role::ROLE_SUPPORT);
                        },
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete-event' => ['POST'],
                    'save-event' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex(): string
    {
        $this->view->title = Yii::t('common', 'Календарь вайпов');

        return $this->render('index');
    }

    /**
     * Список серверов для панели (все статусы из {@see Servers::getStatusList()}).
     */
    public function actionServers(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $rows = Servers::find()
            ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
            ->asArray()
            ->all();

        $statusList = Servers::getStatusList();
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r['id'],
                'name' => (string) $r['name'],
                'tag' => (string) ($r['tag'] ?? ''),
                'status' => (int) $r['status'],
                'statusLabel' => $statusList[(int) $r['status']] ?? (string) $r['status'],
            ];
        }

        return ['servers' => $out];
    }

    /**
     * События FullCalendar: GET start, end (ISO date or datetime).
     */
    public function actionEvents(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $start = Yii::$app->request->get('start');
        $end = Yii::$app->request->get('end');
        if (empty($start) || empty($end)) {
            return ['events' => []];
        }

        $startDay = substr((string) $start, 0, 10);
        $endDay = substr((string) $end, 0, 10);

        $models = WipeCalendarEvent::find()
            ->with('server')
            ->andWhere(['>=', 'event_at', $startDay . ' 00:00:00'])
            ->andWhere(['<=', 'event_at', $endDay . ' 23:59:59'])
            ->orderBy(['event_at' => SORT_ASC])
            ->all();

        $events = [];
        foreach ($models as $m) {
            $events[] = self::eventToFc($m);
        }

        return ['events' => $events];
    }

    /**
     * Подсветка дней: GET start, end (Y-m-d).
     */
    public function actionHighlights(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $start = Yii::$app->request->get('start');
        $end = Yii::$app->request->get('end');
        if (empty($start) || empty($end)) {
            return ['days' => []];
        }
        $startDay = substr((string) $start, 0, 10);
        $endDay = substr((string) $end, 0, 10);

        return [
            'days' => RfCalendarHighlightHelper::highlightsBetween($startDay, $endDay),
        ];
    }

    public function actionSaveEvent(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $req = Yii::$app->request;
        $id = $req->post('id');
        $model = null;
        if ($id !== null && $id !== '') {
            $model = WipeCalendarEvent::findOne((int) $id);
            if ($model === null) {
                return ['success' => false, 'message' => 'Событие не найдено'];
            }
        } else {
            $model = new WipeCalendarEvent();
        }

        $type = (string) $req->post('event_type', '');
        $model->event_type = $type;

        $serverId = $req->post('server_id');
        $model->server_id = $serverId === null || $serverId === '' ? null : (int) $serverId;

        $title = $req->post('title');
        $model->title = $title === null || $title === '' ? null : (string) $title;

        $date = (string) $req->post('date', '');
        $time = (string) $req->post('time', '16:00');
        if (strlen($date) < 10) {
            return ['success' => false, 'message' => 'Укажите дату'];
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time = '16:00';
        }
        $model->event_at = substr($date, 0, 10) . ' ' . $time . ':00';

        if (!$model->save()) {
            return [
                'success' => false,
                'message' => implode('; ', $model->getFirstErrors()),
                'errors' => $model->errors,
            ];
        }

        $model->refresh();
        $model->populateRelation('server', $model->server_id ? Servers::findOne($model->server_id) : null);

        return [
            'success' => true,
            'event' => self::eventToFc($model),
        ];
    }

    public function actionDeleteEvent(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $id = (int) Yii::$app->request->post('id');
        $model = WipeCalendarEvent::findOne($id);
        if ($model === null) {
            return ['success' => false, 'message' => 'Событие не найдено'];
        }
        if ($model->delete() === false) {
            return ['success' => false, 'message' => 'Не удалось удалить'];
        }

        return ['success' => true];
    }

    private static function eventToFc(WipeCalendarEvent $m): array
    {
        $title = $m->getCalendarTitle();
        $t = $m->event_type;
        $colorMap = [
            WipeCalendarEvent::TYPE_MAP_WIPE => '#0d6efd',
            WipeCalendarEvent::TYPE_GLOBAL_WIPE => '#dc3545',
            WipeCalendarEvent::TYPE_GAME_UPDATE => '#fd7e14',
            WipeCalendarEvent::TYPE_CUSTOM => '#6f42c1',
        ];

        return [
            'id' => (string) $m->id,
            'title' => $title,
            'start' => str_replace(' ', 'T', $m->event_at),
            'backgroundColor' => $colorMap[$t] ?? '#6c757d',
            'borderColor' => $colorMap[$t] ?? '#6c757d',
            'extendedProps' => [
                'event_type' => $m->event_type,
                'server_id' => $m->server_id,
                'title' => $m->title,
                'rawTitle' => $title,
            ],
        ];
    }
}
