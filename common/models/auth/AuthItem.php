<?php

namespace common\models\auth;

use Yii;
use common\models\user\User;

/**
 * This is the model class for table "auth_item".
 *
 * @property string           $name
 * @property int              $type
 * @property string           $description
 * @property string           $rule_name
 * @property resource         $data
 * @property int              $created_at
 * @property int              $updated_at
 *
 * @property AuthAssignment[] $authAssignments
 * @property User[]           $users
 * @property AuthRule         $ruleName
 * @property AuthItemChild[]  $authItemChildren
 * @property AuthItemChild[]  $authItemChildren0
 * @property AuthItem[]       $parents
 * @property AuthItem[]       $children
 */
class AuthItem extends \common\components\base\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'auth_item';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name', 'type'], 'required'],
            [['type', 'created_at', 'updated_at'], 'integer'],
            [['description', 'data'], 'string'],
            [['name', 'rule_name'], 'string', 'max' => 64],
            [['name'], 'unique'],
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAuthAssignments()
    {
        return $this->hasMany(AuthAssignment::class, ['item_name' => 'name']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUsers()
    {
        return $this->hasMany(User::class, ['id' => 'user_id'])
            ->viaTable('auth_assignment', ['item_name' => 'name']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRuleName()
    {
        return $this->hasOne(AuthRule::class, ['name' => 'rule_name']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAuthItemChildren()
    {
        return $this->hasMany(AuthItemChild::class, ['child' => 'name']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAuthItemChildren0()
    {
        return $this->hasMany(AuthItemChild::class, ['parent' => 'name']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getParents()
    {
        return $this->hasMany(AuthItem::class, ['name' => 'parent'])
            ->viaTable('auth_item_child', ['child' => 'name']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getChildren()
    {
        return $this->hasMany(AuthItem::class, ['name' => 'child'])
            ->viaTable('auth_item_child', ['parent' => 'name']);
    }
}
