<?php

namespace common\models\user;

use common\components\base\query\DateQuery;
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
            ->alias('ud')
            ->joinWith(['user u'])
            ->joinWith(['user.server s'])
            ->joinWith(['dropOne d']);

        if (is_callable($filter)) {
            call_user_func($filter, $query);
        }

        $query
            ->andFilterWhere([
                'ud.id' => $this->id,
                'ud.user_id' => $this->user_id,
                'ud.drop_id' => $this->drop_id,
                'ud.status' => $this->status,
                'u.server_id' => $this->server_id,
            ])
            ->andFilterWhere(['LIKE', 'u.username', $this->user_username])
            ->andFilterWhere(['LIKE', 'd.name', $this->drop_name]);

        return new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => [
                    'sended_at' => SORT_DESC,
                    'created_at' => SORT_DESC,
                ],
                'attributes' => [
                    'id' => [
                        'asc' => ['ud.id' => SORT_ASC],
                        'desc' => ['ud.id' => SORT_DESC],
                    ],
                    'sended_at' => [
                        'asc' => ['ud.sended_at' => SORT_ASC],
                        'desc' => ['ud.sended_at' => SORT_DESC],
                    ],
                    'created_at' => [
                        'asc' => ['ud.created_at' => SORT_ASC],
                        'desc' => ['ud.created_at' => SORT_DESC],
                    ],
                    'status' => [
                        'asc' => ['ud.status' => SORT_ASC],
                        'desc' => ['ud.status' => SORT_DESC],
                    ],
                    'user_username' => [
                        'asc' => ['u.username' => SORT_ASC],
                        'desc' => ['u.username' => SORT_DESC],
                    ],
                    'drop_name' => [
                        'asc' => ['d.name' => SORT_ASC],
                        'desc' => ['d.name' => SORT_DESC],
                    ],
                ],
            ],
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
    }
}

