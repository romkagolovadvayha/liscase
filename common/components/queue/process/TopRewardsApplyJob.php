<?php

namespace common\components\queue\process;

use common\models\profit\Profit;
use common\models\servers\Servers;
use common\models\user\User;
use common\models\user\UserTop;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class TopRewardsApplyJob extends BaseObject implements JobInterface
{
    /** @var string */
    public $wipe;

    /** @var string[] */
    public $serverTags = [];

    /**
     * @param \yii\queue\Queue $queue
     * @return void
     */
    public function execute($queue)
    {
        try {
            if (empty($this->wipe) || empty($this->serverTags)) {
                return;
            }

            /** @var Servers[] $servers */
            $servers = Servers::find()
                ->andWhere(['IN', 'tag', $this->serverTags])
                ->andWhere(['IN', 'status', [Servers::STATUS_NOACTIVE, Servers::STATUS_ACTIVE]])
                ->all();

            if (empty($servers)) {
                return;
            }

            $tgMessage = [];

            foreach ($servers as $server) {
                $tops = UserTop::getUserTops($server, $this->wipe);
                foreach ($tops as $top) {
                    $label = (string)$top['label'];
                    foreach ($top['items'] as $position => $item) {
                        $steamId = (string)$item['steam_id'];
                        $amount = (int)$item['amount'];

                        $user = User::findBySteamId($steamId, false, 'top-rewards-queue');
                        if (empty($user)) {
                            continue;
                        }

                        $personalBalance = $user->getPersonalBalance();
                        if (empty($personalBalance) || empty($personalBalance->id)) {
                            continue;
                        }

                        $comment = "Награда за первое место в топе \"{$label}\"";
                        if ((int)$position === 1) {
                            $comment = "Награда за второе место в топе \"{$label}\"";
                        } elseif ((int)$position === 2) {
                            $comment = "Награда за третье место в топе \"{$label}\"";
                        }

                        $profit = new Profit();
                        $profit->status = 1;
                        $profit->type = Profit::TYPE_TOP;
                        $profit->amount = $amount;
                        $profit->user_balance_id = (int)$personalBalance->id;
                        $profit->comment = $comment;
                        $profit->created_at = date('Y-m-d H:i:s');
                        $profit->save(false);

                        if (!empty($user->telegram_chat_id)) {
                            $emoji = '🥇';
                            if ((int)$position === 1) {
                                $emoji = '🥈';
                            } elseif ((int)$position === 2) {
                                $emoji = '🥉';
                            }

                            $text = "{$emoji} {$comment} - <b>{$amount} РУБ</b>";
                            if (!empty($tgMessage[$steamId])) {
                                $tgMessage[$steamId] .= PHP_EOL . $text;
                            } else {
                                $tgMessage[$steamId] = "Вам начислены награды за ТОП на сервере {$server->name}" . PHP_EOL . $text;
                            }
                        }
                    }
                }
            }

            if (YII_ENV_PROD) {
                foreach ($tgMessage as $steamId => $message) {
                    $user = User::findBySteamId($steamId, false, 'top-rewards-queue-notify');
                    if (!empty($user) && !empty($user->telegram_chat_id)) {
                        Yii::$app->personalBotTelegram->sendMessage($user->telegram_chat_id, $message);
                    }
                }
            }
        } catch (\Throwable $e) {
            Yii::error('TopRewardsApplyJob error: ' . $e->getMessage(), __METHOD__);
        }
    }
}
