<?php

namespace console\controllers;

use common\components\helpers\Role;
use common\components\queue\support\OpenAiJob;
use common\models\support\Support;
use common\models\support\SupportMessage;
use common\models\support\SupportRead;
use common\models\user\User;
use common\models\user\UserTop;
use Yii;
use common\models\box\Box;
use yii\base\BaseObject;
use yii\console\Controller;

class SupportController extends Controller
{
    /**
     * support/check
     * @throws \Exception
     */
    public function actionCheck()
    {
        /** @var Support[] $tickets */
        $tickets = Support::find()
            ->andWhere(['status' => Support::STATUS_OPEN])
            ->all();

        $createdAt = new \DateTime();
        $createdAt->modify('-' . Yii::$app->settings->get('openAi_sleep') . ' second');

        foreach ($tickets as $ticket) {
            /** @var SupportMessage $message */
            $message = SupportMessage::find()
                ->andWhere(['<=', 'created_at', $createdAt->format('Y-m-d H:i:s')])
                ->andWhere(['support_id' => $ticket->id])
                ->orderBy(['id' => SORT_DESC])
                ->one();

            if (empty($message->user) || $message->user->canRoles([Role::ROLE_MODERATOR, Role::ROLE_ADMIN])) {
                continue;
            }


            if ($ticket->is_bot) {
                Yii::$app->queueSupport->push(new OpenAiJob([
                                                                'chatId' => $ticket->id,
                                                                'userId' => $message->user_id,
                                                                'ownerUserId' => $ticket->user_id,
                                                                'message' => $message->message,
                                                                'username' => $message->user->username,
                                                                'chatNumber' => $ticket->getNumber(),
                                                            ]));
            }

//            $domain = Yii::$app->settings->get('site_domain');

//            $text = "❗️Имеется не отвеченный тикет более 10 минут.";
//            $text .= PHP_EOL. "Имя: {$message->user->username}";
//            $text .= PHP_EOL. "Сообщение: {$message->message}";
//            $text .= PHP_EOL. "<a href=\"https://{$domain}/support/ticket?id={$ticket->getNumber()}\">Перейти к тикету</a>";

//            Yii::$app->telegramSupport->sendMessage($text);
        }

    }

    /**
     * support/empty
     * @throws \Exception
     */
    public function actionEmpty()
    {

    }

    /**
     * support/check-closed
     * @throws \Exception
     */
    public function actionCheckClosed()
    {
        /** @var SupportRead[] $undeadTitkets */
        $undeadTitketsIds = SupportRead::find()
            ->alias('sr')
            ->joinWith('support s')
            ->select('DISTINCT(s.id)')
            ->andWhere(['s.status' => Support::STATUS_CLOSED])
            ->andWhere(['sr.status' => SupportRead::STATUS_UNREAD])
            ->createCommand()
            ->queryColumn();

        foreach ($undeadTitketsIds as $id) {
            SupportRead::readedAll($id);
            echo $id . PHP_EOL;
        }
    }

}
