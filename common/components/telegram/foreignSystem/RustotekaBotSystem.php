<?php

namespace common\components\telegram\foreignSystem;

use backend\models\TelegramConstructorMessage;
use common\components\oauth\Steam;
use common\components\queue\rustoteka\CheckPlayerJob;
use common\components\telegram\TelegramApiHelper;
use common\models\bansystem\BanList;
use common\models\box\Box;
use common\models\box\Drop;
use common\models\profit\Profit;
use common\models\servers\Servers;
use common\models\telegram\TelegramMessage;
use common\models\telegram\TelegramUser;
use common\models\user\UserBox;
use common\models\user\UserConfirmCode;
use common\models\user\UserDrop;
use DemonDogSL\translateManager\models\Language;
use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use common\models\user\User;

class RustotekaBotSystem extends AbstractSystemBots
{

    /**
     * @return int
     */
    public function getSystemId()
    {
        return 4;
    }

    /**
     * @return string
     */
    public function getSystemName()
    {
        return Yii::$app->settings->get('site_domain');
    }

    /**
     * @param array $message
     *
     * @return null|string|array
     */
    public function executeInnerCommand($message)
    {
        $messageText = $this->_getMessageText($message);
        $chatId = ArrayHelper::getValue($message, 'chat.id');

        // Обработка команды /help
        if ($messageText === '/help' || $messageText === 'help') {
            return $this->getHelpMessage();
        }

        // Проверка кулдауна
        $cooldownCheck = $this->checkCooldown($chatId);
        if ($cooldownCheck !== null) {
            return $cooldownCheck;
        }

        // Проверка SteamID (17 цифр)
        // Убираем все пробелы и проверяем, что это 17 цифр
        $cleanText = preg_replace('/\s+/', '', $messageText);
        if (strlen($cleanText) === 17 && preg_match('/^\d{17}$/', $cleanText)) {
            return $this->processCheckRequest($chatId, $cleanText);
        }
        
        // Проверка ссылки на профиль Steam
        if (Steam::hasLinkProfile($messageText)) {
            $steamId = Steam::getSteamId($messageText);
            if (empty($steamId)) {
                return [
                    'message' => "⛔ Произошла ошибка, вы неверно указали ссылку на профиль или SteamID.",
                    'buttons' => $this->getMainMenuButtons()
                ];
            }
            return $this->processCheckRequest($chatId, $steamId);
        }

        TelegramMessage::createModel($chatId, $messageText);

        // Для нераспознанных сообщений показываем подсказку
        return [
            'message' => "❓ Не понимаю эту команду.\n\n" .
                        "📝 <b>Как проверить игрока:</b>\n" .
                        "• Отправьте SteamID (17 цифр)\n" .
                        "• Или ссылку на профиль Steam\n\n" .
                        "💡 Используйте /help для получения справки.",
            'buttons' => $this->getMainMenuButtons()
        ];
    }

    private function numDecline( $number, $titles, $show_number = true ) {
        if( is_string( $titles ) ){
            $titles = preg_split( '/, */', $titles );
        }

        // когда указано 2 элемента
        if( empty( $titles[2] ) ){
            $titles[2] = $titles[1];
        }

        $cases = [ 2, 0, 1, 1, 1, 2 ];

        $intnum = abs( (int) strip_tags( $number ) );

        $title_index = ( $intnum % 100 > 4 && $intnum % 100 < 20 )
            ? 2
            : $cases[ min( $intnum % 10, 5 ) ];

        return ( $show_number ? "$number " : '' ) . $titles[ $title_index ];
    }

    /**
     * Проверка кулдауна для запросов проверки игрока
     * @param int $chatId ID чата
     * @return array|null Возвращает массив с сообщением об ошибке, если КД активен, иначе null
     */
    private function checkCooldown($chatId)
    {
        $cacheKey = "request_kd_{$chatId}";
        $cooldownEndTime = Yii::$app->cache->get($cacheKey);
        
        if ($cooldownEndTime && $cooldownEndTime > time()) {
            $seconds = $cooldownEndTime - time();
            $secondsWord = $this->numDecline($seconds, 'секунда, секунды, секунд', false);
            return [
                'message' => "⛔ Вы делаете запросы слишком часто, попробуйте через <b>{$seconds}</b> {$secondsWord}.",
                'buttons' => $this->getMainMenuButtons()
            ];
        }
        
        // Устанавливаем КД на 10 секунд
        Yii::$app->cache->set($cacheKey, time() + 10, 10);
        return null;
    }

    private function country($code) {
        $list = [
            'ru' => ['icon' => '🇷🇺', 'name' => 'Россия'],
            'by' => ['icon' => '🇧🇾', 'name' => 'Беларусь'],
            'kz' => ['icon' => '🇰🇿', 'name' => 'Казахстан'],
            'ua' => ['icon' => '🇺🇦', 'name' => 'Украина'],
            'us' => ['icon' => '🇺🇸', 'name' => 'США'],
            'de' => ['icon' => '🇩🇪', 'name' => 'Германия'],
            'fr' => ['icon' => '🇫🇷', 'name' => 'Франция'],
            'gb' => ['icon' => '🇬🇧', 'name' => 'Великобритания'],
            'it' => ['icon' => '🇮🇹', 'name' => 'Италия'],
            'es' => ['icon' => '🇪🇸', 'name' => 'Испания'],
            'cn' => ['icon' => '🇨🇳', 'name' => 'Китай'],
            'jp' => ['icon' => '🇯🇵', 'name' => 'Япония'],
            'in' => ['icon' => '🇮🇳', 'name' => 'Индия'],
            'br' => ['icon' => '🇧🇷', 'name' => 'Бразилия'],
            'ca' => ['icon' => '🇨🇦', 'name' => 'Канада'],
            'au' => ['icon' => '🇦🇺', 'name' => 'Австралия'],
            'nl' => ['icon' => '🇳🇱', 'name' => 'Нидерланды'],
            'se' => ['icon' => '🇸🇪', 'name' => 'Швеция'],
            'ch' => ['icon' => '🇨🇭', 'name' => 'Швейцария'],
            'pl' => ['icon' => '🇵🇱', 'name' => 'Польша'],
            'kr' => ['icon' => '🇰🇷', 'name' => 'Южная Корея'],
            'sa' => ['icon' => '🇸🇦', 'name' => 'Саудовская Аравия'],
            'ae' => ['icon' => '🇦🇪', 'name' => 'ОАЭ'],
            'sg' => ['icon' => '🇸🇬', 'name' => 'Сингапур'],
            'mx' => ['icon' => '🇲🇽', 'name' => 'Мексика'],
            'ar' => ['icon' => '🇦🇷', 'name' => 'Аргентина'],
            'ng' => ['icon' => '🇳🇬', 'name' => 'Нигерия'],
            'za' => ['icon' => '🇿🇦', 'name' => 'Южноафриканская Республика'],
            'ke' => ['icon' => '🇰🇪', 'name' => 'Кения'],
            'gh' => ['icon' => '🇬🇭', 'name' => 'Гана'],
            'eg' => ['icon' => '🇪🇬', 'name' => 'Египет'],
            'pk' => ['icon' => '🇵🇰', 'name' => 'Пакистан'],
            'bd' => ['icon' => '🇧🇩', 'name' => 'Бангладеш'],
            'vn' => ['icon' => '🇻🇳', 'name' => 'Вьетнам'],
            'th' => ['icon' => '🇹🇭', 'name' => 'Таиланд'],
            'ph' => ['icon' => '🇵🇭', 'name' => 'Филиппины'],
            'ro' => ['icon' => '🇷🇴', 'name' => 'Румыния'],
            'cz' => ['icon' => '🇨🇿', 'name' => 'Чехия'],
            'hu' => ['icon' => '🇭🇺', 'name' => 'Венгрия'],
            'gr' => ['icon' => '🇬🇷', 'name' => 'Греция'],
            'no' => ['icon' => '🇳🇴', 'name' => 'Норвегия'],
            'fi' => ['icon' => '🇫🇮', 'name' => 'Финляндия'],
            'dk' => ['icon' => '🇩🇰', 'name' => 'Дания'],
            'at' => ['icon' => '🇦🇹', 'name' => 'Австрия'],
            'be' => ['icon' => '🇧🇪', 'name' => 'Бельгия'],
            'ie' => ['icon' => '🇮🇪', 'name' => 'Ирландия'],
            'lu' => ['icon' => '🇱🇺', 'name' => 'Люксембург'],
            'lt' => ['icon' => '🇱🇹', 'name' => 'Литва'],
            'lv' => ['icon' => '🇱🇻', 'name' => 'Латвия'],
            'ee' => ['icon' => '🇪🇪', 'name' => 'Эстония'],
            'hr' => ['icon' => '🇭🇷', 'name' => 'Хорватия'],
            'si' => ['icon' => '🇸🇮', 'name' => 'Словения'],
            'sk' => ['icon' => '🇸🇰', 'name' => 'Словакия'],
            'bg' => ['icon' => '🇧🇬', 'name' => 'Болгария'],
            'ba' => ['icon' => '🇧🇦', 'name' => 'Босния и Герцеговина'],
            'me' => ['icon' => '🇲🇪', 'name' => 'Черногория'],
            'mk' => ['icon' => '🇲🇰', 'name' => 'Северная Македония'],
            'rs' => ['icon' => '🇷🇸', 'name' => 'Сербия'],
            'al' => ['icon' => '🇦🇱', 'name' => 'Албания'],
            'am' => ['icon' => '🇦🇲', 'name' => 'Армения'],
            'ge' => ['icon' => '🇬🇪', 'name' => 'Грузия'],
            'cy' => ['icon' => '🇨🇾', 'name' => 'Кипр'],
            'mt' => ['icon' => '🇲🇹', 'name' => 'Мальта'],
            'is' => ['icon' => '🇮🇸', 'name' => 'Исландия'],
        ];
        if (empty($list[$code])) {
            return null;
        }
        return $list[$code];
    }

    /**
     * Обработка запроса на проверку игрока через очередь
     * @param int $chatId
     * @param string $steamId
     * @return array|null
     */
    private function processCheckRequest($chatId, $steamId)
    {
        try {
            $bot = $this->getTelegramBot();
            
            // Отправляем сообщение с анимированными эмодзи
            // В Telegram многие эмодзи автоматически анимируются (⏳, 🔄, ⚡, ✨ и др.)
            // Используем несколько анимированных эмодзи для красивого эффекта
            $waitingMessage = "⏳ <b>Проверяю игрока...</b>\n\nПожалуйста, подождите, это может занять несколько секунд.";
            
            // Попытка отправить анимированный стикер (если есть file_id)
            // Для получения file_id: отправьте стикер боту и получите file_id из ответа API
            $stickerFileId = Yii::$app->settings->get('rustoteka_bot_waiting_sticker_file_id');
            
            if (!empty($stickerFileId)) {
                // Отправляем анимированный стикер
                try {
                    $stickerResult = $bot->sendSticker($chatId, $stickerFileId);
                    if ($stickerResult && isset($stickerResult['result']['message_id'])) {
                        $waitingMessageId = $stickerResult['result']['message_id'];
                    } else {
                        // Если стикер не отправился, отправляем текст с анимированным эмодзи
                        $result = $bot->sendMessage($chatId, $waitingMessage);
                        if ($result && isset($result['result']['message_id'])) {
                            $waitingMessageId = $result['result']['message_id'];
                        }
                    }
                } catch (\Exception $e) {
                    // Если ошибка при отправке стикера, отправляем текст
                    Yii::warning("RustotekaBotSystem: Failed to send sticker: " . $e->getMessage(), __METHOD__);
                    $result = $bot->sendMessage($chatId, $waitingMessage);
                    if ($result && isset($result['result']['message_id'])) {
                        $waitingMessageId = $result['result']['message_id'];
                    }
                }
            } else {
                // Отправляем текст с анимированным эмодзи
                $result = $bot->sendMessage($chatId, $waitingMessage);
                if ($result && isset($result['result']['message_id'])) {
                    $waitingMessageId = $result['result']['message_id'];
                }
            }
            
            // Ставим задачу в очередь
            $job = new CheckPlayerJob([
                'chatId' => $chatId,
                'steamId' => $steamId,
                'waitingMessageId' => $waitingMessageId,
            ]);
            
            Yii::$app->queueRustotekaBot->push($job);
            
            // Возвращаем специальное значение, чтобы не показывать сообщение "команда не найдена"
            // Сообщение ожидания уже отправлено
            return false;
            
        } catch (\Exception $e) {
            Yii::error("RustotekaBotSystem: Error processing check request for steamId {$steamId}: " . $e->getMessage(), __METHOD__);
            return [
                'message' => "❌ Произошла ошибка при обработке запроса. Попробуйте позже.",
                'buttons' => $this->getMainMenuButtons()
            ];
        }
    }

    /**
     * Получение информации о игроке с кнопками
     * @param string $steamId
     * @return array
     */
    /**
     * Получение страны по IP адресу (выполняется в очереди)
     * @param string $ip
     * @return string|null Код страны (например, 'RU', 'US')
     */
    private function getCountryByIp($ip)
    {
        try {
            if (Yii::$app->has('geoip') && !empty($ip)) {
                $geoipStartTime = microtime(true);
                $location = Yii::$app->geoip->lookupLocation($ip);
                $geoipTime = round((microtime(true) - $geoipStartTime) * 1000, 2);
                Yii::info("RustotekaBotSystem::getCountryByIp: GeoIP lookup for IP {$ip} completed in {$geoipTime}ms", __METHOD__);
                
                if (!empty($location) && !empty($location->countryCode)) {
                    return strtoupper($location->countryCode);
                }
            }
        } catch (\Exception $e) {
            Yii::error("RustotekaBotSystem::getCountryByIp: Failed to get country by IP {$ip}: " . $e->getMessage(), __METHOD__);
        }
        return null;
    }

    /**
     * Форматирование страны с флагом
     * @param string $countryCode Код страны (например, 'RU', 'US')
     * @return string Отформатированная строка со страной и флагом
     */
    private function formatCountry($countryCode)
    {
        if (empty($countryCode)) {
            return '';
        }
        
        $flagItem = $this->country(strtolower($countryCode));
        if (!empty($flagItem)) {
            return "{$flagItem['icon']} {$flagItem['name']}";
        }
        
        // Если флага нет в списке, пытаемся найти через Language
        $language = Language::find()
            ->andWhere(['country' => mb_strtolower($countryCode)])
            ->one();
        if ($language) {
            return $language->name_ascii;
        }
        
        return $countryCode;
    }

    /**
     * Получение информации о игроке с кнопками
     * ВНИМАНИЕ: Этот метод должен вызываться только из очереди (CheckPlayerJob)
     * Все запросы к внешним сервисам и базам данных выполняются асинхронно в очереди
     * 
     * @param string $steamId
     * @return array
     */
    public function getCheck($steamId) {
        $startTime = microtime(true);
        Yii::info("RustotekaBotSystem::getCheck: Starting check for steamId {$steamId} (executed in queue)", __METHOD__);
        
        $message = "🔍 <b>Информация о игроке</b>\n\n";

        $countryCode = null;
        $steamCountryCode = null;

        // Запрос к Steam API (выполняется в очереди)
        $userInfo = null;
        $steamBans = null;
        $rustPlayTime = null;
        
        try {
            $steamStartTime = microtime(true);
            $userInfo = Steam::getInfoUser($steamId);
            $steamTime = round((microtime(true) - $steamStartTime) * 1000, 2);
            Yii::info("RustotekaBotSystem::getCheck: Steam API request completed in {$steamTime}ms", __METHOD__);
            
            if (!empty($userInfo) && !empty($userInfo[0])) {
                $info = $userInfo[0];
                
                if (!empty($info['personaname'])) {
                    $message .= "👤 <b>Ник:</b> {$info['personaname']}\n";
                }
                
                if (!empty($info['loccountrycode'])) {
                    $steamCountryCode = strtoupper($info['loccountrycode']);
                }
                
                // Дата регистрации Steam
                if (!empty($info['timecreated'])) {
                    $regDate = date('d.m.Y', (int)$info['timecreated']);
                    $message .= "📅 <b>Дата регистрации Steam:</b> {$regDate}\n";
                }
                
                // Статус профиля
                if (isset($info['communityvisibilitystate'])) {
                    $visibility = (int)$info['communityvisibilitystate'];
                    if ($visibility === 1) {
                        $message .= "🔒 <b>Профиль:</b> Приватный\n";
                    } elseif ($visibility === 3) {
                        $message .= "🌐 <b>Профиль:</b> Публичный\n";
                    }
                }
                
                // Статус онлайн/офлайн
                if (isset($info['personastate'])) {
                    $state = (int)$info['personastate'];
                    $stateText = ['Офлайн', 'Онлайн', 'Занят', 'Отошёл', 'Спит', 'Ищет игру', 'В игре'][$state] ?? 'Неизвестно';
                    $stateIcon = $state > 0 ? '🟢' : '⚫';
                    $message .= "{$stateIcon} <b>Статус:</b> {$stateText}\n";
                }
                
                // Последний раз в сети
                if (!empty($info['lastlogoff'])) {
                    $lastLogoff = (int)$info['lastlogoff'];
                    $lastLogoffDate = date('d.m.Y H:i', $lastLogoff);
                    $message .= "🕐 <b>Последний раз в сети:</b> {$lastLogoffDate}\n";
                }
                
                // Текущая игра
                if (!empty($info['gameextrainfo'])) {
                    $message .= "🎮 <b>В игре:</b> {$info['gameextrainfo']}\n";
                }
            }
        } catch (\Exception $e) {
            Yii::error("RustotekaBotSystem::getCheck: Steam API error for steamId {$steamId}: " . $e->getMessage(), __METHOD__);
            Yii::$app->telegramReports->sendMessage("RustotekaBotSystem:" . $e->getLine() . ":" . $e->getMessage());
        }

        $message .= "🆔 <b>SteamID:</b> <code>{$steamId}</code>\n";

        // Получение банов Steam (VAC, Game bans)
        try {
            $bansStartTime = microtime(true);
            $steamBans = Steam::getPlayerBans($steamId);
            $bansTime = round((microtime(true) - $bansStartTime) * 1000, 2);
            Yii::info("RustotekaBotSystem::getCheck: Steam GetPlayerBans request completed in {$bansTime}ms", __METHOD__);
            
            if (!empty($steamBans) && !empty($steamBans[0])) {
                $banInfo = $steamBans[0];
                $hasBans = false;
                
                // VAC бан
                if (!empty($banInfo['VACBanned']) && $banInfo['VACBanned'] === true) {
                    $vacBans = isset($banInfo['NumberOfVACBans']) ? (int)$banInfo['NumberOfVACBans'] : 0;
                    $daysSince = isset($banInfo['DaysSinceLastBan']) ? (int)$banInfo['DaysSinceLastBan'] : 0;
                    $message .= "⚠️ <b>VAC бан:</b> Да ({$vacBans} банов, {$daysSince} дн. назад)\n";
                    $hasBans = true;
                }
                
                // Game ban
                if (!empty($banInfo['NumberOfGameBans']) && (int)$banInfo['NumberOfGameBans'] > 0) {
                    $gameBans = (int)$banInfo['NumberOfGameBans'];
                    $message .= "⚠️ <b>Игровые баны:</b> {$gameBans}\n";
                    $hasBans = true;
                }
                
                // Бан в сообществе
                if (!empty($banInfo['CommunityBanned']) && $banInfo['CommunityBanned'] === true) {
                    $message .= "⚠️ <b>Бан в сообществе:</b> Да\n";
                    $hasBans = true;
                }
                
                // Бан в экономике
                if (!empty($banInfo['EconomyBan']) && $banInfo['EconomyBan'] !== 'none') {
                    $message .= "⚠️ <b>Бан в экономике:</b> " . ucfirst($banInfo['EconomyBan']) . "\n";
                    $hasBans = true;
                }
                
                if (!$hasBans) {
                    $message .= "✅ <b>Steam баны:</b> Нет\n";
                }
            }
        } catch (\Exception $e) {
            Yii::error("RustotekaBotSystem::getCheck: Steam GetPlayerBans error for steamId {$steamId}: " . $e->getMessage(), __METHOD__);
        }

        // Получение времени в игре Rust
        try {
            $playTimeStartTime = microtime(true);
            $rustPlayTime = Steam::getRustPlayTime($steamId);
            $playTimeTime = round((microtime(true) - $playTimeStartTime) * 1000, 2);
            Yii::info("RustotekaBotSystem::getCheck: Steam GetOwnedGames (Rust) request completed in {$playTimeTime}ms", __METHOD__);
            
            if (!empty($rustPlayTime)) {
                $hours = $rustPlayTime['hours'];
                $minutes = $rustPlayTime['minutes'];
                if ($hours > 0) {
                    $message .= "⏱️ <b>Время в Rust:</b> {$hours} ч. {$minutes} мин.\n";
                } elseif ($minutes > 0) {
                    $message .= "⏱️ <b>Время в Rust:</b> {$minutes} мин.\n";
                } else {
                    $message .= "⏱️ <b>Время в Rust:</b> Не играл\n";
                }
            }
        } catch (\Exception $e) {
            Yii::error("RustotekaBotSystem::getCheck: Steam GetRustPlayTime error for steamId {$steamId}: " . $e->getMessage(), __METHOD__);
        }

        // Запрос к RustCheck API (выполняется в очереди)
        $rustCheckData = null;
        try {
            if (Yii::$app->has('rustCheck')) {
                $rustCheckStartTime = microtime(true);
                $rustCheckData = Yii::$app->rustCheck->getInfo($steamId);
                $rustCheckTime = round((microtime(true) - $rustCheckStartTime) * 1000, 2);
                Yii::info("RustotekaBotSystem::getCheck: RustCheck API request completed in {$rustCheckTime}ms", __METHOD__);
            }
        } catch (\Exception $e) {
            Yii::error("RustotekaBotSystem::getCheck: RustCheck API error for steamId {$steamId}: " . $e->getMessage(), __METHOD__);
        }

        // Определяем страну по IP из RustCheck (приоритетнее, чем Steam)
        if (!empty($rustCheckData) && isset($rustCheckData['status']) && $rustCheckData['status'] === 'success') {
            if (!empty($rustCheckData['last_ip']) && is_array($rustCheckData['last_ip'])) {
                $ipList = array_unique($rustCheckData['last_ip']);
                // Берем первый IP для определения страны
                if (count($ipList) > 0) {
                    $firstIp = reset($ipList);
                    $countryCode = $this->getCountryByIp($firstIp);
                }
            }
        }

        // Если не удалось определить по IP, используем страну из Steam
        if (empty($countryCode) && !empty($steamCountryCode)) {
            $countryCode = $steamCountryCode;
        }

        // Отображаем страну с флагом
        if (!empty($countryCode)) {
            $countryDisplay = $this->formatCountry($countryCode);
            if (!empty($countryDisplay)) {
                $message .= "🌍 <b>Страна:</b> {$countryDisplay}\n";
            }
        }

        // Добавляем информацию из RustCheck
        if (!empty($rustCheckData) && isset($rustCheckData['status']) && $rustCheckData['status'] === 'success') {
            $message .= "\n";

            // Последний ник из RustCheck
            if (!empty($rustCheckData['last_nick'])) {
                $message .= "👤 <b>Последний ник:</b> {$rustCheckData['last_nick']}\n";
            }

            // Количество проверок
            if (isset($rustCheckData['rcc_checks'])) {
                $checksCount = (int)$rustCheckData['rcc_checks'];
                $message .= "🔍 <b>Проверок в системе:</b> {$checksCount}\n";
            }

            // История проверок
            if (!empty($rustCheckData['last_check']) && is_array($rustCheckData['last_check'])) {
                $checkCount = count($rustCheckData['last_check']);
                if ($checkCount > 0) {
                    // Показываем все проверки
                    foreach ($rustCheckData['last_check'] as $index => $check) {
                        $checkTime = isset($check['time']) ? date('d.m.Y H:i', (int)$check['time']) : 'Неизвестно';
                        $serverName = $check['serverName'] ?? 'Неизвестный сервер';
                        $message .= "   " . ($index + 1) . ". 📅 {$checkTime} 🖥️ {$serverName}\n";
                    }
                }
            }

            // Баны из RustCheck
            $rustCheckBans = !empty($rustCheckData['bans']) && is_array($rustCheckData['bans']) ? $rustCheckData['bans'] : [];
            $rustCheckBanCount = count($rustCheckBans);
            
            if ($rustCheckBanCount > 0) {
                $activeRustCheckBans = 0;
                
                foreach ($rustCheckBans as $ban) {
                    $unbanDate = isset($ban['unbanDate']) ? (int)$ban['unbanDate'] : 0;
                    if ($unbanDate === 0 || $unbanDate > time()) {
                        $activeRustCheckBans++;
                    }
                }

                $message .= "\n\n⚠️ <b>Баны: {$rustCheckBanCount}</b>";
                if ($activeRustCheckBans > 0) {
                    $message .= " (Активных: {$activeRustCheckBans})";
                }
                $message .= "\n\n";

                // Показываем все баны
                foreach ($rustCheckBans as $index => $ban) {
                    $banDate = isset($ban['banDate']) ? date('d.m.Y H:i', (int)$ban['banDate']) : 'Неизвестно';
                    $unbanDate = isset($ban['unbanDate']) ? (int)$ban['unbanDate'] : 0;
                    $unbanDateStr = ($unbanDate === 0) ? 'Никогда' : date('d.m.Y H:i', $unbanDate);
                    $reason = $ban['reason'] ?? 'Не указана';
                    $serverName = $ban['serverName'] ?? 'Неизвестный сервер';
                    $isActive = ($unbanDate === 0 || $unbanDate > time());
                    $statusIcon = $isActive ? "🔴" : "🟢";

                    $message .= "{$statusIcon} <b>Бан #" . ($index + 1) . "</b> 🖥️ {$serverName}\n";
                    $message .= "   📅 Дата бана: {$banDate}\n";
                    $message .= "   🔓 Дата разбана: {$unbanDateStr}\n";
                    $message .= "   📝 Причина: {$reason}\n";
                    
                    if ($index < count($rustCheckBans) - 1) {
                        $message .= "\n";
                    }
                }
            } else {
                // Если банов нет, показываем сообщение
                $message .= "\n\n✅ <b>Аккаунт чист!</b>\nНи одного бана игрока не найдено.";
            }

        } else {
            // Если данных из RustCheck нет, показываем сообщение
            $message .= "\n\n✅ <b>Аккаунт чист!</b>\nНи одного бана игрока не найдено.";
        }

        // Логируем общее время выполнения (все запросы выполнялись в очереди)
        $totalTime = round((microtime(true) - $startTime) * 1000, 2);
        Yii::info("RustotekaBotSystem::getCheck: Completed check for steamId {$steamId} in {$totalTime}ms (all requests executed in queue)", __METHOD__);

        return [
            'message' => $message,
            'buttons' => $this->getCheckButtons($steamId)
        ];
    }

    /**
     * Переопределяем executeCommand для добавления кнопок к /start
     * @param array $message
     * @return string|array
     */
    public function executeCommand($message)
    {
        try {
            $messageText = $this->_getMessageText($message);

            $answerMessage = null;
            switch (true) {
                case strpos($messageText, '/start') === 0 :
                    $chat = ArrayHelper::getValue($message, 'chat');

                    $name = '';
                    $lastName  = ArrayHelper::getValue($chat, 'last_name');
                    $firstName = ArrayHelper::getValue($chat, 'first_name');
                    if (!empty($firstName) && !empty($lastName)) {
                        $name = ', ' . trim($lastName . ' ' . $firstName);
                    } elseif (!empty($firstName)) {
                        $name = ', ' . $firstName;
                    } elseif (!empty($lastName)) {
                        $name = ', ' . $lastName;
                    }

                    $this->loginUser($message);
                    $startText = $this->_getStartMessageText($name);
                    
                    return [
                        'message' => $startText,
                        'buttons' => $this->getMainMenuButtons()
                    ];

                default :
                    $answerMessage = $this->executeInnerCommand($message);
                    break;
            }

        } catch (\Exception $e) {
            $error = "File: " . $e->getFile()
                . PHP_EOL . "Line: " . $e->getLine()
                . PHP_EOL . "Error:" . $e->getMessage();
            Yii::$app->telegramChats->sendMessage($error);
            $answerMessage = [
                'message' => '❌ Что-то пошло не так!😱 Обратитесь в тех.поддержку.',
                'buttons' => $this->getMainMenuButtons()
            ];
        }

        // Если false, значит сообщение уже отправлено (например, сообщение ожидания)
        if ($answerMessage === false) {
            return false;
        }
        
        if (empty($answerMessage)) {
            return [
                'message' => '❓ Введенная команда не найдена 😏',
                'buttons' => $this->getMainMenuButtons()
            ];
        }

        return $answerMessage;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected function _getStartMessageText($name)
    {
        return "👋 Приветствую{$name}!\n\n" .
               "🤖 <b>Бот для проверки игроков Rust</b>\n\n" .
               "📋 <b>Что умеет бот:</b>\n" .
               "• Проверка игроков по SteamID\n" .
               "• Проверка по ссылке на профиль Steam\n" .
               "• Просмотр истории банов\n" .
               "• Информация о стране игрока\n\n" .
               "💡 <b>Как использовать:</b>\n" .
               "Отправьте SteamID (17 цифр) или ссылку на профиль Steam для проверки игрока.";
    }

    /**
     * @return TelegramApiHelper
     */
    public function getTelegramBot()
    {
        return (clone Yii::$app->rustotekaBotTelegram)
            ->setToken($this->getTelegramToken());
    }

    /**
     * @return string
     */
    public function getTelegramToken()
    {
        return '7494504343:AAFL_vGF1V7o5a4SRWvniY-R7NZ6pUqYa8M';
    }

    /**
     * @param int    $chatId
     * @param string $buttonValue
     *
     * @return null|string|array
     */
    public function executeCallBack($chatId, $buttonValue)
    {
        // Парсим данные кнопки
        $data = json_decode($buttonValue, true);
        if (empty($data) || !is_array($data)) {
            return '⛔ Команда не найдена, попробуйте другую';
        }

        $action = $data['action'] ?? null;

        switch ($action) {
            case 'check_again':
                $steamId = $data['steam_id'] ?? null;
                if ($steamId) {
                    // Проверка кулдауна для повторной проверки
                    $cooldownCheck = $this->checkCooldown($chatId);
                    if ($cooldownCheck !== null) {
                        return $cooldownCheck;
                    }
                    
                    // Используем очередь для повторной проверки
                    $this->processCheckRequest($chatId, $steamId);
                    // Возвращаем null, так как сообщение ожидания уже отправлено
                    return null;
                }
                break;
                
            case 'main_menu':
                $chat = ['id' => $chatId];
                $name = '';
                return [
                    'message' => $this->_getStartMessageText($name),
                    'buttons' => $this->getMainMenuButtons()
                ];
                
            case 'help':
                return $this->getHelpMessage();
                
            case 'steam_profile':
                $steamId = $data['steam_id'] ?? null;
                if ($steamId) {
                    // Просто возвращаем текст, кнопка уже открывает ссылку
                    return null;
                }
                break;
        }

        return '⛔ Команда не найдена, попробуйте другую';
    }

    /**
     * Получение кнопок главного меню
     * @return array
     */
    private function getMainMenuButtons()
    {
        // TelegramApiHelper делает 'inline_keyboard' => [$inlineKeyboard]
        // Поэтому передаем массив массивов кнопок (каждая строка - массив кнопок)
        // После обёртки получится правильная структура inline_keyboard
        return [
            [
                [
                    'text' => '📖 Справка',
                    'callback_data' => json_encode(['action' => 'help'])
                ]
            ],
            [
                [
                    'text' => '💡 Как проверить игрока?',
                    'callback_data' => json_encode(['action' => 'help'])
                ]
            ]
        ];
    }

    /**
     * Получение кнопок для результата проверки
     * @param string $steamId
     * @return array
     */
    private function getCheckButtons($steamId)
    {
        // TelegramApiHelper делает 'inline_keyboard' => [$inlineKeyboard]
        // Поэтому передаем массив массивов кнопок (каждая строка - массив кнопок)
        // Первые две кнопки в одной строке, третья - в отдельной
        return [
            [
                [
                    'text' => '🔄 Проверить снова',
                    'callback_data' => json_encode(['action' => 'check_again', 'steam_id' => $steamId])
                ],
                [
                    'text' => '🔗 Профиль Steam',
                    'url' => "https://steamcommunity.com/profiles/{$steamId}"
                ]
            ],
            [
                [
                    'text' => '🏠 Главное меню',
                    'callback_data' => json_encode(['action' => 'main_menu'])
                ]
            ]
        ];
    }

    /**
     * Получение сообщения со справкой
     * @return array
     */
    private function getHelpMessage()
    {
        $message = "📖 <b>Справка по использованию бота</b>\n\n" .
                   "🔍 <b>Как проверить игрока:</b>\n" .
                   "1️⃣ Отправьте SteamID (17 цифр)\n" .
                   "   Пример: <code>76561198012345678</code>\n\n" .
                   "2️⃣ Или отправьте ссылку на профиль Steam\n" .
                   "   Пример: <code>https://steamcommunity.com/profiles/76561198012345678</code>\n" .
                   "   Или: <code>https://steamcommunity.com/id/username</code>\n\n" .
                   "📋 <b>Что показывает бот:</b>\n" .
                   "• 👤 Ник игрока\n" .
                   "• 🌍 Страна игрока\n" .
                   "• 🆔 SteamID с ссылкой на профиль\n" .
                   "• ⚠️ История банов (если есть)\n" .
                   "• 📅 Даты банов и разбанов\n" .
                   "• 📝 Причины банов\n\n" .
                   "💡 <b>Полезные команды:</b>\n" .
                   "/start - Главное меню\n" .
                   "/help - Эта справка\n\n" .
                   "⚡ <b>Ограничения:</b>\n" .
                   "Не более 1 запроса в 10 секунд";

        return [
            'message' => $message,
            'buttons' => [
                [
                    [
                        'text' => '🏠 Главное меню',
                        'callback_data' => json_encode(['action' => 'main_menu'])
                    ]
                ]
            ]
        ];
    }

    /**
     * @param $message
     *
     * @throws \Exception
     */
    public function loginUser($message)
    {
        $chatId = ArrayHelper::getValue($message, 'chat.id');
        $lastName  = ArrayHelper::getValue($message, 'chat.last_name') ?? '';
        $firstName = ArrayHelper::getValue($message, 'chat.first_name') ?? '';
        $username = ArrayHelper::getValue($message, 'chat.username') ?? '';
        $name = '';
        if (!empty($firstName) || !empty($lastName)) {
            $name = trim($lastName . ' ' . $firstName);
        }

        TelegramUser::createModel($name, $chatId, $username, TelegramUser::TYPE_RUSTOTEKA);
    }

    /**
     * @param int $messageId
     * @param string    $language
     *
     * @return array
     */
    public function getMessage($messageId, $language)
    {
        if (empty($messageId)) {
            return [];
        }
        if (empty($language)) {
            return [];
        }
        $cacheKey = "actionGetMessage_{$messageId}_{$language}";
        $cacheData = Yii::$app->cache->get($cacheKey);
        if (!empty($cacheData)) {
            return $cacheData;
        }
        $model = TelegramConstructorMessage::findOne($messageId);
        $message = $model->getTelegramMessage($language);
        $buttons = $model->getTelegramButtons($language);

        $result = [
            'message' => $message,
            'buttons'    => $buttons,
        ];

        Yii::$app->cache->set($cacheKey, $result, 60);
        return $result;
    }

    /**
     * @param $method
     *
     * @return string
     */
    protected function _getUrl($method)
    {
        return 'https://' . Yii::$app->settings->get('site_domain') . '/api/telegram-personal-bot/' . $method;
    }
}
