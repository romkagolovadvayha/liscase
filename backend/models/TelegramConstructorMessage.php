<?php

namespace backend\models;

use Yii;

/**
 * This is the model class for table "telegram_constructor_message".
 *
 * @property int $id
 * @property string $title
 * @property string|null $created_at
 * @property array $buttons
 *
 * @property TelegramConstructorButtons[] $telegramConstructorButtons
 * @property string $message
 * @property string|null $image_link
 */
class TelegramConstructorMessage extends \yii\db\ActiveRecord
{

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'telegram_constructor_message';
    }

    public function getMessage($language = 'ru-RU') {
        /** @var TelegramConstructorMessageLanguage $message */
        $message = TelegramConstructorMessageLanguage::find()
                                                     ->andWhere(['telegram_constructor_message_id' => $this->id])
                                                     ->andWhere(['language' => $language])
                                                     ->one();
        return !empty($message) ? $message->message : '';
    }

    public function getImageLink($language = 'ru-RU') {
        /** @var TelegramConstructorMessageLanguage $message */
        $message = TelegramConstructorMessageLanguage::find()
                                                     ->andWhere(['telegram_constructor_message_id' => $this->id])
                                                     ->andWhere(['language' => $language])
                                                     ->one();
        return !empty($message) ? $message->image_link : '';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['title'], 'required'],
            [['title'], 'string'],
            [['created_at'], 'safe'],
            [['image_link'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'Название',
            'image_link' => 'Ссылка на изображение',
            'created_at' => 'Дата создания',
        ];
    }

    /**
     * Gets query for [[TelegramConstructorButtons]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTelegramConstructorButtons()
    {
        return $this->hasMany(TelegramConstructorButtons::className(), ['telegram_constructor_message_id' => 'id']);
    }

    /**
     * @inheritDoc
     */
    public function beforeSave($insert): bool
    {
        if ($insert) {
            $this->created_at = date('Y-m-d H:i:s');
        }
        return parent::beforeSave($insert);
    }

    /**
     * @return array
     */
    public static function getList() {
        $result = [];
        /** @var TelegramConstructorMessage[] $messages */
        $messages = self::find()->all();
        foreach ($messages as $message) {
            $result[$message->id] = $message->title;
        }

        return $result;
    }

    /**
     * Получение публичной URL изображения
     * Если image_link начинается с @, то это ссылка (возможно с плейсхолдерами)
     * @param string $baseUrl
     * @param string $language
     * @param int|null $userId ID пользователя для подстановки в ссылку
     * @return string
     */
    public function getPubUrl($baseUrl = '', $language = 'ru-Ru', $userId = null): string
    {
        $imageLink = $this->getImageLink($language);
        
        if (empty($imageLink)) {
            return '';
        }
        
        // Если ссылка начинается с @, это внешняя ссылка (возможно с плейсхолдерами)
        if (strpos($imageLink, '@') === 0) {
            $url = substr($imageLink, 1); // Убираем @
            
            // Подставляем {user_id} если указан userId
            if ($userId !== null) {
                $url = str_replace('{user_id}', $userId, $url);
            }
            
            return $url;
        }
        
        // Иначе это путь к файлу на сервере
        if(!$baseUrl) {
            $baseUrl = Yii::$app->params['baseUrl'];
        }
        
        // Если imageLink уже является URL, возвращаем как есть
        if (preg_match('#^https?://#i', $imageLink)) {
            return $imageLink;
        }
        
        // Пытаемся извлечь относительный путь из полного пути к файлу
        $possiblePaths = [
            Yii::getAlias('@app/web/uploads/telegram'),
            Yii::getAlias('@frontend/web/uploads/telegram'),
            Yii::getAlias('@backend/web/uploads/telegram'),
        ];
        
        $relativePath = null;
        foreach ($possiblePaths as $basePath) {
            if (strpos($imageLink, $basePath) === 0) {
                // Нашли совпадение, извлекаем относительный путь
                $relativePath = substr($imageLink, strlen($basePath));
                // Убираем начальный слэш, если есть
                $relativePath = ltrim($relativePath, '/');
                break;
            }
        }
        
        // Если не удалось извлечь относительный путь, пытаемся извлечь только имя файла
        if ($relativePath === null) {
            // Проверяем, содержит ли путь /uploads/telegram/
            // Используем более точное регулярное выражение, чтобы избежать дублирования
            if (preg_match('#/uploads/telegram/([^/]+\.(jpg|jpeg|png|gif|webp))$#i', $imageLink, $matches)) {
                // Извлекаем только имя файла (последняя часть после /uploads/telegram/)
                $relativePath = $matches[1];
            } elseif (preg_match('#/uploads/telegram/(.+)$#', $imageLink, $matches)) {
                // Если есть путь после /uploads/telegram/, но он может содержать дублирование
                $pathAfterUploads = $matches[1];
                // Убираем все, что идет до последнего вхождения /uploads/telegram/
                $pathParts = explode('/uploads/telegram/', $pathAfterUploads);
                $relativePath = end($pathParts);
            } else {
                // Просто берем имя файла
                $relativePath = basename($imageLink);
            }
        }
        
        // Убираем все лишние слэши и пути, оставляем только имя файла или относительный путь
        // Если в relativePath есть полный путь, извлекаем только имя файла
        if (strpos($relativePath, '/uploads/telegram/') !== false) {
            $parts = explode('/uploads/telegram/', $relativePath);
            $relativePath = end($parts);
        }
        // Убираем все, что идет до последнего слэша, если это не просто имя файла
        if (strpos($relativePath, '/') !== false && strpos($relativePath, '/') !== strrpos($relativePath, '/')) {
            // Если есть несколько слэшей, берем только последнюю часть
            $relativePath = basename($relativePath);
        }
        
        // Формируем публичный URL
        return rtrim($baseUrl, '/') . '/uploads/telegram/' . $relativePath;
    }

    public function updateLanguage($language, $message = null, $imageLink = null, $updateImageLink = true)
    {
        /** @var TelegramConstructorMessageLanguage $message */
        $model = TelegramConstructorMessageLanguage::find()
                                                   ->andWhere(['telegram_constructor_message_id' => $this->id])
                                                   ->andWhere(['language' => $language])
                                                   ->one();
        if (empty($model)) {
            $model                                  = new TelegramConstructorMessageLanguage();
            $model->telegram_constructor_message_id = $this->id;
            $model->language                        = $language;
        }
        $model->message = $message;
        if ($updateImageLink) {
            $model->image_link = $imageLink;
        }
        $model->save(false);
    }

    public function getTelegramMessage($language, $photo = true) {
        $message = $this->getMessage($language);
        $message = str_replace(["<p>&nbsp;</p>", "<p>", "</p>", "<br>", "&nbsp;"], ["\n", "", "", "\n", ""], $this->makeStringUTF8($message));
        if ($this->getImageLink($language) && $photo) {
            $imageLink = $this->getPubUrl('', $language);
            $message = '<a href="' . $imageLink . '">&#8205;</a>' . $message;
        }

        return trim($message);
    }

    public function getTelegramButtons($language) {
        $buttons = [];
        foreach ($this->telegramConstructorButtons as $button) {
            $item = [
                'text' => $button->getText($language),
            ];

            if (!empty($button->callback_telegram_constructor_message_id)) {
                $data = [
                    'messageId' => $button->callback_telegram_constructor_message_id,
                    'current_language' => $language,
                ];
                $item['callback_data'] = json_encode($data);
            }

            if (!empty($button->url)) {
                $item['url'] = $button->url;
            }

            $buttons[] = $item;
        }

        return $buttons;
    }

    /**
     * Получение сообщения в формате для VK (без HTML тегов)
     * @param string $language
     * @return string
     */
    public function getVkMessage($language = 'ru-RU') {
        $message = $this->getMessage($language);
        $message = $this->makeStringUTF8($message);
        
        // Конвертируем HTML ссылки в формат VK: [url|text] или просто url
        $message = preg_replace_callback('/<a\s+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', function($matches) {
            $url = $matches[1];
            $text = strip_tags($matches[2]);
            // Если текст ссылки совпадает с URL, просто возвращаем URL
            if (trim($text) === $url || empty(trim($text))) {
                return $url;
            }
            // Иначе используем формат VK: [url|text]
            return $text . ' (' . $url . ')';
        }, $message);
        
        // Конвертируем HTML теги в текст
        // Заменяем <br> и <br/> на перенос строки
        $message = preg_replace('/<br\s*\/?>/i', "\n", $message);
        
        // Заменяем <p> на перенос строки в начале и конце
        $message = preg_replace('/<p[^>]*>/i', "\n", $message);
        $message = str_replace('</p>', "\n", $message);
        
        // Удаляем остальные HTML теги
        $message = strip_tags($message);
        
        // Заменяем HTML entities (включая &mdash;)
        $message = html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Заменяем множественные переносы строк на двойной перенос
        $message = preg_replace('/\n{3,}/', "\n\n", $message);
        
        // Удаляем пустые строки в начале и конце
        $message = trim($message);
        
        return $message;
    }

    /**
     * @param $data
     * @return array|false|string|string[]|null
     */
    private function makeStringUTF8($data)
    {
        if (is_string($data) === true)
        {
            // has html entities?
            if (strpos($data, '&') !== false)
            {
                // if so, revert back to normal
                $data = html_entity_decode($data, ENT_QUOTES, 'UTF-8');
            }
            // make sure it UTF-8
            if (function_exists('iconv') === true)
            {
                return @iconv('UTF-8', 'UTF-8//IGNORE', $data);
            }
            if (function_exists('mb_convert_encoding') === true)
            {
                return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            }
            return utf8_encode(utf8_decode($data));
        }
        return $data;
    }

}
