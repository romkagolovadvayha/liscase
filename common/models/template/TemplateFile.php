<?php
namespace common\models\template;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $template_id
 * @property string $path
 * @property string $root_alias  // 'frontend'|'common'
 * @property string $ext         // 'php'|'twig'|'scss'
 * @property string $content
 * @property string|null $checksum
 * @property int|null $updated_by
 * @property int $created_at
 * @property int $updated_at
 */
class TemplateFile extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%template_file}}';
    }

    public function behaviors()
    {
        return [TimestampBehavior::class];
    }

    public function rules()
    {
        return [
            [['template_id', 'path', 'root_alias', 'ext', 'content'], 'required'],
            [['template_id', 'updated_by', 'created_at', 'updated_at'], 'integer'],
            [['content'], 'string'],
            [['path'], 'string', 'max' => 512],
            [['ext'], 'in', 'range' => ['php','twig','scss']],
            [['checksum'], 'string', 'max' => 64],
            [['template_id','root_alias','path'], 'unique', 'targetAttribute' => ['template_id','root_alias','path']],
        ];
    }

    public function beforeValidate()
    {
        if ($this->content !== null) {
            $this->checksum = hash('sha256', $this->content);
        }
        return parent::beforeValidate();
    }
}
