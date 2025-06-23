<?php

namespace common\components\queue\process;

use common\components\oauth\Steam;
use common\models\user\User;
use WebSocket\Client;
use Yii;
use yii\base\BaseObject;
use yii\helpers\HtmlPurifier;
use yii\queue\JobInterface;

class UserSteamInfoUpdateJob extends BaseObject implements JobInterface
{

    public $steamId;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            $cacheKey = "UserSteamInfoUpdateJob_execute";
            $steamList = [];
            if (!empty(Yii::$app->cache->get($cacheKey))) {
                $steamList = Yii::$app->cache->get($cacheKey);
            }
            if (!in_array($this->steamId, $steamList)) {
                $user = User::findBySteamId($this->steamId);
                if (strtotime($user->updated_at) < time() - 5 * 60) {
                    $steamList[] = $this->steamId;
                }
            }
            if (count($steamList) > 5) {
                $infoUsers       = Steam::getInfoUsers($steamList);
                if (empty($infoUsers)) {
                    sleep(60);
                    Yii::$app->telegramChats->sendMessage('Ждем таймаут стима 60 сек.');
                    return;
                }
                Yii::$app->telegramChats->sendMessage('Успешное обновление ' . count($infoUsers) . " аккаунтов.");
                foreach ($infoUsers as $infoUser) {
                    /** @var User $user */
                    $user = User::find()
                                  ->andWhere(['steam_id' => $infoUser['steamid']])
                                  ->one();
                    if (empty($user)) {
                        Yii::$app->telegramChats->sendMessage('Пользователь для обновления не найден: ' . $infoUser['steamid']);
                        continue;
                    }
                    $user->updated_at = date('Y-m-d H:i:s');
                    $user->username = HtmlPurifier::process($infoUser['personaname']);
                    $user->save();
                    $avatar = $this->_loadImage($infoUser['avatarfull'], $infoUser['steamid']);
                    $user->userProfile->name = HtmlPurifier::process($infoUser['personaname']);
                    $user->userProfile->avatar = $avatar;
                    $user->userProfile->save();
                }
                $steamList = [];
            }
            Yii::$app->cache->set($cacheKey, $steamList, 7*24*60*60);
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('UserSteamInfoUpdateJob: ' . $ex->getMessage());
        }
    }

    private function _loadImage($imageUrl, $id) {
        $uploadDir = \Yii::getAlias('@frontend/web');
        $fileUrl = "/uploads/avatar/steam/{$id}.png";
        $filePath = $uploadDir . $fileUrl;
        if (file_exists($filePath)) {
            Yii::$app->telegramChats->sendMessage('Удален старый аватар и загружен новый: ' . $imageUrl);
            unlink($filePath);
        }
        if (!file_exists(dirname(dirname($filePath)))) {
            mkdir(dirname(dirname($filePath)));
            chmod(dirname(dirname($filePath)), 0777);
        }
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath));
            chmod(dirname($filePath), 0777);
        }
        file_put_contents($filePath, file_get_contents($imageUrl));
        return $fileUrl;
    }
}