<?php

namespace common\models\user;

use Yii;
use yii\helpers\ArrayHelper;
use common\components\base\ActiveRecord;
use common\components\base\behaviors\NestedSetsBehavior;

/**
 * This is the model class for table user_tree.
 *
 * @property integer $id
 * @property integer $user_id
 * @property integer $parent_user_id
 * @property integer $lft
 * @property integer $rgt
 * @property integer $level
 * @property integer $created_at
 *
 * @property User    $user
 * @property User    $parentUser
 */
class UserTree extends ActiveRecord
{
    /**
     * @return UserTreeQuery
     */
    public static function find()
    {
        return new UserTreeQuery(get_called_class());
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'user_tree';
    }

    public function rules()
    {
        return [
            [['user_id', 'parent_user_id', 'lft', 'rgt', 'level'], 'integer'],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return ArrayHelper::merge(parent::behaviors(), [
            [
                'class'          => NestedSetsBehavior::class,
                'depthAttribute' => 'level',
            ],
        ]);
    }

    public function transactions()
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_ALL,
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getParentUser()
    {
        return $this->hasOne(User::class, ['id' => 'parent_user_id']);
    }

    /**
     * @param int $userId
     * @param int $parentUserId
     *
     * @return bool
     */
    public static function appendUser($userId, $parentUserId)
    {
        if (empty($userId) || empty($parentUserId)) {
            return false;
        }

        $parentTree = self::findOne(['user_id' => $parentUserId]);

        $userTree = new self();

        $userTree->user_id        = $userId;
        $userTree->parent_user_id = $parentUserId;

        if ($userTree->appendTo($parentTree)) {
            return true;
        }

        return false;
    }

    /**
     * @param int|null $userLevel
     *
     * @return int
     */
    public function getLevelNumber($userLevel = null)
    {
        return $this->level - $userLevel;
    }
}
