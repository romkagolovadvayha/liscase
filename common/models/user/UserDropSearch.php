<?php

namespace common\models\user;

use common\components\base\query\DateQuery;
use common\models\box\Drop;
use common\models\servers\Servers;
use yii\data\ActiveDataProvider;
use Yii;

class UserDropSearch extends UserDrop
{
    public $user_username;
    public $user_id;
    public $server_id;
    public $drop_name;
    public $drop_id;

    public function rules(): array
    {
        return [
            [['id', 'user_id', 'drop_id', 'status', 'server_id'], 'integer'],
            [['user_username', 'drop_name'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            'user_username' => Yii::t('common', 'Пользователь'),
            'user_id' => Yii::t('common', 'ID пользователя'),
            'server_id' => Yii::t('common', 'Сервер'),
            'drop_name' => Yii::t('common', 'Предмет'),
            'drop_id' => Yii::t('common', 'ID предмета'),
            'status' => Yii::t('common', 'Статус'),
            'sended_at' => Yii::t('common', 'Дата отправки'),
        ]);
    }

    /**
     * @param array $params
     * @param callable|null $filter
     * @return ActiveDataProvider
     */
    public function search(array $params, callable $filter = null)
    {
        $this->load($params);

        $query = self::find()
            ->with(['user', 'user.server']);

        if (is_callable($filter)) {
            call_user_func($filter, $query);
        }

        $query
            ->andFilterWhere([
                'id' => $this->id,
                'user_id' => $this->user_id,
                'drop_id' => $this->drop_id,
                'status' => $this->status,
            ]);
        
        // Фильтр по username через подзапрос
        if (!empty($this->user_username)) {
            $userIds = User::find()
                ->select('id')
                ->where(['LIKE', 'username', $this->user_username])
                ->column();
            if (!empty($userIds)) {
                $query->andWhere(['user_id' => $userIds]);
            } else {
                $query->andWhere('1=0'); // Нет результатов
            }
        }
        
        // Фильтр по server_id через подзапрос
        if (!empty($this->server_id)) {
            $userIds = User::find()
                ->select('id')
                ->where(['server_id' => $this->server_id])
                ->column();
            if (!empty($userIds)) {
                $query->andWhere(['user_id' => $userIds]);
            } else {
                $query->andWhere('1=0'); // Нет результатов
            }
        }
        
        // Фильтр по drop_name через подзапрос
        if (!empty($this->drop_name)) {
            $dropIds = Drop::find()
                ->select('id')
                ->where(['LIKE', 'name', $this->drop_name])
                ->column();
            if (!empty($dropIds)) {
                $query->andWhere(['drop_id' => $dropIds]);
            } else {
                $query->andWhere('1=0'); // Нет результатов
            }
        }

        $tableName = self::tableName();
        
        return new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => [
                    $tableName . '.sended_at' => SORT_DESC,
                    $tableName . '.created_at' => SORT_DESC,
                ],
                'attributes' => [
                    'id' => [
                        'asc' => [$tableName . '.id' => SORT_ASC],
                        'desc' => [$tableName . '.id' => SORT_DESC],
                    ],
                    'sended_at' => [
                        'asc' => [$tableName . '.sended_at' => SORT_ASC],
                        'desc' => [$tableName . '.sended_at' => SORT_DESC],
                    ],
                    'created_at' => [
                        'asc' => [$tableName . '.created_at' => SORT_ASC],
                        'desc' => [$tableName . '.created_at' => SORT_DESC],
                    ],
                    'status' => [
                        'asc' => [$tableName . '.status' => SORT_ASC],
                        'desc' => [$tableName . '.status' => SORT_DESC],
                    ],
                    'user_username' => [
                        'asc' => [$tableName . '.user_id' => SORT_ASC],
                        'desc' => [$tableName . '.user_id' => SORT_DESC],
                    ],
                    'drop_name' => [
                        'asc' => [$tableName . '.drop_id' => SORT_ASC],
                        'desc' => [$tableName . '.drop_id' => SORT_DESC],
                    ],
                ],
            ],
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
    }
}

