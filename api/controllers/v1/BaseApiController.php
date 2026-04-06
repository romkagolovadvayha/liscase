<?php

namespace api\controllers\v1;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\Cors;
use yii\filters\ContentNegotiator;
use yii\web\BadRequestHttpException;
use yii\web\UnauthorizedHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\ServerErrorHttpException;

/**
 * Базовый контроллер для всех API контроллеров v1
 *
 * Предоставляет:
 * - Единый формат ответов
 * - CORS настройки
 * - JSON формат ответа
 * - Обработку ошибок
 */
abstract class BaseApiController extends Controller
{
    /**
     * @var bool Отключить CSRF валидацию для API
     */
    public $enableCsrfValidation = false;

    /**
     * Настройка behaviors
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // CORS настройки должны быть ПЕРВЫМИ, чтобы обработать OPTIONS запросы
        $allowedOrigins = $this->getAllowedOrigins();
        $behaviors = [
            'cors' => [
                'class' => Cors::class,
                'cors' => [
                    'Origin' => $allowedOrigins,
                    'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
                    'Access-Control-Request-Headers' => ['Authorization', 'Content-Type', 'X-Requested-With', 'Accept', 'Accept-Language'],
                    'Access-Control-Allow-Credentials' => true,
                    'Access-Control-Max-Age' => 86400,
                ],
            ],
        ] + $behaviors;

        // Content Negotiator - форсируем JSON формат
        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::class,
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
            ],
        ];

        return $behaviors;
    }

    /**
     * Получение списка разрешенных origins для CORS
     *
     * @return array
     */
    protected function getAllowedOrigins()
    {
        // Получаем список из параметров, если указан
        $paramsOrigins = Yii::$app->params['allowedOrigins'] ?? [];

        // Всегда добавляем localhost для разработки
        // ВАЖНО: Не используем '*' когда Access-Control-Allow-Credentials = true
        $devOrigins = [
            'http://localhost:3000',
            'http://localhost:3001',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:3001',
            'http://localhost',
            'http://127.0.0.1',
            'http://45.129.128.211:3000',
            'http://prostoj.local',
            'http://en.prostoj.local',
            'http://prostoj.loc',
            'http://en.prostoj.loc',
            'https://prostoj.store',
            'https://www.prostoj.store',
            'https://new.prostoj.store',
            'https://en.prostoj.store',
            'https://moscow77.store',
            'https://www.moscow77.store',
            'https://new.moscow77.store',
            'https://en.moscow77.store',
            'http://147.45.235.170:3001',
        ];

        // Объединяем все origins
        $allOrigins = array_unique(array_merge($devOrigins, $paramsOrigins));

        return $allOrigins;
    }

    /**
     * Выполняется перед каждым action
     */
    public function beforeAction($action)
    {
        // Устанавливаем формат ответа на JSON
        Yii::$app->response->format = Response::FORMAT_JSON;

        return parent::beforeAction($action);
    }

    /**
     * Успешный ответ API
     *
     * @param mixed $data Данные для ответа
     * @param array $meta Метаданные (пагинация, фильтры и т.д.)
     * @param int $statusCode HTTP статус код
     * @return array
     */
    protected function successResponse($data = null, $meta = [], $statusCode = 200)
    {
        Yii::$app->response->statusCode = $statusCode;

        $response = [
            'success' => true,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return $response;
    }

    /**
     * Ответ с ошибкой
     *
     * @param string $code Код ошибки
     * @param string $message Сообщение об ошибке
     * @param array $details Детали ошибки
     * @param int $statusCode HTTP статус код
     * @return array
     */
    protected function errorResponse($code, $message, $details = [], $statusCode = 400)
    {
        Yii::$app->response->statusCode = $statusCode;

        return [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ];
    }

    /**
     * Ответ с ошибкой валидации
     *
     * @param \yii\base\Model $model Модель с ошибками валидации
     * @return array
     */
    protected function validationErrorResponse($model)
    {
        return $this->errorResponse(
            'VALIDATION_ERROR',
            'Validation failed',
            $model->getFirstErrors(),
            422
        );
    }

    /**
     * Обработка исключений
     *
     * @param \Exception $exception
     * @return array
     */
    protected function handleException($exception)
    {
        // Логируем исключение
        Yii::error($exception->getMessage() . "\n" . $exception->getTraceAsString(), 'api');

        // Определяем статус код и код ошибки
        if ($exception instanceof UnauthorizedHttpException) {
            return $this->errorResponse('UNAUTHORIZED', $exception->getMessage(), [], 401);
        }

        if ($exception instanceof ForbiddenHttpException) {
            return $this->errorResponse('FORBIDDEN', $exception->getMessage(), [], 403);
        }

        if ($exception instanceof NotFoundHttpException) {
            return $this->errorResponse('NOT_FOUND', $exception->getMessage(), [], 404);
        }

        if ($exception instanceof BadRequestHttpException) {
            return $this->errorResponse('BAD_REQUEST', $exception->getMessage(), [], 400);
        }

        if ($exception instanceof ServerErrorHttpException) {
            return $this->errorResponse('SERVER_ERROR', $exception->getMessage(), [], 500);
        }

        // Общая ошибка
        $message = YII_DEBUG ? $exception->getMessage() : 'Internal server error';
        return $this->errorResponse('INTERNAL_ERROR', $message, [], 500);
    }

    /**
     * Переопределение обработки ошибок
     */
    public function afterAction($action, $result)
    {
        // Если результат - исключение, обрабатываем его
        if ($result instanceof \Exception) {
            return $this->handleException($result);
        }

        return parent::afterAction($action, $result);
    }

    /**
     * Проверка авторизации пользователя
     *
     * @throws UnauthorizedHttpException
     */
    protected function requireAuth()
    {
        if (Yii::$app->user->isGuest) {
            throw new UnauthorizedHttpException('Authentication required');
        }
    }

    /**
     * Получение текущего авторизованного пользователя
     *
     * @return \common\models\user\User
     * @throws UnauthorizedHttpException
     */
    protected function getCurrentUser()
    {
        $this->requireAuth();
        return Yii::$app->user->identity;
    }

    /**
     * Проверка прав доступа
     *
     * @param string $permission Разрешение
     * @param array $params Параметры для проверки
     * @throws ForbiddenHttpException
     */
    protected function requirePermission($permission, $params = [])
    {
        $this->requireAuth();

        if (!Yii::$app->user->can($permission, $params)) {
            throw new ForbiddenHttpException('You do not have permission to perform this action');
        }
    }
}

