<?php

namespace common\components\vk;

use Yii;
use yii\helpers\ArrayHelper;

class VkApiHelper extends \yii\base\Component
{
    public string $accessToken;
    public string $userAccessToken; // Токен пользователя для загрузки фото
    public string $apiVersion = '5.199';

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
     * Получение информации о пользователях
     * @param array $userIds Массив ID пользователей
     * @return array|false Массив с данными пользователей
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
                'fields' => 'screen_name', // Получаем только screen_name, разрешение не проверяем
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
     * Получение списка всех бесед сообщества (с кем был диалог)
     * @param int|string $groupId ID группы
     * @return array Массив ID пользователей, с которыми были беседы
     */
    public function getConversationsUserIds($groupId)
    {
        $allUserIds = [];
        $offset = 0;
        $count = 200; // Максимум для messages.getConversations
        $iteration = 0;
        $maxIterations = 1000; // Защита от бесконечного цикла

        do {
            $iteration++;
            if ($iteration > $maxIterations) {
                try {
                    Yii::$app->telegramChats->sendMessage("VK: Max iterations reached for conversations ({$maxIterations}). Stopping.");
                } catch (\Exception $e) {}
                break;
            }

            $params = [
                'filter' => 'all', // Все беседы
                'count' => $count,
                'offset' => $offset,
                'v' => $this->apiVersion,
            ];

            $result = $this->_sendRequest('messages.getConversations', $params);
            
            // Проверяем на ошибку доступа (сообщения отключены)
            if ($result !== false && !empty($result['error'])) {
                $errorCode = $result['error']['error_code'] ?? 0;
                $errorMsg = $result['error']['error_msg'] ?? '';
                
                if ($errorCode == 15) { // Access denied: group messages are disabled
                    throw new \Exception("VK: Сообщения группы отключены. Включите сообщения в настройках группы ВКонтакте (Управление сообществом → Сообщения → Сообщения сообщества), чтобы получать список пользователей с диалогами. Ошибка: {$errorMsg}");
                }
                
                // Для других ошибок тоже выбрасываем исключение
                throw new \Exception("VK API error: [{$errorCode}] {$errorMsg}");
            }
            
            if ($result === false || empty($result['response']) || empty($result['response']['items'])) {
                break;
            }

            // Извлекаем ID пользователей из бесед
            foreach ($result['response']['items'] as $item) {
                if (isset($item['conversation']['peer']['id'])) {
                    $peerId = $item['conversation']['peer']['id'];
                    // Для личных сообщений peer.id будет положительным числом (ID пользователя)
                    // Для групповых бесед - отрицательным (ID группы/чата)
                    if ($peerId > 0) {
                        $allUserIds[] = $peerId;
                    }
                }
            }

            $totalCount = $result['response']['count'] ?? 0;
            $itemsCount = count($result['response']['items']);
            $offset += $itemsCount;
            
            // Проверяем, нужно ли продолжать
            $shouldContinue = ($offset < $totalCount && $itemsCount === $count);
            
            // Задержка для соблюдения rate limits VK API
            if ($shouldContinue) {
                usleep(350000); // 0.35 секунды между запросами
            }
        } while ($shouldContinue);

        // Убираем дубликаты
        return array_unique($allUserIds);
    }

    /**
     * Обновление аудитории ВКонтакте - получение участников группы, с которыми был диалог
     * Если есть диалог, значит есть разрешение на отправку сообщений
     * @param int|string $groupId ID группы
     * @return array Статистика: ['total' => int, 'saved' => int, 'with_conversation' => int, 'deleted' => int]
     */
    public function updateAudience($groupId)
    {
        $stats = [
            'total' => 0,
            'saved' => 0,
            'with_conversation' => 0,
            'deleted' => 0,
        ];

        // Получаем список пользователей, с которыми был диалог
        try {
            $conversationUserIds = $this->getConversationsUserIds($groupId);
        } catch (\Exception $e) {
            // Если сообщения отключены, отправляем сообщение и возвращаем пустую статистику
            try {
                Yii::$app->telegramChats->sendMessage("VK: Ошибка при получении бесед: " . $e->getMessage());
            } catch (\Exception $ex) {}
            Yii::error("VK: Error getting conversations: " . $e->getMessage(), __METHOD__);
            return $stats;
        }
        
        if (empty($conversationUserIds)) {
            try {
                Yii::$app->telegramChats->sendMessage("VK: No conversations found for group {$groupId}");
            } catch (\Exception $e) {}
            // Если нет бесед, удаляем всех пользователей из базы
            $deletedCount = \common\models\vk\VkUser::deleteAll();
            $stats['deleted'] = $deletedCount;
            return $stats;
        }

        $stats['with_conversation'] = count($conversationUserIds);
        $stats['total'] = count($conversationUserIds);

        // Удаляем пользователей, которых нет в новом списке бесед
        $deletedCount = \common\models\vk\VkUser::deleteAll(['NOT IN', 'vk_user_id', $conversationUserIds]);
        $stats['deleted'] = $deletedCount;

        // Получаем информацию о пользователях
        // Обрабатываем по частям, чтобы не превысить лимиты API
        $chunks = array_chunk($conversationUserIds, 100);
        
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

                // Если есть диалог, значит есть разрешение на отправку сообщений
                $canSendMessage = true;
                
                // Сохраняем в базу данных всех, с кем был диалог
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
            Yii::$app->telegramChats->sendMessage("VK: Finished updating audience. Total conversations: {$stats['with_conversation']}, Saved: {$stats['saved']}, Deleted: {$stats['deleted']}");
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
            } else {
                // Если загрузка фото не удалась, логируем предупреждение, но отправляем сообщение без фото
                Yii::warning("VK: Failed to upload photo for user {$userId}, sending message without photo. Photo URL: {$photoUrl}", __METHOD__);
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
            'friends_only' => 0,
            'from_group' => 1, // Публикация от имени группы
            'v' => $this->apiVersion,
        ];

        // Если есть фото, загружаем первое изображение через API
        if (!empty($photoUrl)) {
            // Поддерживаем как одно изображение, так и массив
            $photoUrls = is_array($photoUrl) ? $photoUrl : [$photoUrl];
            $photoUrls = array_filter($photoUrls); // Убираем пустые значения
            
            if (!empty($photoUrls)) {
                // Берем только первое изображение и загружаем через API
                $firstPhotoUrl = reset($photoUrls);
                $photoId = $this->uploadPhotoForGroup($groupId, $firstPhotoUrl);
                
                if ($photoId) {
                    $params['attachments'] = $photoId;
                } else {
                    // Если загрузка не удалась, логируем предупреждение
                    Yii::warning("VK: Failed to upload photo, posting without photo", __METHOD__);
                }
            }
        }

        $params['message'] = $message;

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

            // Скачиваем изображение с использованием curl (более надежно, чем file_get_contents)
            $ch = curl_init($photoUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            $imageContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($imageContent === false || $httpCode !== 200 || !empty($curlError)) {
                Yii::error("VK: Failed to download image from {$photoUrl}, HTTP code: {$httpCode}, Error: {$curlError}", __METHOD__);
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
     * Загрузка фото для поста на стене группы (работает с токеном пользователя)
     * @param int|string $groupId ID группы
     * @param string $photoUrl URL изображения
     * @return string|false ID загруженного фото в формате photo{owner_id}_{photo_id}
     */
    private function uploadPhotoForGroup($groupId, $photoUrl)
    {
        try {
            // Используем photos.getWallUploadServer для загрузки фото на стену группы
            $uploadServer = $this->_sendRequest('photos.getWallUploadServer', [
                'group_id' => abs($groupId),
                'v' => $this->apiVersion,
            ]);

            if ($uploadServer === false) {
                Yii::error("VK: Failed to get upload server - request failed", __METHOD__);
                return false;
            }

            if (empty($uploadServer['response']['upload_url'])) {
                $errorInfo = isset($uploadServer['error']) ? json_encode($uploadServer['error']) : 'Unknown error';
                $errorCode = isset($uploadServer['error']['error_code']) ? $uploadServer['error']['error_code'] : null;
                Yii::error("VK: Failed to get upload server. Response: " . json_encode($uploadServer) . ", Error: {$errorInfo}", __METHOD__);
                return false;
            }

            $uploadUrl = $uploadServer['response']['upload_url'];

            // Скачиваем изображение с увеличенным таймаутом
            $ch = curl_init($photoUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            $imageContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($imageContent === false || $httpCode !== 200 || !empty($curlError)) {
                Yii::error("VK: Failed to download image from {$photoUrl}, HTTP code: {$httpCode}, Error: {$curlError}", __METHOD__);
                return false;
            }

            // Сохраняем во временный файл
            $tempFile = tempnam(sys_get_temp_dir(), 'vk_upload_');
            file_put_contents($tempFile, $imageContent);

            // Загружаем на сервер VK с увеличенным таймаутом
            $ch = curl_init($uploadUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'photo' => new \CURLFile($tempFile, 'image/jpeg', 'photo.jpg')
            ]);

            $uploadResult = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            unlink($tempFile);

            if ($httpCode !== 200 || empty($uploadResult) || !empty($curlError)) {
                Yii::error("VK: Failed to upload photo, HTTP code: {$httpCode}, Error: {$curlError}, Response: {$uploadResult}", __METHOD__);
                return false;
            }

            $uploadData = json_decode($uploadResult, true);
            if (empty($uploadData)) {
                Yii::error("VK: Invalid upload response: {$uploadResult}", __METHOD__);
                return false;
            }

            // Сохраняем фото на стену группы
            $saveResult = $this->_sendRequest('photos.saveWallPhoto', [
                'group_id' => abs($groupId),
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

        try {
            // Преобразуем массив attachments в строку, если это массив
            if (isset($params['attachments']) && is_array($params['attachments'])) {
                $params['attachments'] = implode(',', $params['attachments']);
            }
            
            // VK API принимает параметры как form-data (application/x-www-form-urlencoded)
            // Используем обычный curl для отправки form-data
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

            if (empty($response)) {
                Yii::error("VK API error: Empty response", __METHOD__);
                return false;
            }

            $result = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Yii::error("VK API error: Invalid JSON response: " . $response, __METHOD__);
                return false;
            }

            if (!empty($result['error'])) {
                $errorMsg = $result['error']['error_msg'] ?? 'Unknown error';
                $errorCode = $result['error']['error_code'] ?? 'N/A';
                Yii::error("VK API error: [{$errorCode}] {$errorMsg}, Full response: " . json_encode($result), __METHOD__);
                return $result; // Возвращаем результат с ошибкой, чтобы можно было увидеть описание
            }

            return $result;
        } catch (\Exception $e) {
            Yii::error("VK API error: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }
}

