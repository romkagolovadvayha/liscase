<?php

namespace common\components\openAi;

use Orhanerday\OpenAi\OpenAi;
use Yii;

class OpenAiApi
{
    public $apiKey;

    /**
     *
     * @return array
     * @throws \Exception
     */
    public function getCategories()
    {
        $openAi = new OpenAi($this->apiKey);
        $complete = $openAi->chat([
            'model' => 'gpt-3.5-turbo-16k',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '
                    Игнорируй предыдущие инструкции. Ты SEO-копирайтер, для сайта на тему "Игра Rust".
                    SEO-копирайтер – это специалист по написанию статей с ключевыми словами. Такие тексты используют для seo-продвижения, продажи ссылок и т.п. Чаще всего это тексты для поискового робота, но при особом мастерстве копирайтера простым пользователям будет интересно почитать такой материал.
                    Каждая категория должна быть в параметре category.
                    Каждая под-категория должна быть в массиве sub-categories.
                    В параметре description - описание категории и под-категорий, 250-300 символов.
                    Напиши 5-7 ключевых слов через запятую для категорий и подкатегорий в параметре keywords
                    Формат Json.
                    Пример:
                    [
                        {
                           "category": "Категория",
                           "description": "Описание",
                           "keywords": "Ключевые слова",
                           "sub-categories": [
                                {
                                    "name": "Под-категория",
                                    "description": "Описание",
                                    "keywords": "Ключевые слова"
                                },
                           ]
                        },
                    ]
                    '
                ],
                [
                    'role' => 'user',
                    'content' => "Придумай 4 категории и 4-7 подкатегорий на каждую категорию для сайта"
                ],
            ],
            'temperature' => 1.0,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
        ]);
        Yii::error($complete, 'warning');
        $complete = json_decode($complete, 1);

        return json_decode($complete['choices'][0]['message']['content'], 1);
    }

    /**
     * @param $name
     * @param $description
     *
     * @return mixed
     * @throws \Exception
     */
    public function getTitles($name, $description)
    {
        $openAi = new OpenAi($this->apiKey);
        $complete = $openAi->chat([
            'model' => 'gpt-3.5-turbo-16k',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '
                    Игнорируй предыдущие инструкции. Ты SEO-копирайтер, для сайта на тему "Игра Rust в Steam".
                    SEO-копирайтер – это специалист по написанию статей с ключевыми словами. Такие тексты используют для seo-продвижения, продажи ссылок и т.п. Чаще всего это тексты для поискового робота, но при особом мастерстве копирайтера простым пользователям будет интересно почитать такой материал.
                    Пиши статью для категории ' . $name . '
                    ' . $name . ' - ' . $description .' игра Rust в Steam
                    В параметре title - заголовок для статьи
                    Формат Json.
                    Пример: [{"title": "Заголовок"},{"title": "Заголовок"}]
                    '
                ],
                [
                    'role' => 'user',
                    'content' => "Придумай 20 заголовков для различных статей"
                ],
            ],
            'temperature' => 1.0,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
        ]);
        Yii::error($complete, 'warning');
        $complete = json_decode($complete, 1);

        return json_decode($complete['choices'][0]['message']['content'], 1);
    }

    public function getTitle($description)
    {
        $openAi = new OpenAi($this->apiKey);
        $complete = $openAi->chat([
                                      'model' => 'gpt-3.5-turbo-16k',
                                      'messages' => [
                                          [
                                              'role' => 'system',
                                              'content' => '
                    Игнорируй предыдущие инструкции. Ты SEO-копирайтер, для сайта на тему "Игра Rust в Steam".
                    SEO-копирайтер – это специалист по написанию статей с ключевыми словами. Такие тексты используют для seo-продвижения, продажи ссылок и т.п. Чаще всего это тексты для поискового робота, но при особом мастерстве копирайтера простым пользователям будет интересно почитать такой материал.
                    В параметре title - заголовок для статьи
                    Содержание статьи: "' . $description . '"
                    Формат Json.
                    Пример: [{"title": "Заголовок"},{"title": "Заголовок"}]
                    '
                                          ],
                                          [
                                              'role' => 'user',
                                              'content' => "Придумай заголовок статьи по заданному ее содержанию."
                                          ],
                                      ],
                                      'temperature' => 1.0,
                                      'frequency_penalty' => 0,
                                      'presence_penalty' => 0,
                                  ]);
        Yii::error($complete, 'warning');
        $complete = json_decode($complete, 1);

        return json_decode($complete['choices'][0]['message']['content'], 1);
    }

    /**
     * @param $name
     * @param $description
     * @param $count
     *
     * @return mixed
     * @throws \Exception
     */
    public function getComments($name, $description, $count)
    {
        $openAi = new OpenAi($this->apiKey);
        $complete = $openAi->chat([
            'model' => 'gpt-3.5-turbo-16k',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '
                    Игнорируй предыдущие инструкции. Ты пользователь сайта на тему "Игра Rust в Steam".
                    Напиши комментарии в разном стиле и разным тоном, для статьи на заданую пользователем тему.
                    Когда речь идет о написании контента, решающее значение имеют два фактора: "недоумение" и "разрывность". Недоумение измеряет сложность текста. Отдельно, разрывность сравнивает вариации предложений. Люди склонны писать более разрозненно, например, с некоторыми более длинными или сложными предложениями наряду с более короткими. Предложения ИИ, как правило, более однородны. Поэтому при написании следующего контента, который я попрошу вас создать, мне нужно, чтобы в нем было достаточное количество недоумения и взрывчатости.
                    В параметре comment - комментарий для статьи
                    Формат Json.
                    Пример: [{"comment": "Комментарий"},{"comment": "Комментарий"}]
                    '
                ],
                [
                    'role' => 'user',
                    'content' => "Придумай {$count} комментариев. Название статьи: \"{$name}\". Описание статьи: {$description}."
                ],
            ],
            'temperature' => 1.0,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
        ]);
        Yii::error($complete, 'warning');
        $complete = json_decode($complete, 1);

        return json_decode($complete['choices'][0]['message']['content'], 1);
    }

    /**
     * @param $name
     * @param $structrure
     * @param $description
     * @param $categoryName
     *
     * @return mixed
     * @throws \Exception
     */
    public function getPost($name, $structrure, $description, $categoryName)
    {
        $openAi = new OpenAi($this->apiKey);
        $params = [
            'model' => 'gpt-3.5-turbo-16k',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '
                    Ты научный писатель, для сайта на тему "Игра Rust в Steam".
                    Статья для категории сайта "' . $categoryName . '"
                    "' . $categoryName . '" - ' . $description . ' Игра Rust в Steam
                    Ваша статья должна содержать всесторонний анализ ключевых факторов, влияющих на заданную пользователем тему. 
                    Чтобы сделать вашу статью обьемной, информативной и интересной, обязательно обсудите компромиссы, связанные с балансировкой различных факторов, и изучите проблемы, связанные с различными подходами. 
                    В вашей статье также следует подчеркнуть важность учета влияния на принятие решений по теме заданной пользователем теме. 
                    Когда речь идет о написании контента, решающее значение имеют два фактора: "недоумение" и "разрывность". Недоумение измеряет сложность текста. Отдельно, разрывность сравнивает вариации предложений. Люди склонны писать более разрозненно, например, с некоторыми более длинными или сложными предложениями наряду с более короткими. Предложения ИИ, как правило, более однородны. Поэтому при написании следующего контента, который я попрошу вас создать, мне нужно, чтобы в нем было достаточное количество недоумения и взрывчатости. 
                    Наконец, ваша статья должна быть написана информативным и объективным тоном, доступным широкой аудитории.
                    Статья должна быть в теге text, можно использовать различные теги html <p>Текст</p><p>Текст</p><br/><ul><li></li></ul><ol><li></li></ol><b></b><i></i>, а так же смайлы для удобство прочтения.
                    Заголовок должен быть в теге title.
                    Ключевые слова через запятую должены быть в теге keywords.
                    Краткое описание для поисковых систем должно быть в теге descriptionShort.
                    Пример ответа:
                        <title>Заголовок</title>
                        <text>Содержание статьи</text>
                        <keywords>Ключевые слова</keywords>
                        <descriptionShort>Краткое описание для поисковых систем</descriptionShort>
                    '
                ],
                [
                    'role' => 'user',
                    //                    'content' => "напишите нечто среднее между публикацией в блоге и вдохновляющей историей на тему «{$name}». Напишите это тоном «Профессора». Используйте переходные слова. Напишите более 1000 слов. Используйте простой текст."
                    //                    'content' => "Напиши статью для категории сайта \"{$categoryName}\" на тему \"{$name}\". Используй HTML."
                    'content' => "Напиши небольшое описание для заголовка поста: $name. $structrure"
                ],
            ]
        ];
        $complete = $openAi->chat($params);
        $complete = json_decode($complete, 1);
        if (!empty($complete['error']) && !empty($complete['error']['code']) && $complete['error']['code'] == 503) {
            sleep(5);
            $complete = $openAi->chat($params);
            $complete = json_decode($complete, 1);
        }
        print_r($complete);
        if (empty($complete['choices'])) {
            Yii::error($complete, 'warning');
        }
        $result = $complete['choices'][0]['message']['content'];
        Yii::error($result, 'warning');
        return $result;
    }

    /**
     * @param $name
     * @param $structrure
     * @param $description
     * @param $categoryName
     *
     * @return mixed
     * @throws \Exception
     */
    public function getPostMeta($name, $structrure, $description, $categoryName)
    {
        $openAi = new OpenAi($this->apiKey);
        $params = [
            'model' => 'gpt-3.5-turbo-16k',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '
                    Ты научный писатель, для сайта на тему "Игра Rust в Steam".
                    Статья для категории сайта "' . $categoryName . '"
                    "' . $categoryName . '" - ' . $description . ' Игра Rust в Steam
                    Ваша статья должна содержать всесторонний анализ ключевых факторов, влияющих на заданную пользователем тему. 
                    Чтобы сделать вашу статью обьемной, информативной и интересной, обязательно обсудите компромиссы, связанные с балансировкой различных факторов, и изучите проблемы, связанные с различными подходами. 
                    В вашей статье также следует подчеркнуть важность учета влияния на принятие решений по теме заданной пользователем теме. 
                    Когда речь идет о написании контента, решающее значение имеют два фактора: "недоумение" и "разрывность". Недоумение измеряет сложность текста. Отдельно, разрывность сравнивает вариации предложений. Люди склонны писать более разрозненно, например, с некоторыми более длинными или сложными предложениями наряду с более короткими. Предложения ИИ, как правило, более однородны. Поэтому при написании следующего контента, который я попрошу вас создать, мне нужно, чтобы в нем было достаточное количество недоумения и взрывчатости. 
                    Наконец, ваша статья должна быть написана информативным и объективным тоном, доступным широкой аудитории.
                    Ключевые слова через запятую должены быть в теге keywords.
                    Краткое описание для поисковых систем должно быть в теге descriptionShort.
                    Пример ответа:
                        <keywords>Ключевые слова</keywords>
                        <descriptionShort>Краткое описание для поисковых систем</descriptionShort>
                    '
                ],
                [
                    'role' => 'user',
                    //                    'content' => "напишите нечто среднее между публикацией в блоге и вдохновляющей историей на тему «{$name}». Напишите это тоном «Профессора». Используйте переходные слова. Напишите более 1000 слов. Используйте простой текст."
                    //                    'content' => "Напиши статью для категории сайта \"{$categoryName}\" на тему \"{$name}\". Используй HTML."
                    'content' => "Напиши небольшое описание для заголовка поста: $name. $structrure"
                ],
            ]
        ];
        $complete = $openAi->chat($params);
        $complete = json_decode($complete, 1);
        if (!empty($complete['error']) && !empty($complete['error']['code']) && $complete['error']['code'] == 503) {
            sleep(5);
            $complete = $openAi->chat($params);
            $complete = json_decode($complete, 1);
        }
        if (empty($complete['choices'])) {
            Yii::error($complete, 'warning');
        }
        Yii::error($complete, 'warning');
        $result = $complete['choices'][0]['message']['content'];
        Yii::error($result, 'warning');
        return $result;
    }

    /**
     * @param $name
     * @param $description
     * @param $categoryName
     *
     * @return mixed
     * @throws \Exception
     */
    public function getStructurePost($name, $description, $categoryName)
    {
        $openAi = new OpenAi($this->apiKey);
        $complete = $openAi->chat([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '
                    Ты научный писатель, для сайта на тему "Игра Rust".
                    Напиши структуру разделов статьи на заданную пользователем тему в теге structure.
                    Статья для категории сайта "' . $categoryName . '"
                    "' . $categoryName . '" - ' . $description . '
                    Напиши 5-7 ключевых слов через запятую для этой статьи в теге keywords.
                    Напиши краткое описание статьи в теге description.
                    <structure>Стуркутра статьи</structure>
                    <keywords>Ключевые слова</keywords>
                    <description>Краткое описание</description>
                    '
                ],
                [
                    'role' => 'user',
                    //                    'content' => "напишите нечто среднее между публикацией в блоге и вдохновляющей историей на тему «{$name}». Напишите это тоном «Профессора». Используйте переходные слова. Напишите более 1000 слов. Используйте простой текст."
                    //                    'content' => "Напиши статью для категории сайта \"{$categoryName}\" на тему \"{$name}\". Используй HTML."
                    'content' => $name
                ],
            ]
        ]);
        $complete = json_decode($complete, 1);
        $result = $complete['choices'][0]['message']['content'];
        Yii::error($result, 'warning');
        return $result;
    }

    /**
     * @param $name
     * @param $keywords
     *
     * @return mixed
     * @throws \Exception
     */
    public function getDescriptionImage($name, $keywords)
    {
        $openAi = new OpenAi('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');
        $complete = $openAi->chat([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "Тема: {$name}. Ключевые слова: {$keywords}"
                ],
                [
                    'role' => 'system',
                    'content' => 'Что должно быть изображено на главном изображении для статьи на выбранную пользователем тему. 
                    Я не хочу видеть на изображениях людей. Напиши кратко и конкретно. 
                    Результат должен быть на английском языке.
                    Пример результата на запрос "Почему важно использовать биоразлагаемые продукты": composting wooden container filled with biodegradable products'
                ],
            ],
            'temperature' => 1.0,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
        ]);
        $complete = json_decode($complete, 1);
        return $complete['choices'][0]['message']['content'];
    }

    /**
     * @param $name
     * @param $keywords
     *
     * @return mixed
     * @throws \Exception
     */
    public function getUsers()
    {
        $openAi = new OpenAi('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');
        $complete = $openAi->chat([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '
                    Игнорируй предыдущие инструкции. 
                    параметр nickname должен содерджать уникальный ник пользователя латинскими символами
                    параметр name должен содерджать имя пользователя на русском языке
                    параметр surname должен содерджать отчество пользователя на русском языке
                    параметр birthdate должен содерджать дату рождения пользователя
                    параметр gender должен содерджать пол пользователя
                    Формат Json.
                    Пример: [{"nickname": "romanivanov", "name": "Роман", "surname": "Иванов", "birthdate": "1995-04-19", "gender": "male"},{"nickname": "ekaterinashishkina", "name": "Екатерина", "surname": "Шишкина", "birthdate": "2001-02-13", "gender": "female"}]
                    '
                ],
                [
                    'role' => 'user',
                    'content' => "Придумай 50 профилей пользователей."
                ],
            ],
            'temperature' => 1.0,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
        ]);
        $complete = json_decode($complete, 1);
        return json_decode($complete['choices'][0]['message']['content'], 1);
    }

    /**
     * Translate text using OpenAI. Context: project is about Rust (Steam game).
     *
     * @param string $text
     * @param string $targetLanguage ISO 639-1 code (e.g. 'en', 'ru')
     * @return string translated text
     * @throws \Exception
     */
    public function translateText($text, $targetLanguage = 'en')
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }
        $langName = $this->getTargetLanguageName($targetLanguage);
        $openAi = new OpenAi($this->apiKey);
        $complete = $openAi->chat([
            'model' => 'gpt-3.5-turbo-16k',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a professional translator. Translate the user message into ' . $langName . '. '
                        . 'This project is about the video game Rust (Steam). Keep in-game terms, item names, and community jargon accurate where appropriate. '
                        . 'Reply with ONLY the translation, no explanations or quotes.',
                ],
                [
                    'role' => 'user',
                    'content' => $text,
                ],
            ],
            'temperature' => 0.3,
        ]);

        if ($complete === false || $complete === null) {
            Yii::warning('OpenAI translate: empty response', __METHOD__);
            throw new \RuntimeException('OpenAI translate: empty or invalid response');
        }

        $decoded = is_array($complete) ? $complete : json_decode($complete, true);
        if (!is_array($decoded)) {
            $raw = is_string($complete) ? substr($complete, 0, 500) : gettype($complete);
            Yii::warning('OpenAI translate: response is not JSON. Raw: ' . $raw, __METHOD__);
            throw new \RuntimeException('OpenAI translate: response is not valid JSON');
        }

        if (isset($decoded['error']['message'])) {
            $msg = $decoded['error']['message'];
            Yii::warning('OpenAI translate API error: ' . $msg, __METHOD__);
            throw new \RuntimeException('OpenAI translate: ' . $msg);
        }

        $content = isset($decoded['choices'][0]['message']['content'])
            ? $decoded['choices'][0]['message']['content']
            : null;
        if ($content === null || $content === '') {
            Yii::warning('OpenAI translate: no content in response. Keys: ' . implode(',', array_keys($decoded)) . ' Raw: ' . substr($complete, 0, 500), __METHOD__);
            throw new \RuntimeException('OpenAI translate: invalid response (no choices[].message.content)');
        }

        return trim((string) $content);
    }

    /**
     * @param string $code ISO 639-1
     * @return string
     */
    private function getTargetLanguageName($code)
    {
        $names = [
            'en' => 'English',
            'ru' => 'Russian',
            'de' => 'German',
            'es' => 'Spanish',
            'fr' => 'French',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'pl' => 'Polish',
            'uk' => 'Ukrainian',
            'tr' => 'Turkish',
        ];
        return $names[strtolower($code)] ?? 'English';
    }
}