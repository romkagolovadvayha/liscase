<?php

namespace common\components\base\behaviors;

use yii\db\BaseActiveRecord;
use yii\db\Schema;

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
        $this->_updateAttribute($event, $this->updateAttribute);
    }

    /**
     * @param $event
     * @param $attribute
     */
    private function _updateAttribute($event, $attribute)
    {
        $sender = $event->sender;

        if (!$sender->hasAttribute($attribute) || !empty($sender->getAttribute($attribute))) {
            return;
        }

        $tableSchema = $sender->getTableSchema();
        $column = $tableSchema ? $tableSchema->getColumn($attribute) : null;

        // Колонки UNIX time (INT/BIGINT) — иначе строка даты попадает в INT и даёт мусор (1970-01-01 и т.п.)
        if ($column && $this->isIntegerTimestampColumn($column)) {
            $sender->setAttribute($attribute, time());
        } else {
            $sender->setAttribute($attribute, date('Y-m-d H:i:s'));
        }
    }

    private function isIntegerTimestampColumn($column): bool
    {
        return in_array($column->type, [
            Schema::TYPE_INTEGER,
            Schema::TYPE_BIGINT,
            Schema::TYPE_SMALLINT,
            Schema::TYPE_TINYINT,
        ], true);
    }

    public function handlerBeforeUpdate($event)
    {
        $this->_updateAttribute($event, $this->updateAttribute);
    }
}
