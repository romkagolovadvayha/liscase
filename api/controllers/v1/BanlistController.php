<?php

namespace api\controllers\v1;

use common\models\bans\Bans;
use common\models\servers\Servers;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;

/**
 * @OA\Tag(
 *   name="Banlist",
 *   description="API для получения списка забаненных пользователей"
 * )
 */
class BanlistController extends BaseApiController
{
    /**
     * @OA\Get(
     *   path="/v1/banlist",
     *   summary="Получить список забаненных пользователей",
     *   tags={"Banlist"},
     *   @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="Номер страницы",
     *     required=false,
     *     @OA\Schema(type="integer", default=1)
     *   ),
     *   @OA\Parameter(
     *     name="steam_id",
     *     in="query",
     *     description="Поиск по Steam ID или нику",
     *     required=false,
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\Parameter(
     *     name="reason",
     *     in="query",
     *     description="Поиск по причине бана",
     *     required=false,
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\Parameter(
     *     name="server_id",
     *     in="query",
     *     description="Фильтр по ID сервера",
     *     required=false,
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Parameter(
     *     name="sort",
     *     in="query",
     *     description="Поле для сортировки (username, server, first_seen, banned_at, reason)",
     *     required=false,
     *     @OA\Schema(type="string", default="banned_at")
     *   ),
     *   @OA\Parameter(
     *     name="order",
     *     in="query",
     *     description="Порядок сортировки (asc, desc)",
     *     required=false,
     *     @OA\Schema(type="string", default="desc")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Список забаненных пользователей",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *           @OA\Property(property="id", type="integer"),
     *           @OA\Property(property="username", type="string"),
     *           @OA\Property(property="steam_id", type="string"),
     *           @OA\Property(property="avatar", type="string"),
     *           @OA\Property(property="reason", type="string"),
     *           @OA\Property(property="banned_at", type="string", format="date-time"),
     *           @OA\Property(property="unbanned_at", type="string", format="date-time", nullable=true),
     *           @OA\Property(property="server_id", type="integer", nullable=true),
     *           @OA\Property(property="server_name", type="string"),
     *           @OA\Property(property="server_tag", type="string", nullable=true),
     *           @OA\Property(property="first_seen", type="string", format="date-time", nullable=true)
     *         )
     *       ),
     *       @OA\Property(
     *         property="pagination",
     *         type="object",
     *         @OA\Property(property="page", type="integer"),
     *         @OA\Property(property="totalPages", type="integer"),
     *         @OA\Property(property="total", type="integer"),
     *         @OA\Property(property="pageSize", type="integer")
     *       )
     *     )
     *   )
     * )
     */
    public function actionIndex()
    {
        try {
            $request = Yii::$app->request;
            
            $query = Bans::find()
                ->with(['user', 'server'])
                ->andWhere([
                    'OR',
                    ['>=', 'unbanned_at', date('Y-m-d H:i:s')],
                    ['IS', 'unbanned_at', null]
                ]);

            // Фильтр по Steam ID или нику
            $steamId = $request->get('steam_id');
            if (!empty($steamId)) {
                $query->andWhere([
                    'OR',
                    ['LIKE', 'username', $steamId],
                    ['LIKE', 'steam_id', $steamId]
                ]);
            }

            // Фильтр по причине
            $reason = $request->get('reason');
            if (!empty($reason)) {
                $query->andWhere(['LIKE', 'reason', $reason]);
            }

            // Фильтр по серверу
            $serverId = $request->get('server_id');
            if (!empty($serverId)) {
                $query->andWhere(['server_id' => (int)$serverId]);
            }

            // Сортировка
            $sortField = $request->get('sort', 'banned_at');
            $sortOrder = $request->get('order', 'desc') === 'asc' ? SORT_ASC : SORT_DESC;
            
            // Обработка сортировки
            if ($sortField === 'server') {
                $query->joinWith('server');
                $query->orderBy([Servers::tableName() . '.monitoring_name' => $sortOrder]);
            } elseif ($sortField === 'first_seen') {
                // first_seen - это created_at пользователя, нужно join с user
                $query->joinWith('user');
                $query->orderBy([\common\models\user\User::tableName() . '.created_at' => $sortOrder]);
            } else {
                $allowedSortFields = ['username', 'banned_at', 'reason'];
                if (!in_array($sortField, $allowedSortFields)) {
                    $sortField = 'banned_at';
                }
                $query->orderBy([Bans::tableName() . '.' . $sortField => $sortOrder]);
            }

            // Пагинация
            $pageSize = 20;
            $page = (int)$request->get('page', 1);
            $offset = ($page - 1) * $pageSize;
            
            $total = (int)$query->count();
            $totalPages = (int)ceil($total / $pageSize);
            
            $bans = $query->offset($offset)->limit($pageSize)->all();
            
            $data = [];
            foreach ($bans as $ban) {
                $user = $ban->user;
                $server = $ban->server;
                
                $data[] = [
                    'id' => $ban->id,
                    'username' => $ban->username ?: ($user ? $user->username : ''),
                    'steam_id' => $ban->steam_id,
                    'avatar' => $user ? $user->getAvatar() : '',
                    'reason' => $ban->reason ?: '',
                    'banned_at' => $ban->banned_at,
                    'unbanned_at' => $ban->unbanned_at,
                    'server_id' => $ban->server_id,
                    'server_name' => $server ? $server->monitoring_name : 'Все сервера',
                    'server_tag' => $server ? $server->tag : null,
                    'first_seen' => $user ? $user->created_at : null,
                ];
            }

            $response = $this->successResponse($data);
            $response['pagination'] = [
                'page' => $page,
                'totalPages' => $totalPages,
                'total' => $total,
                'pageSize' => $pageSize,
            ];
            return $response;
        } catch (\Exception $e) {
            Yii::error('Banlist API error: ' . $e->getMessage(), 'api');
            return $this->errorResponse('INTERNAL_ERROR', 'Ошибка при получении списка банов', [], 500);
        }
    }
}

