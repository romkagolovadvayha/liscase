<?php

namespace common\components\log;

use common\components\telegram\TelegramChats;
use common\helpers\HDates;
use common\helpers\HStrings;
use common\models\user\User;
use sergeymakinen\yii\logmessage\Message;
use sergeymakinen\yii\telegramlog\Target;
use yii\helpers\ArrayHelper;
use Yii;

/**
 *
 * Class TelegramSenderErrors
 *
 * @package common\components\log
 */
class TelegramSenderErrors extends Target
{
    /**
     * @var integer Ограничение на то как часто можно отправлять письма
     */
    public $minIntervalSec = 60;

    public $maxMessageLen = 3500; // оберточные красивости (теги, юзеры, ip сервера и проч.) съедают лимит в 4096 символов, уменьшил его

    public $template = '{levelAndRequest}

<pre>{text}</pre>

{category}
{user}
{stackTrace}
{serverName}';

    /**
     * @inheritDoc
     * @throws \yii\base\InvalidValueException
     */
    public function export()
    {
        $groupedMessages = [];
        foreach ($this->messages as $message) {
            $key = "{$message[1]}:{$message[2]}";

            if (!isset($groupedMessages[$key])) {
                $groupedMessages[$key] = $message;
            }
            else {
                $groupedMessages[$key][0] .= "\r\n" . $message[0];
            }
        }

        $messages = [];
        foreach ($groupedMessages as $key => $message) {
            $tgKey = "telegram.sent.$key";

            if ($this->minIntervalSec) {
                if (!\Yii::$app->cache->get($tgKey)) {
                    \Yii::$app->cache->set($tgKey, true, $this->minIntervalSec);
                    $messages[] = $message;
                }
                else {
                    continue;
                }
            }
            else {
                $messages[] = $message;
            }
        }

        foreach ($messages as $message) {
            $this->sendToQueue($message);
        }

        $this->messages = [];
    }

    /**
     * Send message to queue.
     *
     * @param $message
     */
    protected function sendToQueue($message)
    {
        $message = $this->formatMessageRequest($message);
        if (empty($message)) {
            return;
        }
        try {
            Yii::$app->telegramChats->sendMessage($message);
        } catch (\Exception $e) {
            Yii::error('Ошибка отправки в очередь телеграмма. ' . $e->getMessage());
        }
    }

    /**
     * @param array $message raw message.
     *
     * @return string
     */
    protected function formatMessageRequest($message)
    {
        if (is_array($message[0])) {
            $message[0] = HStrings::short(json_encode($message[0]), $this->maxMessageLen);
        } else {
            $message[0] = HStrings::short($message[0], $this->maxMessageLen);
        }
        $message    = new Message($message, $this);
        if (in_array($message->getCategory(), ['application'])) {
            return '';
        }
        if (strpos($message->getText(), '2 - cancelled') !== false) {
            return '';
        }
        if (
            strpos($message->getText(), 'RustTm items(): prices item list is empty') !== false
            || strpos($message->getText(), 'RustTm prices invalid JSON') !== false
        ) {
            return '';
        }
        return preg_replace_callback(
            '/{([^}]+)}([\n]*|$)/', function (array $matches) use ($message) {
            if (isset($this->substitutions[$matches[1]])) {
                $value = $this->substitute($matches[1], $message);
                if ($value !== '') {
                    return $value . $matches[2];
                }
            }

            return '';
        }, $this->template
        );
    }

    /**
     * Returns a substituted value.
     *
     * @param string  $name
     * @param Message $message
     *
     * @return string
     */
    protected function substitute($name, Message $message)
    {
        $config = $this->substitutions[$name];
        $value  = (string)call_user_func($config['value'], $message);
        if ($value === '') {
            return '';
        }

        $wrapAsCode = $config['wrapAsCode'];
        is_callable($wrapAsCode) && $wrapAsCode = (bool)call_user_func($wrapAsCode, $message);

        if ($wrapAsCode) {
            $value = "<code>" . $value . "</code>";
        }
        if (isset($config['title'])) {
            $separator = $config['short'] ? ' ' : "\n";
            $value     = "<b>{$config['title']}</b>:{$separator}{$value}";
        }
        else if (isset($config['emojiTitle'])) {
            $value = "{$config['emojiTitle']} {$value}";
        }

        return $value;
    }

    /**
     * Returns an array with the default substitutions.
     *
     * @return array default substitutions.
     */
    protected function defaultSubstitutions()
    {
        $data = [
            'text'  => [
                'wrapAsCode' => true,
            ],
            'levelAndRequest' => [
                'title' => null,
                'short' => false,
                'wrapAsCode' => false,
                'value' => function (Message $message) {
                    if (isset($this->levelEmojis[$message->message[1]])) {
                        $value = $this->levelEmojis[$message->message[1]] . ' ';
                    } else {
                        $value = '<b>' . ucfirst($message->getLevel()) . '</b> @ ';
                    }
                    if ($message->getIsConsoleRequest()) {
                        $value .= '<code>' . $message->getCommandLine() . '</code>';
                    } else {
                        $value .= '<a href="' . $message->getUrl() . '">' . $message->getUrl() . '</a>';
                    }
                    return $value;
                },
            ],
            'serverName' => [
                'emojiTitle' => '🖥',
                'short' => true,
                'wrapAsCode' => false,
                'value' => function (Message $message) {
                    $serverName = '';
                    if (!empty($_SERVER['SERVER_NAME'])) {
                        $serverName = $_SERVER['SERVER_NAME'];
                    }
                    $serverAddr = '';
                    if (!empty($_SERVER['SERVER_ADDR'])) {
                        $serverAddr = $_SERVER['SERVER_ADDR'];
                    }
                    return $serverName . ' ' . $serverAddr . ' ' . HDates::long();
                },
            ],
            'category' => [
                'emojiTitle' => '📖',
                'short' => true,
                'wrapAsCode' => false,
                'value' => function (Message $message) {
                    return '<b>' . $message->getCategory() . '</b>';
                },
            ],
            'user' => [
                'emojiTitle' => '🙂',
                'short' => true,
                'wrapAsCode' => false,
                'value' => function (Message $message) {
                    $value = [];
                    $id = $message->getUserId();
                    if ((string) $id !== '') {
                        $user = User::findOne($id);
                        if (!empty($user)) {
                            $value[] = "<b>{$user->username}</b> (<code>{$user->steam_id}</code>)";
                        }
                    }
                    $ip = $message->getUserIp();
                    if ((string) $ip !== '') {
                        $value[] = $ip;
                    }
                    return implode(str_repeat(' ', 4), $value);
                },
            ],
        ];

        return ArrayHelper::merge(parent::defaultSubstitutions(), $data);
    }
}
