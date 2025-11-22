<?php

namespace console\controllers;

use common\components\helpers\CacheArrayHelper;
use common\components\oauth\Steam;
use common\components\queue\process\UserSteamInfoUpdateJob;
use common\models\user\User;
use common\models\user\UserDrop;
use common\models\user\UserProfile;
use common\models\servers\Servers;
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
        
        $attempts = [0, 2, 5];
        foreach ($attempts as $attempt => $sleep) {
            if ($sleep > 0) {
                sleep($sleep);
            }

            $ch = curl_init($imageUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 60);

            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($imageData !== false && $httpCode === 200 && !empty($imageData)) {
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                file_put_contents($filePath, $imageData);
                return $fileUrl;
            }

            \Yii::warning(sprintf(
                'Attempt %d: failed to load avatar for user %s (HTTP %s, error: %s)',
                $attempt + 1,
                $id,
                $httpCode,
                $error
            ), __METHOD__);
        }

        if (file_exists($filePath)) {
            return $fileUrl;
        }

        \Yii::error("Failed to load avatar for user {$id}: all attempts exhausted", __METHOD__);
        return '/images/steam_avatar_default.jpg';
    }

    /**
     * user/skin-expiration-check
     * 
     * Находит всех игроков на сервере classicx2, которые выводили скины (drop_id 847, 846, 845)
     * и показывает сколько дней скинов у них осталось
     */
    public function actionSkinExpirationCheck()
    {
        ini_set('memory_limit', '1024M');
        
        $serverTag = 'classicx2';
        
        // Находим сервер
        $server = Servers::find()
            ->andWhere(['tag' => $serverTag])
            ->one();
            
        if (empty($server)) {
            echo "Сервер с тегом '{$serverTag}' не найден!" . PHP_EOL;
            return;
        }
        
        echo "Сервер найден: {$server->name} (ID: {$server->id})" . PHP_EOL;
        echo "===========================================" . PHP_EOL . PHP_EOL;
        
        // Маппинг drop_id -> количество дней
        $skinDaysMap = [
            847 => 90,  // Скины на 90 дней
            846 => 30,  // Скины на 30 дней
            845 => 14,  // Скины на 14 дней
        ];
        
        $skinDropIds = array_keys($skinDaysMap);
        
        // Сначала находим все выведенные скины (без фильтрации по серверу)
        $userDrops = UserDrop::find()
            ->select(['id', 'user_id', 'drop_id', 'sended_at'])
            ->andWhere(['IN', 'drop_id', $skinDropIds])
            ->andWhere(['status' => UserDrop::STATUS_SENDED])
            ->andWhere(['IS NOT', 'sended_at', null])
            ->asArray()
            ->all();
            
        if (empty($userDrops)) {
            echo "Не найдено выведенных скинов" . PHP_EOL;
            return;
        }
        
        echo "Найдено выведенных скинов: " . count($userDrops) . PHP_EOL . PHP_EOL;
        
        // Группируем по пользователям и проверяем срок действия
        $usersWithSkins = [];
        $now = time();
        
        foreach ($userDrops as $userDrop) {
            if (empty($userDrop['sended_at'])) {
                continue;
            }
            
            $days = $skinDaysMap[$userDrop['drop_id']] ?? 0;
            if ($days == 0) {
                continue;
            }
            
            $sendedTimestamp = strtotime($userDrop['sended_at']);
            $expirationTimestamp = $sendedTimestamp + ($days * 24 * 60 * 60);
            
            // Проверяем, не истек ли срок
            if ($expirationTimestamp < $now) {
                continue; // Срок истек, пропускаем
            }
            
            $remainingDays = ceil(($expirationTimestamp - $now) / (24 * 60 * 60));
            
            $userId = $userDrop['user_id'];
            if (!isset($usersWithSkins[$userId])) {
                $usersWithSkins[$userId] = [
                    'user_id' => $userId,
                    'skins' => [],
                    'totalDays' => 0,
                ];
            }
            
            $usersWithSkins[$userId]['skins'][] = [
                'drop_id' => $userDrop['drop_id'],
                'days' => $days,
                'sended_at' => $userDrop['sended_at'],
                'remaining_days' => $remainingDays,
            ];
            
            // Суммируем общее количество дней (складываем все активные скины)
            $usersWithSkins[$userId]['totalDays'] += $remainingDays;
        }
        
        if (empty($usersWithSkins)) {
            echo "Не найдено активных скинов (срок не истек)" . PHP_EOL;
            return;
        }
        
        echo "Найдено пользователей с активными скинами: " . count($usersWithSkins) . PHP_EOL;
        
        // Проверяем, что эти пользователи играли на нужном сервере
        $userIdsWithSkins = array_keys($usersWithSkins);
        $usersOnServer = User::find()
            ->select(['id', 'username', 'steam_id'])
            ->andWhere(['IN', 'id', $userIdsWithSkins])
            ->andWhere(['server_id' => $server->id])
            ->andWhere(['status' => User::STATUS_ACTIVE])
            ->indexBy('id')
            ->all();
        
        // Фильтруем только тех, кто играл на сервере
        $usersWithSkins = array_intersect_key($usersWithSkins, $usersOnServer);
        
        if (empty($usersWithSkins)) {
            echo "Не найдено игроков с активными скинами, которые играли на сервере '{$serverTag}'" . PHP_EOL;
            return;
        }
        
        echo "Из них играли на сервере '{$serverTag}': " . count($usersWithSkins) . PHP_EOL . PHP_EOL;
        
        // Сортируем по количеству оставшихся дней (по убыванию)
        uasort($usersWithSkins, function($a, $b) {
            return $b['totalDays'] <=> $a['totalDays'];
        });
        
        // Выводим результат
        echo "===========================================" . PHP_EOL;
        echo "ИГРОКИ НА СЕРВЕРЕ {$serverTag} С АКТИВНЫМИ СКИНАМИ" . PHP_EOL;
        echo "===========================================" . PHP_EOL . PHP_EOL;
        
        $index = 1;
        foreach ($usersWithSkins as $userId => $data) {
            if (!isset($usersOnServer[$userId])) {
                continue; // Пропускаем, если пользователь не найден
            }
            
            $user = $usersOnServer[$userId];
            $totalDays = $data['totalDays'];
            
            echo "{$index}. {$user->username} (SteamID: {$user->steam_id})" . PHP_EOL;
            echo "   Осталось дней скинов: {$totalDays}" . PHP_EOL;
            echo "   Скины:" . PHP_EOL;
            
            foreach ($data['skins'] as $skin) {
                $skinType = $skinDaysMap[$skin['drop_id']] . " дней";
                echo "     - Drop ID {$skin['drop_id']} ({$skinType}): осталось {$skin['remaining_days']} дней (выведен: {$skin['sended_at']})" . PHP_EOL;
            }
            
            echo PHP_EOL;
            $index++;
        }
        
        echo "===========================================" . PHP_EOL;
        echo "Всего игроков с активными скинами: " . count($usersWithSkins) . PHP_EOL;
    }
}
