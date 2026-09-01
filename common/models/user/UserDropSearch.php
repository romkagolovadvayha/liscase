<?php

namespace common\models\user;

use common\models\box\Drop;
use yii\data\ActiveDataProvider;
use Yii;
use yii\db\ActiveQuery;

class UserDropSearch extends UserDrop
{
    public $user_username;
    /** Не объявлять public $user_id / $drop_id — совпадают с колонками AR и ломают связи user / dropOne. */

    public $server_id;
    public $drop_name;

    /** @var string|null Steam ID (частичное совпадение) */
    public $steam_id;

    /**
     * Фильтр «товар активен в магазине»: '' — все, 1 — да (market + каталог), 0 — нет.
     * @var string|int|null
     */
    public $drop_in_store;

    public function rules(): array
    {
        return [
            [['id', 'user_id', 'drop_id', 'status', 'server_id'], 'integer'],
            [['user_username', 'drop_name', 'steam_id', 'drop_in_store'], 'safe'],
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
            'steam_id' => Yii::t('common', 'Steam ID'),
            'drop_in_store' => Yii::t('common', 'Активен в магазине'),
        ]);
    }

    /**
     * @param array $params
     * @param callable|null $filter
     * @return ActiveDataProvider
     */
    public function search(array $params, ?callable $filter = null)
    {
        $this->load($params);

        /** @var ActiveQuery $query */
        $query = self::find()
            ->alias('ud')
            ->with(['user', 'user.server', 'dropOne.dropImages']);

        if (is_callable($filter)) {
            call_user_func($filter, $query);
        }

        $query
            ->andFilterWhere([
                'ud.id' => $this->id,
                'ud.user_id' => $this->user_id,
                'ud.drop_id' => $this->drop_id,
                'ud.status' => $this->status,
            ]);

        $hasExtraJoin = false;
        if ($this->steam_id !== null && $this->steam_id !== '') {
            $hasExtraJoin = true;
            $query->innerJoin(['uf' => User::tableName()], 'uf.id = ud.user_id');
            $query->andWhere(['like', 'uf.steam_id', trim((string) $this->steam_id)]);
        }

        if ($this->drop_in_store !== null && $this->drop_in_store !== '') {
            $hasExtraJoin = true;
            $query->leftJoin(['ds' => Drop::tableName()], 'ds.id = ud.drop_id');
            if ((string) $this->drop_in_store === '1') {
                $query->andWhere([
                    'ds.market_status' => Drop::MARKET_STATUS_ACTIVE,
                    'ds.status' => Drop::STATUS_ACTIVE,
                ]);
            } elseif ((string) $this->drop_in_store === '0') {
                $query->andWhere([
                    'or',
                    ['ds.id' => null],
                    [
                        'not',
                        [
                            'and',
                            ['ds.market_status' => Drop::MARKET_STATUS_ACTIVE],
                            ['ds.status' => Drop::STATUS_ACTIVE],
                        ],
                    ],
                ]);
            }
        }
        
        // Фильтр по username через подзапрос
        if (!empty($this->user_username)) {
            $userIds = User::find()
                ->select('id')
                ->where(['LIKE', 'username', $this->user_username])
                ->column();
            if (!empty($userIds)) {
                $query->andWhere(['ud.user_id' => $userIds]);
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
                $query->andWhere(['ud.user_id' => $userIds]);
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
                $query->andWhere(['ud.drop_id' => $dropIds]);
            } else {
                $query->andWhere('1=0'); // Нет результатов
            }
        }

        // При JOIN только строки user_drop — иначе SELECT * смешивает колонки (id и т.д.) и ломает гидратацию.
        if ($hasExtraJoin) {
            $query->select(['ud.*']);
        }

        $tableName = 'ud';
        
        return new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => [
                    'created_at' => SORT_DESC,
                ],
                'attributes' => [
                    'id' => [
                        'asc' => [$tableName . '.id' => SORT_ASC],
                        'desc' => [$tableName . '.id' => SORT_DESC],
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
                        'asc' => ['ud.user_id' => SORT_ASC],
                        'desc' => ['ud.user_id' => SORT_DESC],
                    ],
                    'drop_name' => [
                        'asc' => ['ud.drop_id' => SORT_ASC],
                        'desc' => ['ud.drop_id' => SORT_DESC],
                    ],
                ],
            ],
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
    }
}
