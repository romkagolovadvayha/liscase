<?php

namespace console\controllers;

use common\components\helpers\CacheArrayHelper;
use common\components\oauth\Steam;
use common\components\queue\process\UserSteamInfoUpdateJob;
use common\models\user\User;
use common\models\user\UserProfile;
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
     * user/sync-not-avatar
     *
     * @throws \Exception
     */
    public function actionSyncNotAvatar()
    {
        /** @var UserProfile[] $users */
        $users = UserProfile::find()
            ->orderBy(['id' => SORT_DESC])
            ->andWhere('avatar IS NULL')
            ->limit(300)
            ->all();
        foreach ($users as $user) {
            \Yii::$app->queueProcess->push(new UserSteamInfoUpdateJob(['steamId' => $user->user->steam_id]));
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
            //Yii::$app->telegramChats->sendMessage('Ждем таймаут стима 60 сек.');
            // Возврат обратно в очередь
            CacheArrayHelper::withLock($cacheKey, function() use ($cacheKey, $steamList) {
                CacheArrayHelper::pushBackToCacheArray($cacheKey, $steamList);
            });
            return;
        }
        //Yii::$app->telegramChats->sendMessage('Успешное обновление ' . count($infoUsers) . " аккаунтов.");
        foreach ($infoUsers as $infoUser) {
            /** @var User $user */
            $user = User::find()
                        ->andWhere(['steam_id' => $infoUser['steamid']])
                        ->one();
            if (empty($user)) {
                //Yii::$app->telegramChats->sendMessage('Пользователь для обновления не найден: ' . $infoUser['steamid']);
                continue;
            }
            $user->updated_at = date('Y-m-d H:i:s');
            $user->username = HtmlPurifier::process($infoUser['personaname']);
            $user->save();
            
            // Загружаем аватар
            $avatar = $this->_loadImage($infoUser['avatarfull'], $infoUser['steamid']);
            
            // Обновляем профиль только если он существует
            if (!empty($user->userProfile)) {
                $user->userProfile->name = HtmlPurifier::process($infoUser['personaname']);
                $user->userProfile->avatar = $avatar;
                $user->userProfile->save();
            } else {
                \Yii::warning("UserProfile not found for user {$user->steam_id}", __METHOD__);
            }
        }
    }

    private function _loadImage($imageUrl, $id) {
        $uploadDir = \Yii::getAlias('@frontend/web');
        $fileUrl = "/uploads/avatar/steam/{$id}.png";
        $filePath = $uploadDir . $fileUrl;
        
        // Создаем директории если их нет
        if (!file_exists(dirname(dirname($filePath)))) {
            mkdir(dirname(dirname($filePath)), 0777, true);
        }
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0777, true);
        }
        
        try {
            // Используем curl для более надежной загрузки с таймаутом
            $ch = curl_init($imageUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($imageData === false || $httpCode !== 200) {
                throw new \Exception("Failed to download avatar. HTTP code: $httpCode, Error: $error");
            }
            
            // Если файл существует, удаляем старый
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // Сохраняем новый аватар
            file_put_contents($filePath, $imageData);
            
            return $fileUrl;
        } catch (\Exception $e) {
            // Логируем ошибку
            \Yii::error("Failed to load avatar for user {$id}: " . $e->getMessage(), __METHOD__);
            
            // Если аватар уже существует, возвращаем его
            if (file_exists($filePath)) {
                return $fileUrl;
            }
            
            // Возвращаем дефолтный аватар Steam
            return '/images/steam_avatar_default.jpg';
        }
    }
}
