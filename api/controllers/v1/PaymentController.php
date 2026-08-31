<?php

namespace api\controllers\v1;

use Yii;
use common\helpers\ApiPublicCacheTtl;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\BadRequestHttpException;
use common\models\invoice\Deposit;
use common\models\invoice\PaymentBonuses;
use common\components\payments\PaymentCallbackHandler;
use common\components\payments\PaymentApi;
use common\components\helpers\EmailHelper;
use frontend\forms\market\PaymentForm;
use api\components\jwt\JwtAuthFilter;
use OpenApi\Annotations as OA;

/**
 * Контроллер для работы с платежами
 * 
 * @package api\controllers\v1
 * @OA\Tag(name="Payment")
 */
class PaymentController extends BaseApiController
{
    /**
     * Настройка behaviors
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // JWT авторизация требуется для большинства методов
        $behaviors['authenticator'] = [
            'class' => JwtAuthFilter::class,
            'except' => ['methods', 'callback', 'options'], // methods и callback публичные
        ];

        return $behaviors;
    }

    /**
     * Список доступных методов оплаты
     * 
     * @OA\Get(
     *     path="/v1/payment/methods",
     *     operationId="getPaymentMethods",
     *     tags={"Payment"},
     *     summary="Получить список методов оплаты",
     *     description="Публичный метод, авторизация не требуется",
     *     @OA\Response(response=200, description="Список методов оплаты")
     * )
     */
    public function actionMethods()
    {
        $cacheKey = 'api_payment_methods';
        $cache = Yii::$app->cache;
        $cached = $cache->get($cacheKey);
        if ($cached !== false && is_array($cached)) {
            return $this->successResponse($cached);
        }

        $typeList = Deposit::getTypeList();
        $iconList = Deposit::getIconTypeList();
        $limits = Deposit::getLimits();
        $shortNameList = Deposit::getShortNameList();

        $methods = [];
        foreach ($typeList as $typeId => $name) {
            $limitsForType = $limits[$typeId] ?? null;
            
            // Проверяем настройки для каждого метода
            $isEnabled = false;
            switch ($typeId) {
                case Deposit::TYPE_PAYMENT_CARD_TINKOFF:
                    $isEnabled = Yii::$app->settings->get('tinkoffpay_enabled');
                    break;
                case Deposit::TYPE_PAYMENT_TRC20:
                    $isEnabled = Yii::$app->settings->get('trc20_enabled');
                    break;
                case Deposit::TYPE_PAYMENT_TON:
                    $isEnabled = Yii::$app->settings->get('ton_enabled');
                    break;
                case Deposit::TYPE_PAYMENT_SKINS:
                    $isEnabled = Yii::$app->settings->get('skinpay_enabled');
                    break;
                case Deposit::TYPE_PAYMENT_TELEGRAM:
                    $isEnabled = Yii::$app->settings->get('telegrampay_enabled');
                    break;
                case Deposit::TYPE_PAYMENT_FUNPAY:
                    $isEnabled = Yii::$app->settings->get('funpay_enabled');
                    break;
                default:
                    $isEnabled = true; // По умолчанию включены
            }

            if (!$isEnabled) {
                continue;
            }

            $methods[] = [
                'id' => $typeId,
                'name' => $name,
                'short_name' => $shortNameList[$typeId] ?? null,
                'icon' => $iconList[$typeId] ?? null,
                'limits' => $limitsForType ? [
                    'min' => $limitsForType[0],
                    'max' => $limitsForType[1],
                ] : null,
            ];
        }

        // Бонусы за пополнение (как в frontend/views/user/payment.php)
        $bonuses = PaymentBonuses::find()
            ->orderBy(['amount' => SORT_ASC])
            ->asArray()
            ->all();
        $bonusesList = array_map(function ($row) {
            return [
                'amount' => (int)($row['amount'] ?? 0),
                'bonus' => (int)($row['bonus'] ?? 0),
            ];
        }, $bonuses);

        $response = [
            'methods' => $methods,
            'bonuses' => $bonusesList,
        ];
        $cache->set($cacheKey, $response, ApiPublicCacheTtl::SECONDS);

        return $this->successResponse($response);
    }

    /**
     * Создание платежа
     * 
     * @OA\Post(
     *     path="/v1/payment/create",
     *     operationId="createPayment",
     *     tags={"Payment"},
     *     summary="Создать новый платеж",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"amount", "payment_id"},
     *                 @OA\Property(property="amount", type="integer", example=1000, description="Сумма платежа в рублях"),
     *                 @OA\Property(property="payment_id", type="integer", example=18, description="ID метода оплаты"),
     *                 @OA\Property(property="email", type="string", format="email", example="user@example.com", description="Email (если не указан в профиле)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Платеж создан",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=400, description="Неверные параметры или сумма вне лимитов"),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionCreate()
    {
        $user = $this->getCurrentUser();

        $amount = Yii::$app->request->post('amount');
        $paymentId = Yii::$app->request->post('payment_id');
        $email = Yii::$app->request->post('email');

        // Валидация
        if (empty($amount) || empty($paymentId)) {
            return $this->errorResponse('INVALID_REQUEST', 'Amount and payment_id are required', [], 400);
        }

        $amount = (int)$amount;
        $paymentId = (int)$paymentId;

        // Проверка существования метода оплаты
        $typeList = Deposit::getTypeList();
        if (!isset($typeList[$paymentId])) {
            return $this->errorResponse('INVALID_PAYMENT_METHOD', 'Payment method not found', [], 400);
        }

        // Проверка лимитов
        $limits = Deposit::getLimits();
        if (!empty($limits[$paymentId])) {
            $minLimit = $limits[$paymentId][0];
            $maxLimit = $limits[$paymentId][1];
            if ($amount < $minLimit) {
                return $this->errorResponse('AMOUNT_TOO_LOW', "Minimum amount is {$minLimit} RUB", [
                    'min' => $minLimit,
                ], 400);
            }
            if ($amount > $maxLimit) {
                return $this->errorResponse('AMOUNT_TOO_HIGH', "Maximum amount is {$maxLimit} RUB", [
                    'max' => $maxLimit,
                ], 400);
            }
        }

        // Проверка email (если требуется и не указан)
        if (!$user->is_email) {
            if (empty($email)) {
                return $this->errorResponse('EMAIL_REQUIRED', 'Email is required', [], 400);
            }
            if (!EmailHelper::isValid($email)) {
                return $this->errorResponse('INVALID_EMAIL', 'Invalid email address', [], 400);
            }
            $user->email = $email;
            $user->is_email = true;
            $user->save(false);
        } else {
            $email = $user->email;
        }

        try {
            // Создаем депозит
            $deposit = Deposit::createOperation($user->id, $amount, $paymentId);

            // Получаем API провайдера и создаем платеж
            $paymentApi = PaymentApi::getInstance($paymentId);
            $response = $paymentApi->create($deposit);

            if (empty($response)) {
                $deposit->markCanceled();
                return $this->errorResponse('PAYMENT_CREATION_FAILED', 'Failed to create payment', [], 500);
            }

            // Формируем ответ
            $result = [
                'deposit_id' => $deposit->id,
                'status' => Deposit::STATUS_WAIT_CONFIRM,
            ];

            if (!empty($response['paymentURL'])) {
                $result['payment_url'] = $response['paymentURL'];
            }

            if (!empty($response['template'])) {
                $result['template'] = $response['template'];
                $result['template_data'] = $response;
            }

            return $this->successResponse($result);

        } catch (\Exception $e) {
            Yii::error('Payment creation error: ' . $e->getMessage());
            
            if (isset($deposit)) {
                $deposit->markCanceled();
            }

            $statusCode = $e->getCode() === 414 ? 414 : 500;
            return $this->errorResponse('PAYMENT_ERROR', $e->getMessage(), [], $statusCode);
        }
    }

    /**
     * Получение статуса платежа
     * 
     * @OA\Get(
     *     path="/v1/payment/status/{id}",
     *     operationId="getPaymentStatus",
     *     tags={"Payment"},
     *     summary="Получить статус платежа",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID платежа",
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Статус платежа",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=404, description="Платеж не найден")
     * )
     */
    public function actionStatus($id)
    {
        $user = $this->getCurrentUser();

        $deposit = Deposit::findOne($id);
        if (!$deposit) {
            throw new NotFoundHttpException('Payment not found');
        }

        // Проверяем, что платеж принадлежит текущему пользователю
        if ($deposit->user_id != $user->id) {
            throw new ForbiddenHttpException('Access denied');
        }

        return $this->successResponse([
            'deposit' => [
                'id' => $deposit->id,
                'amount' => $deposit->amount,
                'amount_exchange' => $deposit->amount_exchange,
                'payment_type' => $deposit->payment_type,
                'payment_type_name' => Deposit::getTypeList()[$deposit->payment_type] ?? null,
                'status' => $deposit->status,
                'status_name' => Deposit::getStatusList()[$deposit->status] ?? null,
                'payment_id' => $deposit->payment_id,
                'created_at' => $deposit->created_at,
            ],
        ]);
    }

    /**
     * Обработка callback от платежной системы
     * POST /api/v1/payment/callback/{payment}
     * 
     * @param string $payment Название платежной системы (tinkoff, tome, anypay)
     */
    /**
     * Callback от платежной системы
     * 
     * @OA\Post(
     *     path="/v1/payment/callback/{payment}",
     *     operationId="paymentCallback",
     *     tags={"Payment"},
     *     summary="Callback от платежной системы",
     *     description="Публичный метод для обработки callback от платежных систем. Авторизация не требуется.",
     *     @OA\Parameter(
     *         name="payment",
     *         in="path",
     *         required=true,
     *         description="Идентификатор платежной системы",
     *         @OA\Schema(type="string", example="interkassa")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Callback обработан",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=400, description="Ошибка обработки callback")
     * )
     */
    public function actionCallback($payment)
    {
        return PaymentCallbackHandler::handle($payment);
    }

}
