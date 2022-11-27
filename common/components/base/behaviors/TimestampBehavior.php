<?php

namespace common\components\base\behaviors;

use Yii;
use yii\db\BaseActiveRecord;

class TimestampBehavior extends \yii\base\Behavior
{
    public $createAttribute     = 'created_at';
    public $createDateAttribute = 'created_at_date';
    public $updateAttribute     = 'updated_at';

    public function events()
    {
        return [
            BaseActiveRecord::EVENT_BEFORE_INSERT => [$this, 'handlerBeforeInsert'],
            BaseActiveRecord::EVENT_BEFORE_UPDATE => [$this, 'handlerBeforeUpdate'],
        ];
    }

    public function handlerBeforeInsert($event)
    {
        $this->_updateAttribute($event, $this->createAttribute);
        $this->_updateAttribute($event, $this->createDateAttribute);
    }

    /**
     * @param $event
     * @param $attribute
     */
    private function _updateAttribute($event, $attribute)
    {
        $sender = $event->sender;

        if ($sender->hasAttribute($attribute) && empty($sender->getAttribute($attribute))) {
            $sender->setAttribute($attribute, date('Y-m-d H:i:s'));
        }
    }

    public function handlerBeforeUpdate($event)
    {
        $this->_updateAttribute($event, $this->updateAttribute);
    }
}
