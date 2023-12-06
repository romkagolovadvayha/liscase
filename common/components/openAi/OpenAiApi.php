<?php

namespace common\components\openAi;

use common\models\settings\Settings;
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
                    Игнорируй предыдущие инструкции. Ты SEO-копирайтер, для сайта на тему "' . Settings::getByKey('subject', false) . '".
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
                    Игнорируй предыдущие инструкции. Ты SEO-копирайтер, для сайта на тему "' . Settings::getByKey('subject', false) . '".
                    SEO-копирайтер – это специалист по написанию статей с ключевыми словами. Такие тексты используют для seo-продвижения, продажи ссылок и т.п. Чаще всего это тексты для поискового робота, но при особом мастерстве копирайтера простым пользователям будет интересно почитать такой материал.
                    Пиши статью для категории ' . $name . '
                    ' . $name . ' - ' . $description .'
                    В параметре title - заголовок для статьи
                    Формат Json.
                    Пример: [{"title": "Заголовок"},{"title": "Заголовок"}]
                    '
                ],
                [
                    'role' => 'user',
                    'content' => "Придумай 50 заголовков для различных статей"
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
                    Игнорируй предыдущие инструкции. Ты пользователь сайта на тему "' . Settings::getByKey('subject', false) . '".
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
                    Ты научный писатель, для сайта на тему "' . Settings::getByKey('subject', false) . '".
                    Статья для категории сайта "' . $categoryName . '"
                    "' . $categoryName . '" - ' . $description . '
                    Ваша статья должна содержать всесторонний анализ ключевых факторов, влияющих на заданную пользователем тему. 
                    Чтобы сделать вашу статью обьемной, информативной и интересной, обязательно обсудите компромиссы, связанные с балансировкой различных факторов, и изучите проблемы, связанные с различными подходами. 
                    В вашей статье также следует подчеркнуть важность учета влияния на принятие решений по теме заданной пользователем теме. 
                    Статья обязательно должна содержать минимум 1500 слов!
                    Когда речь идет о написании контента, решающее значение имеют два фактора: "недоумение" и "разрывность". Недоумение измеряет сложность текста. Отдельно, разрывность сравнивает вариации предложений. Люди склонны писать более разрозненно, например, с некоторыми более длинными или сложными предложениями наряду с более короткими. Предложения ИИ, как правило, более однородны. Поэтому при написании следующего контента, который я попрошу вас создать, мне нужно, чтобы в нем было достаточное количество недоумения и взрывчатости. 
                    Наконец, ваша статья должна быть написана информативным и объективным тоном, доступным широкой аудитории.
                    Статья должна быть в теге text.
                    Вставь где считаешь нужным изображения, но не более 3 и напиши в тегах image, что на них должно быть изображено.
                    Изображения не должны содержать насилия и других вещей запрещенных законом России.
                    Пример ответа:
                        <h1>Заголовок</h1>
                        <image>на изображении должно быть изображено дерево с красными яблоками, на фоне природа и речка</image>
                        <h2>Подраздел</h2>
                        <p>Текст</p>
                        <image>на изображении должно быть изображена тарелка с овсяным печеньем с шоколадной крошкой</image>
                        <ul><li></li></ul>
                        <ol><li></li></ol>
                        <b></b>
                        <i></i>
                    '
                ],
                [
                    'role' => 'user',
                    //                    'content' => "напишите нечто среднее между публикацией в блоге и вдохновляющей историей на тему «{$name}». Напишите это тоном «Профессора». Используйте переходные слова. Напишите более 1000 слов. Используйте простой текст."
                    //                    'content' => "Напиши статью для категории сайта \"{$categoryName}\" на тему \"{$name}\". Используй HTML."
                    'content' => "Напиши статью с изображениями на тему: $name. Придерживайся следующей структуры: $structrure"
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
                    Ты научный писатель, для сайта на тему "' . Settings::getByKey('subject', false) . '".
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

}