<?php

declare(strict_types=1);

namespace yii2mod\behaviors;

use Carbon\Carbon;
use yii\base\Behavior;
use yii\db\ActiveRecord;

class CarbonBehavior extends Behavior
{
    /** @var string[] */
    public $attributes = [];

    public $dateFormat = 'Y-m-d H:i:s';

    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_FIND => 'attributesToCarbon',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'attributesToDefaultFormat',
            ActiveRecord::EVENT_AFTER_UPDATE => 'attributesToCarbon',
        ];
    }

    public function attributesToCarbon($event = null)
    {
        foreach ($this->attributes as $attribute) {
            $value = $this->owner->$attribute;
            if (empty($value)) {
                continue;
            }

            if (is_numeric($value)) {
                $this->owner->$attribute = Carbon::createFromTimestamp($value);
            } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $value) === 1) {
                $this->owner->$attribute = Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
            } else {
                $this->owner->$attribute = Carbon::createFromFormat($this->dateFormat, $value);
            }
        }
    }

    public function attributesToDefaultFormat($event = null)
    {
        foreach ($this->attributes as $attribute) {
            $oldAttributeValue = $this->owner->oldAttributes[$attribute] ?? null;
            if ($this->owner->$attribute instanceof Carbon && is_numeric($oldAttributeValue)) {
                $this->owner->$attribute = $this->owner->$attribute->timestamp;
            }
        }
    }
}
