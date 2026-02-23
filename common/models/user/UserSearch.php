<?php

namespace common\models\user;

use common\models\auth\AuthItem;
use common\models\servers\Servers;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Expression;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use common\components\base\query\DateQuery;

class UserSearch extends User
{
    /** @var string Фильтр: только онлайн (на сервере) — 1 или пусто */
    public $is_online;
    /** @var string Фильтр: только с активным VIP — 1 или пусто */
    public $has_vip;

    public function rules(): array
    {
        return [
            [
                [
                    'id',
                    'email',
                    'status',
                    'username',
                    'steam_id',
                    'ref_code',
                    'country_id',
                    'is_mailer',
                    'created_at',
                    'last_visit',
                    'userPhone',
                    'investorLevel',
                    'hasActiveLicense',
                    'server_id',
                    'is_online',
                    'has_vip',
                ],
                'safe',
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'is_online' => Yii::t('common', 'Только онлайн'),
            'has_vip' => Yii::t('common', 'Только с VIP'),
            'server_id' => Yii::t('common', 'Сервер игрока'),
        ]);
    }

    /**
     * @param array         $params
     * @param callable|null $filter
     *
     * @return ActiveDataProvider
     */
    public function search($params, callable $filter = null)
    {
        $this->load($params);

        $query = self::find()
            ->alias('t');

        if (is_callable($filter)) {
            call_user_func($filter, $query);
        }

        if (!empty($this->ref_code)) {
            $refCodes = explode(',', $this->ref_code);
            foreach ($refCodes as &$refCode) {
                $refCode = trim($refCode);
            }
            unset($refCode);

            $query->andWhere(['IN', 't.ref_code', $refCodes]);
        }

        $query
            ->joinWith(['userProfile up'])
            ->andFilterWhere([
                't.id'              => $this->id,
                't.status'          => $this->status,
                't.steam_id'        => $this->steam_id,
                't.server_id'       => $this->server_id,
            ])
            ->andFilterWhere(['LIKE', 't.username', $this->username])
            ->andFilterWhere(['LIKE', 't.email', $this->email]);

        if ($this->is_online === '1' || $this->is_online === 1) {
            // Онлайн = был на сервере в последние 10 минут (как в User::isOnline())
            $threshold = date('Y-m-d H:i:s', time() - 10 * 60);
            $query->andWhere(['>=', 't.last_visit_server_at', $threshold]);
        }

        if ($this->has_vip === '1' || $this->has_vip === 1) {
            $query->andWhere([
                'EXISTS',
                (new Query())
                    ->select([new Expression('1')])
                    ->from(['uv' => 'user_vip'])
                    ->where('uv.user_id = t.id')
                    ->andWhere(['>', 'uv.expires_at', date('Y-m-d H:i:s')]),
            ]);
        }

        DateQuery::addDateCondition($query, $this, 't.created_at');

        return $this->_getDataProvider($query);
    }

    /**
     * @param $query
     *
     * @return ActiveDataProvider
     */
    protected function _getDataProvider($query)
    {
        return new ActiveDataProvider([
            'query' => $query,
            'sort'  => [
                'defaultOrder' => ['last_visit_server_at' => SORT_DESC],
            ],
        ]);
    }

    public static function authRoles(): array
    {
        return ArrayHelper::map(AuthItem::find()->select('name')->asArray()->all(), 'name', 'name');
    }

    public static function authRolesNames(): array
    {
        return ArrayHelper::map(AuthItem::find()->select(['name', 'description'])->asArray()->all(), 'name', 'description');
    }

    /**
     * Список серверов для фильтра (активные, по sort).
     * @return array id => name
     */
    public static function getServerList(): array
    {
        $servers = Servers::find()
            ->andWhere(['status' => Servers::STATUS_ACTIVE])
            ->orderBy(['sort' => SORT_ASC])
            ->all();
        return ArrayHelper::map($servers, 'id', 'name');
    }
}
