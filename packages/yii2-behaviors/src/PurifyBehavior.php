<?php

declare(strict_types=1);

namespace yii2mod\behaviors;

use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\helpers\HtmlPurifier;

class PurifyBehavior extends Behavior
{
    /** @var string[] */
    public $attributes = [];

    /** @var array|string|null */
    public $config;

    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
        ];
    }

    public function beforeValidate($event = null)
    {
        foreach ($this->attributes as $attribute) {
            $this->owner->$attribute = HtmlPurifier::process(
                $this->owner->$attribute,
                $this->config
            );
        }
    }
}
