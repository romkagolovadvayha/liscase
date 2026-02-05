<?php

namespace api\controllers\v1;

use Yii;
use OpenApi\Annotations as OA;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;
use common\models\user\User;
use common\models\user\UserPayoutSkins;
use common\models\skindrops\Skindrops;
use frontend\modules\user\SkinsSearch;
use frontend\forms\user\SkinsForm;
use api\components\jwt\JwtAuthFilter;
use api\components\jwt\JwtService;

/**
 * Контроллер для работы с каталогом скинов
 * 
 * @package api\controllers\v1
 * @OA\Tag(name="Skins")
 */
class SkinsController extends BaseApiController
{
    /**
     * Настройка behaviors
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // JWT авторизация только для покупки (actionConfirm)
        $behaviors['authenticator'] = [
            'class' => JwtAuthFilter::class,
            'only' => ['confirm'],
            'except' => ['index', 'giveaway', 'skindrops', 'options'],
        ];

        return $behaviors;
    }

    /**
     * Публичный каталог скинов для покупки
     * 
     * @OA\Get(
     *     path="/v1/skins",
     *     operationId="getSkins",
     *     tags={"Skins"},
     *     summary="Получить каталог скинов",
     *     description="Публичный метод, авторизация не требуется",
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string", enum={"rust", "cs2"}, default="rust")),
     *     @OA\Parameter(name="name", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Каталог скинов")
     * )
     */
    public function actionIndex($type = 'rust')
    {
        if (!Yii::$app->settings->get('section_skindrops')) {
            throw new NotFoundHttpException('Страница не найдена');
        }

        // Валидация типа
        if (!in_array($type, ['rust', 'cs2'])) {
            $type = 'rust';
        }

        $searchModel = new SkinsSearch();
        $params = Yii::$app->request->get();

        // Устанавливаем параметры поиска
        $searchModel->name = $params['name'] ?? null;
        $searchModel->quality = $params['quality'] ?? null;
        $searchModel->price_min = isset($params['price_min']) ? (int)$params['price_min'] : null;
        $searchModel->price_max = isset($params['price_max']) ? (int)$params['price_max'] : null;
        $searchModel->sort = $params['sort'] ?? 'price_asc';

        // Получаем данные через поисковую модель
        $dataProvider = $searchModel->search($params, $type);
        $items = $dataProvider->getModels();

        // Форматируем данные для API
        $formattedItems = [];
        foreach ($items as $item) {
            $formattedItems[] = [
                'id' => $item['id'] ?? null,
                'name' => $item['name'] ?? '',
                'price' => (float)($item['price'] ?? 0),
                'image' => $item['image'] ?? null,
                'image300' => $item['image300'] ?? null,
                'type' => $type,
                'market_info' => [
                    'market_id' => $item['id'] ?? null,
                    'market_url' => $item['url'] ?? null,
                ],
            ];
        }

        $pagination = $dataProvider->getPagination();

        return $this->successResponse([
            'items' => $formattedItems,
            'filters' => [
                'type' => ['rust', 'cs2'],
                'search' => $searchModel->name,
                'sort' => $searchModel->sort,
            ],
        ], [
            'pagination' => [
                'page' => $pagination->page + 1,
                'pageSize' => $pagination->pageSize,
                'totalCount' => $pagination->totalCount,
                'totalPages' => $pagination->getPageCount(),
            ],
        ]);
    }

    /**
     * Подтверждение покупки скина (требует JWT авторизации)
     * 
     * @OA\Post(
     *     path="/v1/skins/confirm/{id}",
     *     operationId="confirmSkinPurchase",
     *     tags={"Skins"},
     *     summary="Подтвердить покупку скина",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID скина",
     *         @OA\Schema(type="integer", example=12345)
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         required=false,
     *         description="Тип игры",
     *         @OA\Schema(type="string", enum={"rust", "cs2"}, default="rust")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Покупка обработана",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=400, description="Недостаточно средств или неверные параметры"),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=404, description="Скин не найден")
     * )
     */
    public function actionConfirm($id, $type = 'rust')
    {
        if (!Yii::$app->settings->get('section_skindrops')) {
            throw new NotFoundHttpException('Страница не найдена');
        }

        $user = $this->getCurrentUser();

        // Валидация типа
        if (!in_array($type, ['rust', 'cs2'])) {
            return $this->errorResponse('INVALID_TYPE', 'Неверный тип игры. Допустимые значения: rust, cs2', [], 400);
        }

        // Выбираем маркетплейс
        if ($type == 'rust') {
            $market = Yii::$app->rustTm;
        } else {
            $market = Yii::$app->csGoMarket;
        }

        // Получаем данные о скинах
        $data = $market->items();
        if (empty($data[$id])) {
            throw new NotFoundHttpException('Скин не найден');
        }

        $item = $data[$id];
        $balance = $user->getSkinsBalance();

        // Проверка баланса
        if ($item['price'] > $balance->balance) {
            return $this->errorResponse('INSUFFICIENT_BALANCE', 'Недостаточно средств', [
                'balance' => (float)$balance->balance,
                'required' => (float)$item['price'],
            ], 400);
        }

        // Создаем форму для покупки
        $formModel = new SkinsForm();
        $formModel->market = $market;
        $formModel->type = $type;
        $formModel->id = $id;
        $formModel->amount = $item['price'];

        if ($formModel->load(Yii::$app->request->post(), '')) {
            if ($formModel->saveRecord()) {
                // Получаем обновленный баланс
                $balance->refresh();
                
                return $this->successResponse([
                    'message' => 'Скин отправляется, ожидайте трейд-обмен',
                    'payout_id' => $formModel->payout_id ?? null,
                    'balance' => (float)$balance->balance,
                ]);
            } else {
                return $this->validationErrorResponse($formModel);
            }
        }

        // Если это GET запрос, возвращаем информацию о скине и балансе
        return $this->successResponse([
            'skin' => [
                'id' => $id,
                'name' => $item['name'] ?? '',
                'price' => (float)$item['price'],
                'image' => $item['image'] ?? null,
            ],
            'balance' => (float)$balance->balance,
            'can_buy' => $item['price'] <= $balance->balance,
        ]);
    }

    /**
     * Получить данные о раздаче скинов
     * 
     * @OA\Get(
     *     path="/v1/skins/giveaway",
     *     operationId="getSkinGiveaway",
     *     tags={"Skins"},
     *     summary="Получить данные о раздаче скинов",
     *     description="Публичный метод, возвращает последние раздачи скинов и общую сумму",
     *     @OA\Response(response=200, description="Данные о раздаче скинов")
     * )
     */
    public function actionGiveaway()
    {
        if (!Yii::$app->settings->get('section_skindrops')) {
            throw new NotFoundHttpException('Страница не найдена');
        }

        // Кэшируем данные на 10 минут
        $cacheKey = 'skin_giveaway_data';
        $cache = Yii::$app->cache;
        $data = $cache->get($cacheKey);

        if ($data === false) {
            $data = [
                'rust' => [
                    'recentDrops' => [],
                    'totalCount' => 0,
                ],
                'cs2' => [
                    'recentDrops' => [],
                    'totalCount' => 0,
                ],
            ];

            // Получаем последние 4 раздачи скинов для Rust
            $lastPayoutsRust = UserPayoutSkins::find()
                ->where(['status' => UserPayoutSkins::STATUS_SUCCESS, 'type' => 'rust'])
                ->orderBy(['created_at' => SORT_DESC])
                ->limit(5)
                ->all();

            foreach ($lastPayoutsRust as $payout) {
                $user = $payout->user;
                $data['rust']['recentDrops'][] = [
                    'id' => $payout->id,
                    'name' => $payout->name,
                    'price' => (float)$payout->amount,
                    'image' => $payout->image300 ?: $payout->image,
                    'user' => [
                        'id' => $user->id ?? null,
                        'username' => $user->username ?? 'Неизвестный',
                        'avatar' => $user->getAvatar() ?? null,
                    ],
                    'created_at' => $payout->created_at,
                ];
            }

            // Получаем последние 4 раздачи скинов для CS2
            $lastPayoutsCs2 = UserPayoutSkins::find()
                ->where(['status' => UserPayoutSkins::STATUS_SUCCESS, 'type' => 'cs2'])
                ->orderBy(['created_at' => SORT_DESC])
                ->limit(5)
                ->all();

            foreach ($lastPayoutsCs2 as $payout) {
                $user = $payout->user;
                $data['cs2']['recentDrops'][] = [
                    'id' => $payout->id,
                    'name' => $payout->name,
                    'price' => (float)$payout->amount,
                    'image' => $payout->image300 ?: $payout->image,
                    'user' => [
                        'id' => $user->id ?? null,
                        'username' => $user->username ?? 'Неизвестный',
                        'avatar' => $user->getAvatar() ?? null,
                    ],
                    'created_at' => $payout->created_at,
                ];
            }

            // Получаем количество разыгранных скинов для каждого типа
            $data['rust']['totalCount'] = (int)UserPayoutSkins::find()
                ->where(['status' => UserPayoutSkins::STATUS_SUCCESS, 'type' => 'rust'])
                ->count();

            $data['cs2']['totalCount'] = (int)UserPayoutSkins::find()
                ->where(['status' => UserPayoutSkins::STATUS_SUCCESS, 'type' => 'cs2'])
                ->count();

            // Сохраняем в кэш на 10 минут (600 секунд)
            $cache->set($cacheKey, $data, 600);
        }

        return $this->successResponse($data);
    }

    /**
     * Получить данные для страницы skindrops
     * 
     * @OA\Get(
     *     path="/v1/skins/skindrops",
     *     operationId="getSkindropsPage",
     *     tags={"Skins"},
     *     summary="Получить данные для страницы skindrops",
     *     description="Публичный метод, возвращает информацию о раздаче скинов, статус пользователя и последние выигрыши",
     *     @OA\Response(response=200, description="Данные для страницы skindrops")
     * )
     */
    public function actionSkindrops()
    {
        if (!Yii::$app->settings->get('section_skindrops')) {
            throw new NotFoundHttpException('Страница не найдена');
        }

        // Кэшируем данные на 10 минут
        $cacheKey = 'skindrops_page_data';
        $cache = Yii::$app->cache;
        $data = $cache->get($cacheKey);

        // Проверяем структуру данных (на случай старых данных в кэше)
        if ($data === false || !isset($data['rust']) || !isset($data['cs2'])) {
            $data = [
                'rust' => [
                    'recentWins' => [],
                ],
                'cs2' => [
                    'recentWins' => [],
                ],
                'prefix' => Yii::$app->settings->get('skindrops_prefix') ?? '',
            ];

            // Получаем последние 20 выигрышей для Rust
            $lastPayoutsRust = UserPayoutSkins::find()
                ->alias('p')
                ->joinWith(['user'])
                ->where(['p.status' => UserPayoutSkins::STATUS_SUCCESS, 'p.type' => 'rust'])
                ->orderBy(['p.id' => SORT_DESC])
                ->limit(20)
                ->all();

            foreach ($lastPayoutsRust as $payout) {
                $user = $payout->user;
                $data['rust']['recentWins'][] = [
                    'id' => $payout->id,
                    'name' => $payout->name,
                    'price' => (float)$payout->amount,
                    'image' => $payout->image300 ?: $payout->image,
                    'user' => [
                        'id' => $user->id ?? null,
                        'username' => $user->username ?? 'Неизвестный',
                        'avatar' => $user->getAvatar() ?? null,
                    ],
                    'created_at' => $payout->created_at,
                ];
            }

            // Получаем последние 20 выигрышей для CS2
            $lastPayoutsCs2 = UserPayoutSkins::find()
                ->alias('p')
                ->joinWith(['user'])
                ->where(['p.status' => UserPayoutSkins::STATUS_SUCCESS, 'p.type' => 'cs2'])
                ->orderBy(['p.id' => SORT_DESC])
                ->limit(20)
                ->all();

            foreach ($lastPayoutsCs2 as $payout) {
                $user = $payout->user;
                $data['cs2']['recentWins'][] = [
                    'id' => $payout->id,
                    'name' => $payout->name,
                    'price' => (float)$payout->amount,
                    'image' => $payout->image300 ?: $payout->image,
                    'user' => [
                        'id' => $user->id ?? null,
                        'username' => $user->username ?? 'Неизвестный',
                        'avatar' => $user->getAvatar() ?? null,
                    ],
                    'created_at' => $payout->created_at,
                ];
            }

            // Сохраняем в кэш на 10 минут (600 секунд)
            $cache->set($cacheKey, $data, 600);
        }

        // Данные пользователя (если авторизован)
        // Пытаемся получить пользователя из JWT токена, если он есть
        $user = $this->getUserFromToken();
        
        // Если пользователь не найден через JWT, проверяем стандартную авторизацию
        if (!$user) {
            $user = Yii::$app->user->identity;
        }
        
        // Логика как в старой версии: проверяем все условия
        $authCompleted = !empty($user);
        $tradeLinkCompleted = false;
        $usernameCompleted = false;
        
        if ($user) {
            $prefix = Yii::$app->settings->get('skindrops_prefix') ?? '';
            
            // Проверка Trade-URL
            if (!empty($user->userProfile->trade_link)) {
                $tradeLinkCompleted = true;
            }
            
            // Проверка префикса в нике (как в старой версии)
            if (!empty($prefix) && strpos(mb_strtolower($user->username), strtolower($prefix)) !== false) {
                $usernameCompleted = true;
            }
            
            $allCompleted = $usernameCompleted && $tradeLinkCompleted && $authCompleted;
            
            $userData = [
                'isAuthenticated' => true,
                'usernameCompleted' => $usernameCompleted,
                'tradeLinkCompleted' => $tradeLinkCompleted,
                'tradeLink' => $user->userProfile->trade_link ?? null,
                'allCompleted' => $allCompleted,
            ];
        }

        if (!$userData) {
            $userData = [
                'isAuthenticated' => false,
                'usernameCompleted' => false,
                'tradeLinkCompleted' => false,
                'tradeLink' => null,
                'allCompleted' => false,
            ];
        }

        return $this->successResponse([
            'rust' => $data['rust'],
            'cs2' => $data['cs2'],
            'prefix' => $data['prefix'],
            'user' => $userData,
        ]);
    }

    /**
     * Получение пользователя из JWT токена (без обязательной авторизации)
     * 
     * @return User|null
     */
    protected function getUserFromToken()
    {
        try {
            $jwtService = Yii::$app->has('jwt') ? Yii::$app->get('jwt') : new JwtService();
            $token = $jwtService->extractTokenFromRequest(Yii::$app->request);
            
            if (empty($token)) {
                return null;
            }
            
            // Валидация токена
            $payload = $jwtService->validateToken($token);
            
            // Проверка blacklist
            $jti = $jwtService->getJti($payload);
            if (!empty($jti)) {
                $cacheKey = 'jwt_blacklist_' . $jti;
                $blacklisted = Yii::$app->cache->get($cacheKey);
                if ($blacklisted !== false) {
                    return null;
                }
            }
            
            // Поиск пользователя
            $userId = $jwtService->getUserId($payload);
            $steamId = $jwtService->getSteamId($payload);
            
            if ($userId) {
                $user = User::findIdentity($userId);
                if ($user) {
                    return $user;
                }
            }
            
            if ($steamId) {
                $user = User::find()->where(['steam_id' => $steamId])->one();
                if ($user) {
                    return $user;
                }
            }
            
            return null;
        } catch (\Exception $e) {
            // Если токен невалидный, просто возвращаем null
            return null;
        }
    }
}

