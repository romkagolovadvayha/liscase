<?php

namespace common\components\tasks_v2;

use common\models\tasks_v2\TaskV2;
use common\models\user\User;
use Yii;

/**
 * Проверка привязки пользователя к партнёрскому аккаунту CobaltLab (CHECK_AFFILIATES_V2 API).
 * Поддерживает два типа заданий:
 * - Регистрация на CobaltLab (isMyRef — регистрация по реферальной ссылке)
 * - Первый депозит на CobaltLab (isFD — первый депозит совершён)
 */
class CobaltLabAffiliateChecker implements TaskCheckerInterface
{
    private const API_URL = 'https://cobaltlab.tech/api/CHECK_AFFILIATES_V2';

    /**
     * {@inheritdoc}
     */
    public function check(TaskV2 $task, User $user): CheckResult
    {
        $params = is_array($task->check_params) ? $task->check_params : (json_decode($task->check_params, true) ?? []);
        $token = trim($params['token'] ?? '');

        if ($token === '') {
            Yii::warning('CobaltLabAffiliateChecker: token not configured for task id=' . $task->id, __METHOD__);
            return CheckResult::failure(
                Yii::t('common', 'Проверка временно недоступна. Обратитесь в поддержку.')
            );
        }

        $steamId = $user->steam_id ?? '';
        if ($steamId === '') {
            return CheckResult::failure(
                Yii::t('common', 'Не найден Steam-аккаунт. Войдите через Steam.')
            );
        }

        $response = $this->callApi($token, [$steamId]);
        if ($response === null) {
            return CheckResult::failure(
                Yii::t('common', 'Не удалось проверить статус. Попробуйте позже.')
            );
        }

        if (($response['status'] ?? '') !== 'success' || empty($response['data']) || !is_array($response['data'])) {
            $message = $response['message'] ?? 'unknown';
            Yii::warning('CobaltLabAffiliateChecker API error: ' . $message . ' for task id=' . $task->id, __METHOD__);
            return CheckResult::failure(
                Yii::t('common', 'Не удалось проверить статус. Попробуйте позже.')
            );
        }

        $item = $response['data'][0] ?? [];
        $affiliated = !empty($item['affiliated']);

        if ($task->check_type === TaskV2::CHECK_TYPE_COBALTLAB_REGISTRATION) {
            $isMyRef = $affiliated && !empty($item['isMyRef']);
            if ($isMyRef) {
                return CheckResult::success(
                    Yii::t('common', 'Регистрация на CobaltLab по вашей ссылке подтверждена!')
                );
            }
            return CheckResult::failure(
                Yii::t('common', 'Зарегистрируйтесь на CobaltLab по реферальной ссылке из описания задания.')
            );
        }

        if ($task->check_type === TaskV2::CHECK_TYPE_COBALTLAB_FIRST_DEPOSIT) {
            $isFD = $affiliated && !empty($item['isFD']);
            if ($isFD) {
                return CheckResult::success(
                    Yii::t('common', 'Первый депозит на CobaltLab подтверждён!')
                );
            }
            if (!$affiliated) {
                return CheckResult::failure(
                    Yii::t('common', 'Сначала зарегистрируйтесь на CobaltLab по реферальной ссылке, затем совершите первый депозит.')
                );
            }
            return CheckResult::failure(
                Yii::t('common', 'Совершите первый депозит на CobaltLab.')
            );
        }

        return CheckResult::failure(Yii::t('common', 'Неизвестный тип проверки.'));
    }

    /**
     * Вызов API CHECK_AFFILIATES_V2
     * @param string $token Affiliate-токен
     * @param string[] $steamIds Массив SteamID64
     * @return array|null Декодированный JSON или null при ошибке
     */
    private function callApi(string $token, array $steamIds): ?array
    {
        $steamIds = array_values(array_unique(array_map('strval', $steamIds)));
        if (empty($steamIds)) {
            return null;
        }

        $body = json_encode([
            'token' => $token,
            'steamIDS' => $steamIds,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($body),
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError !== '') {
            Yii::warning('CobaltLabAffiliateChecker cURL error: ' . $curlError, __METHOD__);
            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            Yii::warning('CobaltLabAffiliateChecker invalid JSON, HTTP ' . $httpCode, __METHOD__);
            return null;
        }

        return $decoded;
    }
}
