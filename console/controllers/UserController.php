<?php

namespace console\controllers;

use common\components\helpers\CacheArrayHelper;
use common\components\oauth\Steam;
use common\components\queue\process\UserSteamInfoUpdateJob;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;
use yii\console\Controller;
use yii\db\StaleObjectException;
use yii\helpers\HtmlPurifier;

class UserController extends Controller
{
    /**
     * user/sync
     *
     * @throws \Exception
     */
    public function actionSync()
    {
        /** @var User[] $users */
        $users = User::find()
            ->orderBy(['id' => SORT_DESC])
            ->limit(40)
            ->all();
        foreach ($users as $user) {
            \Yii::$app->queueProcess->push(new UserSteamInfoUpdateJob(['steamId' => $user->steam_id]));
        }
    }

    /**
     * user/update
     *
     * @throws \Exception
     */
    public function actionUpdate()
    {
        $cacheKey = "steamIds_for_update";
        $steamList = CacheArrayHelper::withLock($cacheKey, function() use ($cacheKey) {
            return CacheArrayHelper::popFromCacheArray($cacheKey, 63);
        });
        if (empty($steamList)) {
            echo 'Empty steam Ids list.' . PHP_EOL;
            return;
        }
        $infoUsers       = Steam::getInfoUsers($steamList);
        if (empty($infoUsers)) {
            Yii::$app->telegramChats->sendMessage('Ждем таймаут стима 60 сек.');
            // Возврат обратно в очередь
            CacheArrayHelper::withLock($cacheKey, function() use ($cacheKey, $steamList) {
                CacheArrayHelper::pushBackToCacheArray($cacheKey, $steamList);
            });
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
    }

    private function _loadImage($imageUrl, $id) {
        $uploadDir = \Yii::getAlias('@frontend/web');
        $fileUrl = "/uploads/avatar/steam/{$id}.png";
        $filePath = $uploadDir . $fileUrl;
        if (file_exists($filePath)) {
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
