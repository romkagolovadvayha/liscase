<?php

namespace backend\forms\settings;

use common\components\base\Model;
use common\models\settings\Settings;
use Yii;
use yii\helpers\ArrayHelper;

class SettingsForm extends Model
{
    public string $title;
    public string $title_short;
    public string $description;
    public string $subject;

    public function __construct() {
        parent::__construct();
        $this->title = Settings::getByKey('title', false);
        $this->title_short = Settings::getByKey('title_short', false);
        $this->subject = Settings::getByKey('subject', false);
        $this->description = Settings::getByKey('description', false);
    }

    public function rules(): array
    {
        return ArrayHelper::merge([
            [['title', 'title_short', 'subject', 'description'], 'trim'],
        ], parent::rules());
    }

    public function attributeLabels(): array
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'title' => 'Название сайта',
            'title_short' => 'Краткое название',
            'subject' => 'Тематика сайта',
            'description' => 'Описание сайта',
        ]);
    }

    /**
     * @return bool
     */
    public function saveRecord(): bool
    {
        Settings::setByKey('title', null, $this->title);
        Settings::setByKey('title_short', null, $this->title_short);
        Settings::setByKey('subject', null, $this->subject);
        Settings::setByKey('description', null, $this->description);
        return true;
    }

}
