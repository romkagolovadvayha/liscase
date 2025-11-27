<?php

namespace common\components\vk;

use Yii;
use yii\helpers\ArrayHelper;

class VkApiHelper extends \yii\base\Component
{
    public string $accessToken;
    public string $userAccessToken; // Токен пользователя для загрузки фото
    public string $apiVersion = '5.131';

    /**
     * Установка access token
     * @param string $token
     * @return $this
     */
    public function setAccessToken($token)
    {
        $this->accessToken = $token;
        return $this;
    }

    /**
     * Установка токена пользователя для загрузки фото
     * @param string $token
     * @return $this
     */
    public function setUserAccessToken($token)
    {
        $this->userAccessToken = $token;
        return $this;
    }

    /**
     * Получение списка участников группы
     * @param int|string $groupId ID группы
     * @param int $offset Смещение
     * @param int $count Количество (максимум 1000)
     * @return array|false Массив с ключами 'items' (массив ID пользователей) и 'count' (общее количество)
     */
    public function getGroupMembers($groupId, $offset = 0, $count = 1000)
    {
        $params = [
            'group_id' => abs($groupId),
            'offset' => $offset,
            'count' => min($count, 1000), // VK API ограничивает максимум 1000
            'sort' => 'id_asc', // Сортировка для стабильной пагинации
            'v' => $this->apiVersion,
        ];

        $result = $this->_sendRequest('groups.getMembers', $params);
        
        if ($result === false || empty($result['response'])) {
            return false;
        }

        return $result['response'];
    }

    /**
     * Получение всех участников группы (с пагинацией)
     * @param int|string $groupId ID группы
     * @return array|false Массив ID пользователей
     */
    public function getAllGroupMembers($groupId)
    {
        $allMembers = [];
        $offset = 0;
        $count = 1000;
        $totalCount = null;
        $iteration = 0;
        $maxIterations = 1000; // Защита от бесконечного цикла

        do {
            $iteration++;
            if ($iteration > $maxIterations) {
                try {
                    Yii::$app->telegramChats->sendMessage("VK: Max iterations reached ({$maxIterations}). Stopping.");
                } catch (\Exception $e) {}
                break;
            }

            $result = $this->getGroupMembers($groupId, $offset, $count);
            
            if ($result === false || empty($result['items'])) {
                break;
            }

            // Сохраняем общее количество участников из первого запроса
            if ($totalCount === null && isset($result['count'])) {
                $totalCount = (int)$result['count'];
            }

            $itemsCount = count($result['items']);
            $allMembers = array_merge($allMembers, $result['items']);
            $offset += $itemsCount;
            
            // Проверяем, нужно ли продолжать
            $shouldContinue = false;
            if ($totalCount !== null) {
                $shouldContinue = ($offset < $totalCount);
            } else {
                $shouldContinue = ($itemsCount === $count);
            }
            
            // Задержка для соблюдения rate limits VK API
            if ($shouldContinue) {
                usleep(350000); // 0.35 секунды между запросами
            }
        } while ($shouldContinue);

        return $allMembers;
    }

    /**
     * Получение информации о пользователях с проверкой разрешений на отправку сообщений
     * @param array $userIds Массив ID пользователей
     * @return array|false Массив с данными пользователей, включая can_write_private_message
     */
    public function getUsersInfo($userIds)
    {
        if (empty($userIds)) {
            return [];
        }

        // VK API ограничивает максимум 100 пользователей за запрос users.get
        $chunks = array_chunk($userIds, 100);
        $allUsers = [];
        $chunkIndex = 0;

        foreach ($chunks as $chunk) {
            $chunkIndex++;
            $params = [
                'user_ids' => implode(',', $chunk),
                'fields' => 'can_write_private_message,screen_name',
                'v' => $this->apiVersion,
            ];

            $result = $this->_sendRequest('users.get', $params);
            
            if ($result === false || empty($result['response'])) {
                continue;
            }

            $allUsers = array_merge($allUsers, $result['response']);
            
            // Задержка между запросами
            if (count($chunks) > 1 && $chunkIndex < count($chunks)) {
                usleep(350000);
            }
        }

        return $allUsers;
    }

    /**
     * Обновление аудитории ВКонтакте - получение всех участников группы и сохранение тех, кто разрешил отправку сообщений
     * @param int|string $groupId ID группы
     * @return array Статистика: ['total' => int, 'with_permission' => int, 'saved' => int]
     */
    public function updateAudience($groupId)
    {
        $stats = [
            'total' => 0,
            'with_permission' => 0,
            'saved' => 0,
        ];

        // Получаем всех участников группы
        $members = $this->getAllGroupMembers($groupId);
        if (empty($members)) {
            try {
                Yii::$app->telegramChats->sendMessage("VK: No members found for group {$groupId}");
            } catch (\Exception $e) {}
            return $stats;
        }

        $stats['total'] = count($members);

        // Получаем информацию о пользователях с проверкой разрешений
        // Обрабатываем по частям, чтобы не превысить лимиты API
        $chunks = array_chunk($members, 1000);
        
        $chunkIndex = 0;
        foreach ($chunks as $chunk) {
            $chunkIndex++;
            $usersInfo = $this->getUsersInfo($chunk);
            
            if (empty($usersInfo)) {
                continue;
            }

            foreach ($usersInfo as $userData) {
                $vkUserId = $userData['id'] ?? null;
                if (empty($vkUserId)) {
                    continue;
                }

                $canSendMessage = !empty($userData['can_write_private_message']) && $userData['can_write_private_message'] == 1;
                
                if ($canSendMessage) {
                    $stats['with_permission']++;
                }
                
                // Сохраняем в базу данных (и с разрешением, и без - чтобы знать всех проверенных пользователей)
                try {
                    \common\models\vk\VkUser::createOrUpdate($vkUserId, $userData, $canSendMessage);
                    $stats['saved']++;
                } catch (\Exception $e) {
                    // Игнорируем ошибки сохранения отдельных пользователей
                }
            }

            // Задержка между обработкой частей
            if (count($chunks) > 1 && $chunkIndex < count($chunks)) {
                usleep(350000);
            }
        }

        try {
            Yii::$app->telegramChats->sendMessage("VK: Finished updating audience. Total: {$stats['total']}, Saved: {$stats['saved']}, With permission: {$stats['with_permission']}");
        } catch (\Exception $e) {}
        return $stats;
    }

    /**
     * Отправка личного сообщения пользователю ВКонтакте
     * @param int $userId ID пользователя
     * @param string $message Текст сообщения
     * @param string|null $photoUrl URL изображения (опционально)
     * @return array|false
     */
    public function sendMessage($userId, $message, $photoUrl = null)
    {
        $params = [
            'user_id' => $userId,
            'message' => $message,
            'random_id' => rand(0, 2147483647), // VK требует random_id для защиты от дубликатов
            'v' => $this->apiVersion,
        ];

        // Если есть фото, загружаем его
        if (!empty($photoUrl)) {
            $photoId = $this->uploadMessagePhoto($photoUrl);
            if ($photoId) {
                $params['attachment'] = $photoId;
            }
        }

        return $this->_sendRequest('messages.send', $params);
    }

    /**
     * Публикация поста в группу ВКонтакте
     * @param int|string $groupId ID группы (может быть отрицательным числом)
     * @param string $message Текст сообщения
     * @param string|array|null $photoUrl URL изображения или массив URL изображений (опционально)
     * @return array|false
     */
    public function postToGroup($groupId, $message, $photoUrl = null)
    {
        $params = [
            'owner_id' => $groupId,
            'message' => $message,
            'v' => $this->apiVersion,
        ];

        // Если есть фото, загружаем их
        if (!empty($photoUrl)) {
            $photoIds = [];
            
            // Поддерживаем как одно изображение, так и массив
            $photoUrls = is_array($photoUrl) ? $photoUrl : [$photoUrl];
            
            foreach ($photoUrls as $url) {
                if (!empty($url)) {
                    $photoId = $this->uploadPhoto($groupId, $url);
                    if ($photoId) {
                        $photoIds[] = $photoId;
                    } else {
                        // Логируем ошибку загрузки конкретного фото, но продолжаем
                        Yii::warning("VK: Failed to upload photo from URL: {$url}", __METHOD__);
                    }
                }
            }
            
            // Прикрепляем все загруженные фото (если хотя бы одно загрузилось)
            if (!empty($photoIds)) {
                $params['attachments'] = implode(',', $photoIds);
            } else {
                // Если ни одно фото не загрузилось, логируем предупреждение
                Yii::warning("VK: All photos failed to upload, posting without photos", __METHOD__);
            }
        }

        return $this->_sendRequest('wall.post', $params);
    }

    /**
     * Загрузка фото для личного сообщения
     * @param string $photoUrl URL изображения
     * @return string|false ID загруженного фото в формате photo{owner_id}_{photo_id}
     */
    private function uploadMessagePhoto($photoUrl)
    {
        try {
            // Получаем адрес сервера для загрузки фото в сообщения
            $uploadServer = $this->_sendRequest('photos.getMessagesUploadServer', [
                'v' => $this->apiVersion,
            ]);

            if (empty($uploadServer['response']['upload_url'])) {
                Yii::error("VK: Failed to get messages upload server", __METHOD__);
                return false;
            }

            $uploadUrl = $uploadServer['response']['upload_url'];

            // Скачиваем изображение
            $imageContent = file_get_contents($photoUrl);
            if ($imageContent === false) {
                Yii::error("VK: Failed to download image from {$photoUrl}", __METHOD__);
                return false;
            }

            // Сохраняем во временный файл
            $tempFile = tempnam(sys_get_temp_dir(), 'vk_upload_');
            file_put_contents($tempFile, $imageContent);

            // Загружаем на сервер VK
            $ch = curl_init($uploadUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'photo' => new \CURLFile($tempFile, 'image/jpeg', 'photo.jpg')
            ]);

            $uploadResult = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            unlink($tempFile);

            if ($httpCode !== 200 || empty($uploadResult)) {
                Yii::error("VK: Failed to upload photo, HTTP code: {$httpCode}", __METHOD__);
                return false;
            }

            $uploadData = json_decode($uploadResult, true);
            if (empty($uploadData)) {
                Yii::error("VK: Invalid upload response: {$uploadResult}", __METHOD__);
                return false;
            }

            // Сохраняем фото
            $saveResult = $this->_sendRequest('photos.saveMessagesPhoto', [
                'photo' => $uploadData['photo'],
                'server' => $uploadData['server'],
                'hash' => $uploadData['hash'],
                'v' => $this->apiVersion,
            ]);

            if (!empty($saveResult['response'][0]['id'])) {
                $photo = $saveResult['response'][0];
                return "photo{$photo['owner_id']}_{$photo['id']}";
            }

            return false;
        } catch (\Exception $e) {
            Yii::error("VK: Exception while uploading message photo: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Загрузка фото для поста
     * @param int|string $groupId ID группы
     * @param string $photoUrl URL изображения
     * @return string|false ID загруженного фото в формате photo{owner_id}_{photo_id}
     */
    private function uploadPhoto($groupId, $photoUrl)
    {
        try {
            // Используем токен пользователя для загрузки фото, если он доступен
            $useUserToken = !empty($this->userAccessToken);
            $originalToken = $this->accessToken;
            
            if ($useUserToken) {
                $this->accessToken = $this->userAccessToken;
            }
            
            // Получаем адрес сервера для загрузки
            $uploadServer = $this->_sendRequest('photos.getWallUploadServer', [
                'group_id' => abs($groupId),
                'v' => $this->apiVersion,
            ]);
            
            // Восстанавливаем оригинальный токен
            if ($useUserToken) {
                $this->accessToken = $originalToken;
            }

            if ($uploadServer === false) {
                Yii::error("VK: Failed to get upload server - request failed", __METHOD__);
                return false;
            }

            if (empty($uploadServer['response']['upload_url'])) {
                $errorInfo = isset($uploadServer['error']) ? json_encode($uploadServer['error']) : 'Unknown error';
                $errorCode = isset($uploadServer['error']['error_code']) ? $uploadServer['error']['error_code'] : null;
                
                // Если ошибка связана с токеном группы (код 27), это нормально - просто не загружаем фото
                if ($errorCode == 27) {
                    Yii::warning("VK: Cannot upload photo with group token (error 27). Post will be published without photos. Consider using vk_user_token setting.", __METHOD__);
                } else {
                    Yii::error("VK: Failed to get upload server. Response: " . json_encode($uploadServer) . ", Error: {$errorInfo}", __METHOD__);
                }
                return false;
            }

            $uploadUrl = $uploadServer['response']['upload_url'];

            // Скачиваем изображение
            $imageContent = file_get_contents($photoUrl);
            if ($imageContent === false) {
                Yii::error("VK: Failed to download image from {$photoUrl}", __METHOD__);
                return false;
            }

            // Сохраняем во временный файл
            $tempFile = tempnam(sys_get_temp_dir(), 'vk_upload_');
            file_put_contents($tempFile, $imageContent);

            // Загружаем на сервер VK
            $ch = curl_init($uploadUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'photo' => new \CURLFile($tempFile, 'image/jpeg', 'photo.jpg')
            ]);

            $uploadResult = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            unlink($tempFile);

            if ($httpCode !== 200 || empty($uploadResult)) {
                Yii::error("VK: Failed to upload photo, HTTP code: {$httpCode}", __METHOD__);
                return false;
            }

            $uploadData = json_decode($uploadResult, true);
            if (empty($uploadData)) {
                Yii::error("VK: Invalid upload response: {$uploadResult}", __METHOD__);
                return false;
            }

            // Используем токен пользователя для сохранения фото, если он доступен
            $useUserToken = !empty($this->userAccessToken);
            $originalToken = $this->accessToken;
            
            if ($useUserToken) {
                $this->accessToken = $this->userAccessToken;
            }
            
            // Сохраняем фото в альбом группы
            $saveResult = $this->_sendRequest('photos.saveWallPhoto', [
                'group_id' => abs($groupId),
                'photo' => $uploadData['photo'],
                'server' => $uploadData['server'],
                'hash' => $uploadData['hash'],
                'v' => $this->apiVersion,
            ]);
            
            // Восстанавливаем оригинальный токен
            if ($useUserToken) {
                $this->accessToken = $originalToken;
            }

            if (!empty($saveResult['response'][0]['id'])) {
                $photo = $saveResult['response'][0];
                return "photo{$photo['owner_id']}_{$photo['id']}";
            }

            return false;
        } catch (\Exception $e) {
            Yii::error("VK: Exception while uploading photo: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Отправка запроса к VK API
     * @param string $method Метод API
     * @param array $params Параметры запроса
     * @return array|false
     */
    private function _sendRequest($method, $params = [])
    {
        if (empty($this->accessToken)) {
            $this->accessToken = Yii::$app->settings->get('vk_token');
        }

        if (empty($this->accessToken)) {
            Yii::error("VK: Access token is not set (check vk_token setting)", __METHOD__);
            return false;
        }

        $params['access_token'] = $this->accessToken;
        $url = 'https://api.vk.com/method/' . $method;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($error)) {
            Yii::error("VK API error: {$error}", __METHOD__);
            return false;
        }

        $result = json_decode($response, true);

        if (!empty($result['error'])) {
            $errorMsg = $result['error']['error_msg'] ?? 'Unknown error';
            $errorCode = $result['error']['error_code'] ?? 'N/A';
            Yii::error("VK API error (HTTP {$httpCode}): [{$errorCode}] {$errorMsg}, Full response: " . json_encode($result), __METHOD__);
            return $result; // Возвращаем результат с ошибкой, чтобы можно было увидеть описание
        }

        return $result;
    }
}

