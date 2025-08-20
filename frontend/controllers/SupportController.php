<?php

namespace frontend\controllers;

use common\components\helpers\DateHelper;
use common\components\helpers\FileHelper;
use common\components\helpers\Role;
use common\controllers\WebController;
use common\models\support\SupportFile;
use common\models\support\SupportRead;
use common\models\user\User;
use WebSocket\Client;
use Yii;
use common\models\support\Support;
use common\models\support\SupportMessage;
use frontend\forms\buildings\BuildingForm;
use frontend\forms\support\SupportForm;
use frontend\models\support\SupportSearch;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * SupportController implements the CRUD actions for Support model.
 */
class SupportController extends WebController
{
    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        return array_merge(
            parent::behaviors(),
            [
                'verbs' => [
                    'class' => VerbFilter::className(),
                    'actions' => [
                        'delete' => ['POST'],
                    ],
                ],
            ]
        );
    }

    /**
     * Lists all Support models.
     *
     * @return string|\yii\web\Response
     */
    public function actionIndex()
    {
        if (\Yii::$app->user->isGuest) {
            $this->view->title = Yii::t('common', 'Поддержка');
            $this->view->params['page'] = 'support';
            return $this->render('auth.twig');
        }
        $user = \Yii::$app->user->identity;
        if ($user->blocked_support) {
            return $this->render('blocked');
        }

        $unreadMessages = SupportRead::find()
                                     ->select(['support_id', 'cnt' => new \yii\db\Expression('COUNT(*)')])
                                     ->where(['user_id' => $user->id, 'status' => SupportRead::STATUS_UNREAD])
                                     ->asArray()
                                     ->groupBy('support_id')
                                     ->indexBy('support_id')
                                     ->all();

        $ticketsQuery = SupportSearch::find();
        $activeTicket = null;
        if (!$user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR])) {
            $ticketsQuery->andWhere(['user_id' => Yii::$app->user->id]);
        }

        $ticketsQuery->orderBy(['status' => SORT_ASC, 'updated_at' => SORT_DESC]);
        /** @var Support[] $tickets */
        $tickets = $ticketsQuery->limit(20)->all();
        if ($user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR])) {
            $activeTicket = $tickets[0];
        } else {
            foreach ($tickets as $ticket) {
                if ($ticket->status === Support::STATUS_OPEN) {
                    $activeTicket = $ticket;
                    break;
                }
            }
        }

        if (!empty($activeTicket)) {
            return $this->redirect(['ticket', 'id' => $activeTicket->getNumber()]);
        }

        $user = \Yii::$app->user->identity;
        $nModel = new Support();
        $nModel->user_id = $user->id;
        $nModel->status = Support::STATUS_OPEN;
        $nModel->created_at = date('Y-m-d H:i:s');
        $nModel->updated_at = date('Y-m-d H:i:s');

        $fModels = [];
        $fModel = new SupportMessage();
        $fModel->user_id = null;
        $fModel->message = "{USER_INFO}";
        $fModel->support_id = null;
        $fModel->created_at = date('Y-m-d H:i:s');
        $fModels[] = $fModel;

        $fModel = clone $fModel;
        $fModel->message = "{ALERT_REPORT}";
        $fModels[] = $fModel;

        $this->view->params['page'] = 'support';

        return $this->render('view', [
            'model' => $nModel,
            'user' => $user,
            'tickets' => $tickets,
            'fModels' => $fModels,
            'unreadMessages' => $unreadMessages,
        ]);
    }

    private function mime2ext($mime) {
        $mime_map = [
            'application/x-compressed'                                                  => '7zip',
            'video/x-f4v'                                                               => 'f4v',
            'video/x-flv'                                                               => 'flv',
            'image/gif'                                                                 => 'gif',
            'application/x-gtar'                                                        => 'gtar',
            'application/x-gzip'                                                        => 'gzip',
            'image/jp2'                                                                 => 'jp2',
            'video/mj2'                                                                 => 'jp2',
            'image/jpx'                                                                 => 'jp2',
            'image/jpm'                                                                 => 'jp2',
            'image/png'                                                                => 'png',
            'image/jpeg'                                                                => 'jpeg',
            'image/pjpeg'                                                               => 'jpeg',
            'video/quicktime'                                                           => 'mov',
            'video/x-sgi-movie'                                                         => 'movie',
            'audio/mpeg'                                                                => 'mp3',
            'audio/mpg'                                                                 => 'mp3',
            'audio/mpeg3'                                                               => 'mp3',
            'audio/mp3'                                                                 => 'mp3',
            'video/mp4'                                                                 => 'mp4',
            'video/mpeg'                                                                => 'mpeg',
            'application/x-photoshop'                                                   => 'psd',
            'image/vnd.adobe.photoshop'                                                 => 'psd',
            'application/x-rar'                                                         => 'rar',
            'application/rar'                                                           => 'rar',
            'application/x-rar-compressed'                                              => 'rar',
            'image/svg+xml'                                                             => 'svg',
            'audio/x-wav'                                                               => 'wav',
            'audio/wave'                                                                => 'wav',
            'audio/wav'                                                                 => 'wav',
            'video/webm'                                                                => 'webm',
            'image/webp'                                                                => 'webp',
            'video/x-ms-wmv'                                                            => 'wmv',
            'video/x-ms-asf'                                                            => 'wmv',
            'application/x-zip'                                                         => 'zip',
            'application/zip'                                                           => 'zip',
            'application/x-zip-compressed'                                              => 'zip',
            'application/s-compressed'                                                  => 'zip',
            'multipart/x-zip'                                                           => 'zip',
        ];

        return isset($mime_map[$mime]) ? $mime_map[$mime] : false;
    }

    public function actionMute($support_id, $user_id, $blocked = true) {
        $user = \Yii::$app->user->identity;
        if (!$user->canRoles([Role::ROLE_MODERATOR, Role::ROLE_ADMIN])) {
            return $this->redirect(['ticket', 'id' => $support_id]);
        }
        $ticket = Support::findByNumber($support_id);
        if (empty($ticket)) {
            return $this->redirect(['/support']);
        }

        $player = User::findOne($user_id);
        if (empty($player)) {
            return $this->redirect(['/support']);
        }
        if ($player->canRoles([Role::ROLE_MODERATOR, Role::ROLE_ADMIN])) {
            return $this->redirect(['/support']);
        }

        $mModel = new SupportMessage();
        $mModel->user_id = null;
        if ($blocked) {
            $mModel->message = "Чат игрока <span class=\"support_messages_item_message_success\">{$player->username}</span> заблокирован пользователем <span class=\"support_messages_item_message_success\">{$user->username}</span> на 30 минут.";
        } else {
            $mModel->message = "Чат игрока <span class=\"support_messages_item_message_success\">{$player->username}</span> разблокирован пользователем <span class=\"support_messages_item_message_success\">{$user->username}</span>";
        }
        $mModel->support_id = $ticket->id;
        $mModel->created_at = date('Y-m-d H:i:s');
        $mModel->save();

        $date = new \DateTime();
        if ($blocked) {
            $date->modify('+30 minute');
        } else {
            $date->modify('-30 minute');
        }
        $player->blocked_support_at = $date->format('Y-m-d H:i:s');
        $player->save();

        try {
            $client = new Client(Yii::$app->params['ws']);
            $client->send(
                json_encode(
                    [
                        'action' => 'supportStatus',
                        'code'   => 200,
                        'id'     => $ticket->getNumber(),
                    ]
                )
            );
            $client->close();
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('actionBlocked: ' . $ex->getMessage());
        }

        return $this->redirect(['ticket', 'id' => $support_id]);
    }

    public function actionBlockedChat($support_id, $user_id, $blocked = true) {
        $user = \Yii::$app->user->identity;
        if (!$user->canRoles([Role::ROLE_MODERATOR, Role::ROLE_ADMIN])) {
            return $this->redirect(['ticket', 'id' => $support_id]);
        }
        $ticket = Support::findByNumber($support_id);
        if (empty($ticket)) {
            return $this->redirect(['/support']);
        }

        $player = User::findOne($user_id);
        if (empty($player)) {
            return $this->redirect(['/support']);
        }
        if ($player->canRoles([Role::ROLE_MODERATOR, Role::ROLE_ADMIN])) {
            return $this->redirect(['/support']);
        }

        $mModel = new SupportMessage();
        $mModel->user_id = null;
        if ($blocked) {
            $mModel->message = "Чат игрока <span class=\"support_messages_item_message_success\">{$player->username}</span> заблокирован пользователем <span class=\"support_messages_item_message_success\">{$user->username}</span> навсегда.";
        } else {
            $mModel->message = "Чат игрока <span class=\"support_messages_item_message_success\">{$player->username}</span> разблокирован пользователем <span class=\"support_messages_item_message_success\">{$user->username}</span>.";
        }
        $mModel->support_id = $ticket->id;
        $mModel->created_at = date('Y-m-d H:i:s');
        $mModel->save();

        $player->blocked_support = $blocked;
        $player->save();

        try {
            $client = new Client(Yii::$app->params['ws']);
            $client->send(
                json_encode(
                    [
                        'action' => 'supportStatus',
                        'code'   => 200,
                        'id'     => $ticket->getNumber(),
                    ]
                )
            );
            $client->close();
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('actionBlocked: ' . $ex->getMessage());
        }

        return $this->redirect(['ticket', 'id' => $support_id]);
    }

    public function actionBlocked($support_id, $user_id, $blocked = true) {
        $user = \Yii::$app->user->identity;
        if (!$user->canRoles([Role::ROLE_MODERATOR, Role::ROLE_ADMIN])) {
            return $this->redirect(['ticket', 'id' => $support_id]);
        }
        $ticket = Support::findByNumber($support_id);
        if (empty($ticket)) {
            return $this->redirect(['/support']);
        }

        $player = User::findOne($user_id);
        if (empty($player)) {
            return $this->redirect(['/support']);
        }
        if ($player->canRoles([Role::ROLE_MODERATOR, Role::ROLE_ADMIN])) {
            return $this->redirect(['/support']);
        }

        $mModel = new SupportMessage();
        $mModel->user_id = null;
        if ($blocked) {
            $mModel->message = "Игрок <span class=\"support_messages_item_message_success\">{$player->username}</span> заблокирован пользователем <span class=\"support_messages_item_message_success\">{$user->username}</span> навсегда.";
        } else {
            $mModel->message = "Игрок <span class=\"support_messages_item_message_success\">{$player->username}</span> разблокирован пользователем <span class=\"support_messages_item_message_success\">{$user->username}</span>.";
        }
        $mModel->support_id = $ticket->id;
        $mModel->created_at = date('Y-m-d H:i:s');
        $mModel->save();

        if ($blocked) {
            $player->ban('Бан в чате поддержки', $user->id);
        } else {
            $player->unban();
        }

        try {
            $client = new Client(Yii::$app->params['ws']);
            $client->send(
                json_encode(
                    [
                        'action' => 'supportStatus',
                        'code'   => 200,
                        'id'     => $ticket->getNumber(),
                    ]
                )
            );
            $client->close();
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('actionBlocked: ' . $ex->getMessage());
        }

        return $this->redirect(['ticket', 'id' => $support_id]);
    }

    public function actionCreatePresignedRequest($fileName, $s) {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $exp = null;

        $size = $s / 1000000;
        if ($size > 1024 * 2) {
            return ['code' => 400, 'message' => Yii::t('common', "Превышен максимальный обьем файла 2GB")];
        }
        if (in_array(substr($fileName, -3), ['png', 'txt', 'jpg', 'gif', 'mp4', 'mov', 'avi', 'mp3', 'ogg'])) {
            $exp = substr($fileName, -3);
        }
        if (empty($exp) && in_array(substr($fileName, -4), ['jpeg', 'webm'])) {
            $exp = substr($fileName, -4);
        }

        if (empty($exp)) {
            return ['code' => 400, 'message' => Yii::t('common', "Неверный формат файла!")];
        }

        $user = \Yii::$app->user->identity;
        if ($user->blocked_support) {
            return ['code' => 400, 'message' => Yii::t('common', "Ваш чат заблокирован")];
        }

        $fileName = $user->id . "_" . md5(time()) . ".{$exp}";
        $cacheKey = 'actionCreatePresignedRequest_' . $user->id;
        if (!empty(Yii::$app->cache->get($cacheKey))) {
            return ['code' => 400, 'message' => Yii::$app->cache->get($cacheKey)];
        }
        Yii::$app->cache->set($cacheKey, Yii::t('common', "Нельзя выполнять действие слишком часто!"), 5);

        $path = 'support/' . $fileName;
        $presignedUrl = Yii::$app->s3Api->createPresignedRequest($path);

        return ['code' => 200, 'url' => $presignedUrl, 'filename' => $fileName];
    }

    public function actionUploadFileSave($mimetype, $newFilename, $fileName, $id) {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!Yii::$app->request->isPost) {
            return ['code' => 400, 'message' => Yii::t('common', "Неверный запрос!")];
        }

        if (!in_array(substr($newFilename, -3), ['png', 'txt', 'jpg', 'gif', 'mp4', 'mov', 'avi', 'mp3', 'ogg'])
            && !in_array(substr($newFilename, -4), ['jpeg', 'webm'])) {
            return ['code' => 400, 'message' => Yii::t('common', "Неверный формат файла!")];
        }

        $user = \Yii::$app->user->identity;
        if ($user->blocked_support) {
            return ['code' => 400, 'message' => Yii::t('common', "Ваш чат заблокирован")];
        }
        $cacheKey = 'actionUploadFileSave_' . $user->id;
        if (!empty(Yii::$app->cache->get($cacheKey))) {
            return ['code' => 400, 'message' => Yii::$app->cache->get($cacheKey)];
        }
        Yii::$app->cache->set($cacheKey, Yii::t('common', "Нельзя выполнять действие слишком часто!"), 5);

        $chat = Support::findByNumber($id);
        $redirect = false;
        if (empty($chat)) {
            $chat = new Support();
            $chat->user_id = $user->id;
            $chat->status = Support::STATUS_OPEN;
            $chat->server_tag = !empty($user->server_id) ? $user->server->tag : null;
            $chat->created_at = date('Y-m-d H:i:s');
            $chat->updated_at = date('Y-m-d H:i:s');
            $chat->save(false);
            $mModel = new SupportMessage();
            $mModel->user_id = null;
            $mModel->message = "{USER_INFO}";
            $mModel->support_id = $chat->id;
            $mModel->created_at = date('Y-m-d H:i:s');
            $mModel->save();
            $redirect = true;
        } else {
            if (!$user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR]) && $chat->user_id !== $user->id) {
                return ['code' => 403, 'message' => Yii::t('common', "Доступ запрещен!")];
            }
            $chat->updated_at = date('Y-m-d H:i:s');
            $chat->save(false);
        }
        $message = new SupportMessage();
        $message->user_id = $user->id;
        $message->message = null;
        $message->support_id = $chat->id;
        $message->created_at = date('Y-m-d H:i:s');
        $message->save();
        $file = new SupportFile();
        $file->support_message_id = $message->id;
        $file->file = $newFilename;
        $file->filename = $fileName;
        $file->mimetype = $mimetype;
        $file->created_at = date('Y-m-d H:i:s');
        $file->save();
        try {
            $client = new Client(Yii::$app->params['ws']);
            $client->send(
                json_encode(
                    [
                        'action' => 'chatUpdate',
                        'code'   => 200,
                        'id'     => $chat->getNumber(),
                        'user_id'     => $chat->user_id,
                        'messageId'     => $message->id,
                    ]
                )
            );
            $client->close();
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('actionUploadFile: ' . $ex->getMessage());
        }

        if ($redirect) {
            return ['code' => 200, 'redirect' => $chat->getUrl()];
        }

        return ['code' => 200];
    }

//    public function actionUploadFile($id) {
//        if (empty($_FILES) || empty($_FILES['file'])) {
//            return;
//        }
//        $fileData = $_FILES['file'];
//        $fileName = $fileData['name'];
//        $mimeType = $fileData['type'];
//        try {
//            $user = \Yii::$app->user->identity;
//            $cacheKey = 'commandChatFile_' . $user->id;
//            if (!empty(Yii::$app->cache->get($cacheKey))) {
////                $client->send(json_encode(['type' => 'error', 'error' => Yii::$app->cache->get($cacheKey)]));
//                return;
//            }
//            Yii::$app->cache->set($cacheKey, Yii::t('common', "Нельзя отправлять сообщения слишком часто!", [], $user->current_language), 5);
//            $exp = $this->mime2ext($mimeType);
//            if (empty($exp)) {
////                $client->send(json_encode(['type' => 'error', 'error' => Yii::t('common', "Не верный формат файла!", [], $user->current_language)]));
//                return;
//            }
//            $decodedData = file_get_contents($fileData['tmp_name']);
//            $newFileName = $id . "_" . md5(time()) . ".{$exp}";
//            Yii::$app->s3Api->uploadFile('support/' . $newFileName, $decodedData);
//            $chat = Support::findByNumber($id);
//            if (empty($chat)) {
//                $chat = new Support();
//                $chat->user_id = $user->id;
//                $chat->status = Support::STATUS_OPEN;
//                $chat->server_tag = !empty($user->server_id) ? $user->server->tag : null;
//                $chat->created_at = date('Y-m-d H:i:s');
//                $chat->updated_at = date('Y-m-d H:i:s');
//                $chat->save(false);
//                $mModel = new SupportMessage();
//                $mModel->user_id = null;
//                $mModel->message = "{USER_INFO}";
//                $mModel->support_id = $chat->id;
//                $mModel->created_at = date('Y-m-d H:i:s');
//                $mModel->save();
////                $client->send(json_encode(['type' => 'redirect', 'url' => $chat->getUrl()]));
//            } else {
//                $chat->updated_at = date('Y-m-d H:i:s');
//                $chat->save(false);
//            }
//            $message = new SupportMessage();
//            $message->user_id = $user->id;
//            $message->message = null;
//            $message->support_id = $chat->id;
//            $message->created_at = date('Y-m-d H:i:s');
//            $message->save();
//            if (!empty($message->getErrors())) {
//                print_r($message->getErrors());
//            }
//            $filename = htmlspecialchars(\yii\helpers\HtmlPurifier::process($fileName));
//            $file = new SupportFile();
//            $file->support_message_id = $message->id;
//            $file->file = $newFileName;
//            $file->filename = $filename;
//            $file->mimetype = $mimeType;
//            $file->created_at = date('Y-m-d H:i:s');
//            $file->save();
//
//            try {
//                $client = new Client(Yii::$app->params['ws']);
//                $client->send(
//                    json_encode(
//                        [
//                            'action' => 'ticketUpdate',
//                            'code'   => 200,
//                            'user_id'     => $user->id,
//                        ]
//                    )
//                );
//            } catch (\Exception $ex) {
//                Yii::$app->telegramChats->sendMessage('actionUploadFile: ' . $ex->getMessage());
//            }
//            if (!empty($file->getErrors())) {
//                print_r($file->getErrors());
//            }
//        } catch (\Exception $e) {
//            echo "commandChatFile: " . $e->getMessage() . PHP_EOL;
//        }
//    }

    /**
     * Displays a single Support model.
     *
     * @param int $id ID
     *
     * @return string|Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionTicket($id)
    {
        if (\Yii::$app->user->isGuest) {
            $this->view->title = Yii::t('common', 'Поддержка');
            $this->view->params['page'] = 'support';
            return $this->render('auth.twig');
        }
        $user = \Yii::$app->user->identity;

        $unreadMessages = SupportRead::find()
                          ->select(['support_id', 'cnt' => new \yii\db\Expression('COUNT(*)')])
                          ->where(['user_id' => $user->id, 'status' => SupportRead::STATUS_UNREAD])
                          ->asArray()
                          ->groupBy('support_id')
                          ->indexBy('support_id')
                          ->all();

        if ($user->blocked_support) {
            return $this->redirect(['/support']);
        }

        $this->view->title = Yii::t('common', 'Тикет') . "  ID" . $id;
        $this->view->params['page'] = 'support';
        $model = $this->findModel($id);

        if (!empty($unreadMessages[$model->id]) && $unreadMessages[$model->id]['cnt'] > 0) {
            SupportRead::readedAll($model->id, $user->id);
        }

        $ticketsQuery = SupportSearch::find();
        if (!$user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR])) {
            $ticketsQuery->andWhere(['user_id' => Yii::$app->user->id]);
        }
        $ticketsQuery->orderBy(['status' => SORT_ASC, 'updated_at' => SORT_DESC]);
        $tickets = $ticketsQuery->limit(20)->all();

        if (!$user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR]) && $model->user->id !== Yii::$app->user->id) {
            throw new ForbiddenHttpException(Yii::t('common', 'Ошибка доступа'));
        }

        return $this->render('view', [
            'model' => $model,
            'user' => $user,
            'tickets' => $tickets,
            'unreadMessages' => $unreadMessages,
        ]);
    }

    public function actionTicketClose($id)
    {
        $user = \Yii::$app->user->identity;
        $model = $this->findModel($id);
        if (empty($user)) {
            return $this->redirect(['ticket', 'id' => $model->getNumber()]);
        }
        if (!$user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR]) && $model->user->id !== Yii::$app->user->id) {
            throw new ForbiddenHttpException(Yii::t('common', 'Ошибка доступа'));
        }
        if ($model->status !== Support::STATUS_OPEN) {
            throw new ForbiddenHttpException(Yii::t('common', 'Тикет уже закрыт!'));
        }

        $mModel = new SupportMessage();
        $mModel->user_id = null;
        $mModel->message = "Тикет закрыт пользователем <span class=\"support_messages_item_message_success\">{$user->username}</span>";
        $mModel->support_id = $model->id;
        $mModel->created_at = date('Y-m-d H:i:s');
        $mModel->save();

        $model->status = Support::STATUS_CLOSED;
        $model->save(false);
        SupportRead::readedAll($model->id);

        try {
            $client = new Client(Yii::$app->params['ws']);
            $client->send(
                json_encode(
                    [
                        'action' => 'supportStatus',
                        'code'   => 200,
                        'id'     => $model->getNumber(),
                    ]
                )
            );
            $client->send(
                json_encode(
                    [
                        'action' => 'ticketUpdate',
                        'code'   => 200,
                        'user_id'     => $model->user_id,
                    ]
                )
            );
            $client->close();
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('actionTicketClose: ' . $ex->getMessage());
        }

        return $this->redirect(['ticket', 'id' => $model->getNumber()]);
    }

    public function actionTicketOpen($id)
    {
        $user = \Yii::$app->user->identity;
        $model = $this->findModel($id);
        if (!$user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR]) && $model->user->id !== Yii::$app->user->id) {
            throw new ForbiddenHttpException(Yii::t('common', 'Ошибка доступа'));
        }
        if ($model->status !== Support::STATUS_CLOSED) {
            throw new ForbiddenHttpException(Yii::t('common', 'Тикет уже открыт!'));
        }

        $mModel = new SupportMessage();
        $mModel->user_id = null;
        $mModel->message = "Тикет открыт пользователем <span class=\"support_messages_item_message_success\">{$user->username}</span>";
        $mModel->support_id = $model->id;
        $mModel->created_at = date('Y-m-d H:i:s');
        $mModel->save();

        $model->status = Support::STATUS_OPEN;
        $model->save(false);

        try {
            $client = new Client(Yii::$app->params['ws']);
            $client->send(
                json_encode(
                    [
                        'action' => 'supportStatus',
                        'code'   => 200,
                        'id'     => $model->getNumber(),
                    ]
                )
            );
            $client->send(
                json_encode(
                    [
                        'action' => 'ticketUpdate',
                        'code'   => 200,
                        'user_id'     => $model->user_id,
                    ]
                )
            );
            $client->close();
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('actionTicketOpen: ' . $ex->getMessage());
        }
        return $this->redirect(['ticket', 'id' => $model->getNumber()]);
    }

    public function actionGetMessage($id)
    {
        $message = SupportMessage::findOne($id);
        if (empty($message) || (!Yii::$app->user->identity->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR]) && $message->support->user->id !== Yii::$app->user->id)) {
            throw new ForbiddenHttpException(Yii::t('common', 'Ошибка доступа'));
        }
        return $this->renderAjax('_message', [
            'model' => $message
        ]);
    }

    public function actionGetTickets($id)
    {
        $model = $this->findModel($id);
        $user = Yii::$app->user->identity;

        if (!empty($model->id) && (!$user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR]) && $model->user->id !== $user->id)) {
            throw new ForbiddenHttpException(Yii::t('common', 'Ошибка доступа'));
        }

        $ticketsQuery = SupportSearch::find();
        if (!$user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR])) {
            $ticketsQuery->andWhere(['user_id' => Yii::$app->user->id]);
        }
        $ticketsQuery->orderBy(['status' => SORT_ASC, 'updated_at' => SORT_DESC]);
        $tickets = $ticketsQuery->limit(100)->all();

        return $this->renderAjax('_tickets', [
            'model' => $model,
            'tickets' => $tickets,
            'user' => $user,
        ]);
    }

    /**
     * Finds the Support model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return Support the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($number)
    {
        if ($number - 43242 == 0) {
            return new Support();
        }
        if (($model = Support::findByNumber($number)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
