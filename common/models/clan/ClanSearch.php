<?php

namespace common\models\clan;

use common\components\base\query\DateQuery;
use common\models\clan\Clan;
use common\models\servers\Servers;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;
use Yii;

class ClanSearch extends Clan
{
    public $search;
    public $server_id;
    public $min_rating;
    public $max_rating;
    public $min_members;
    public $max_members;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'user_id', 'user_count', 'server_id', 'min_rating', 'max_rating', 'min_members', 'max_members'], 'integer'],
            [['title', 'description_short', 'description', 'search'], 'string'],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return array_merge(parent::attributeLabels(), [
            'search' => Yii::t('common', 'Поиск'),
            'server_id' => Yii::t('common', 'Сервер'),
            'min_rating' => Yii::t('common', 'Минимальный рейтинг'),
            'max_rating' => Yii::t('common', 'Максимальный рейтинг'),
            'min_members' => Yii::t('common', 'Минимум участников'),
            'max_members' => Yii::t('common', 'Максимум участников'),
        ]);
    }

    /**
     * Поиск кланов с кэшированием
     * @param array $params
     * @param Servers $server
     * @return ArrayDataProvider
     */
    public function searchClans(array $params, Servers $server)
    {
        $this->load($params);

        // Создаем ключ кэша на основе параметров поиска
        $cacheKey = 'clan_search_' . $server->id . '_' . md5(serialize($this->getAttributes()) . serialize($params));
        
        // Пытаемся получить данные из кэша
        $cachedData = Yii::$app->cache->get($cacheKey);
        if ($cachedData !== false) {
            return $cachedData;
        }

        // Получаем кланы для сервера (здесь должна быть логика получения кланов)
        $clans = $this->getClansForServer($server);
        
        // Применяем фильтры
        $filteredClans = $this->applyFilters($clans);

        // Создаем DataProvider
        $dataProvider = new ArrayDataProvider([
            'allModels' => $filteredClans,
            'totalCount' => count($filteredClans),
            'pagination' => [
                'pageSize' => 20,
            ],
            'sort' => [
                'attributes' => ['title', 'rating', 'kills', 'users_count', 'rocket_basic', 'c4thrown', 'scrap', 'sulfur.ore'],
                'defaultOrder' => ['rating' => SORT_DESC],
            ],
        ]);

        // Кэшируем результат на 5 минут
        Yii::$app->cache->set($cacheKey, $dataProvider, 300);

        return $dataProvider;
    }

    /**
     * Получение кланов для сервера
     * @param Servers $server
     * @return array
     */
    private function getClansForServer(Servers $server)
    {
        return Clan::getClans($server);
    }

    /**
     * Применение фильтров к списку кланов
     * @param array $clans
     * @return array
     */
    private function applyFilters(array $clans)
    {
        if (empty($clans)) {
            return $clans;
        }

        return array_filter($clans, function($clan) {
            // Фильтр по поисковому запросу
            if (!empty($this->search)) {
                $searchLower = mb_strtolower($this->search);
                $titleMatch = !empty($clan['title']) && mb_strpos(mb_strtolower($clan['title']), $searchLower) !== false;
                $descMatch = !empty($clan['description_short']) && mb_strpos(mb_strtolower($clan['description_short']), $searchLower) !== false;
                
                if (!$titleMatch && !$descMatch) {
                    return false;
                }
            }

            // Фильтр по рейтингу
            if (!empty($this->min_rating) && isset($clan['rating']) && $clan['rating'] < $this->min_rating) {
                return false;
            }
            if (!empty($this->max_rating) && isset($clan['rating']) && $clan['rating'] > $this->max_rating) {
                return false;
            }

            // Фильтр по количеству участников
            if (!empty($this->min_members) && isset($clan['users_count']) && $clan['users_count'] < $this->min_members) {
                return false;
            }
            if (!empty($this->max_members) && isset($clan['users_count']) && $clan['users_count'] > $this->max_members) {
                return false;
            }

            return true;
        });
    }

    /**
     * Стандартный поиск для админки
     * @param array $params
     * @param callable|null $filter
     * @return ActiveDataProvider
     */
    public function search(array $params, callable $filter = null)
    {
        $this->load($params);

        $query = self::find();

        if (is_callable($filter)) {
            call_user_func($filter, $query);
        }

        $query
            ->andFilterWhere([
                                 'id'       => $this->id,
                             ])
            ->andFilterWhere(['LIKE', 'title', $this->title])
            ->andFilterWhere(['LIKE', 'description_short', $this->search]);

        DateQuery::addDateCondition($query, $this, 'created_at');
        return new ActiveDataProvider([
                                          'query'      => $query,
                                          'sort'       => [
                                              'defaultOrder' => ['id' => SORT_DESC],
                                          ],
                                          'pagination' => [
                                              'pageSize' => 20,
                                          ],
                                      ]);
    }
}