<?php

namespace common\components\bansystem;

use common\components\rustadmin\dto\RustAdminPlayerResponse;
use common\models\bans\Bans;
use common\models\servers\Servers;
use Yii;
use yii\base\Component;

/**
 * Единственная точка интеграции liscase с Rust Admin.
 *
 * Профиль игрока, локальная копия RCC и команды приходят одним запросом.
 * Бан-лист читается cursor-страницами и сверяется с локальной БД пакетно.
 */
class RustAdmin extends Component
{
    private const DEFAULT_BASE_URL = 'https://api.rust-admin.ru';
    private const BAN_PAGE_SIZE = 250;
    private const MAX_BAN_PAGES = 10000;

    /** @var array<string, array> */
    private array $profileCache = [];

    public int $connectTimeout = 3;
    public int $timeout = 12;

    public static function normalizeSteamId($steamId): ?string
    {
        if ($steamId === null || $steamId === '') {
            return null;
        }
        if (is_float($steamId) || (is_string($steamId) && stripos($steamId, 'E') !== false)) {
            $steamId = sprintf('%.0f', (float)$steamId);
        }
        $value = preg_replace('/\s+/', '', trim((string)$steamId));
        if (preg_match('#steamcommunity\.com/profiles/(\d{17})#i', $value, $matches)) {
            $value = $matches[1];
        }

        return preg_match('/^7656119\d{10}$/', $value) ? $value : null;
    }

    public function player(string $steamId): RustAdminPlayerResponse
    {
        $profile = $this->profile($steamId);

        return RustAdminPlayerResponse::fromArray($profile);
    }

    public function getTrustInfo($steamId): array
    {
        $profile = $this->profile((string)$steamId);

        return !empty($profile['trust']) && is_array($profile['trust'])
            ? $profile['trust']
            : [];
    }

    public function createBan(string $steamId, string $reason, array $options = []): array
    {
        $steamId = self::normalizeSteamId($steamId);
        $reason = trim($reason);
        if ($steamId === null || $reason === '') {
            return $this->failure('invalid_params', 'steamId and reason are required');
        }

        $servers = $this->resolveBanServers($options);
        if ($servers === []) {
            return $this->failure('server_not_mapped', 'Rust Admin server is not configured');
        }

        $payload = [
            'steamId' => $steamId,
            'reason' => $reason,
            'serverIds' => array_values(array_unique(array_filter(array_column($servers, 'rust_admin_id')))),
            'servers' => array_map(static function (array $server): array {
                return [
                    'ip' => (string)$server['ip'],
                    'gamePort' => (int)$server['port'],
                ];
            }, $servers),
            'targetName' => $options['target_name'] ?? null,
            'targetIp' => $options['target_ip'] ?? null,
            'banIp' => !empty($options['ban_ip']),
            'expiresAt' => $options['expires_at'] ?? null,
            'comment' => $options['comment'] ?? 'Автобан liscase',
        ];

        try {
            $response = $this->request('POST', '/api/services/private/bans', [], $payload);

            return ['success' => true, 'data' => $response];
        } catch (\Throwable $throwable) {
            Yii::error('Rust Admin createBan failed: ' . $throwable->getMessage(), __METHOD__);

            return $this->failure('request_failed', $throwable->getMessage());
        }
    }

    public function banList(): array
    {
        if ($this->apiKey() === '') {
            return [];
        }

        $serverMaps = $this->serverMaps();
        if ($serverMaps['by_id'] === [] && $serverMaps['by_address'] === []) {
            return [];
        }

        $result = [];
        $cursor = null;
        for ($page = 0; $page < self::MAX_BAN_PAGES; $page++) {
            $query = ['limit' => self::BAN_PAGE_SIZE];
            if ($cursor !== null) {
                $query['cursor'] = $cursor;
            }
            $response = $this->request('GET', '/api/services/private/bans', $query);
            $rows = !empty($response['bans']) && is_array($response['bans'])
                ? $response['bans']
                : [];
            if ($rows === []) {
                break;
            }

            $candidates = $this->mapBanRows($rows, $serverMaps);
            if ($candidates !== []) {
                $result = array_merge($result, $this->filterExistingBans($candidates));
            }

            $cursor = isset($response['nextCursor']) && is_string($response['nextCursor'])
                ? $response['nextCursor']
                : null;
            if ($cursor === null || $cursor === '') {
                break;
            }
        }

        return $result;
    }

    private function profile(string $steamId): array
    {
        $steamId = self::normalizeSteamId($steamId);
        if ($steamId === null || $this->apiKey() === '') {
            return [];
        }
        if (!array_key_exists($steamId, $this->profileCache)) {
            try {
                $this->profileCache[$steamId] = $this->request(
                    'GET',
                    '/api/services/private/player/' . rawurlencode($steamId)
                );
            } catch (\Throwable $throwable) {
                Yii::error('Rust Admin player request failed: ' . $throwable->getMessage(), __METHOD__);
                $this->profileCache[$steamId] = [];
            }
        }

        return $this->profileCache[$steamId];
    }

    private function request(string $method, string $path, array $query = [], ?array $payload = null): array
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            throw new \RuntimeException('Rust Admin private API key is not configured');
        }
        $url = $this->baseUrl() . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $handle = curl_init($url);
        $headers = [
            'Accept: application/json',
            'X-Api-Key: ' . $apiKey,
        ];
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($handle, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        if ($method === 'POST') {
            $body = json_encode($payload ?: [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($body === false) {
                curl_close($handle);
                throw new \RuntimeException('Cannot encode Rust Admin request');
            }
            $headers[] = 'Content-Type: application/json';
            curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($handle, CURLOPT_POST, true);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if ($raw === false) {
            throw new \RuntimeException('Rust Admin network error: ' . $error);
        }

        $decoded = $raw !== '' ? json_decode($raw, true) : [];
        if ($status < 200 || $status >= 300) {
            $message = is_array($decoded) && !empty($decoded['error'])
                ? (string)$decoded['error']
                : 'HTTP ' . $status;
            throw new \RuntimeException('Rust Admin API: ' . $message);
        }
        if (!is_array($decoded)) {
            throw new \RuntimeException('Rust Admin returned invalid JSON');
        }

        return $decoded;
    }

    private function baseUrl(): string
    {
        $configured = trim((string)Yii::$app->settings->get('rustAdmin_baseUrl'));
        $baseUrl = rtrim($configured !== '' ? $configured : self::DEFAULT_BASE_URL, '/');
        if (!preg_match('#^https?://#i', $baseUrl)) {
            throw new \RuntimeException('Invalid Rust Admin base URL');
        }

        return $baseUrl;
    }

    private function apiKey(): string
    {
        return trim((string)Yii::$app->settings->get('rustAdmin_privateApiKey'));
    }

    private function serverMaps(): array
    {
        $rows = Servers::find()
            ->select(['id', 'rust_admin_id', 'ip', 'port'])
            ->andWhere(['in', 'status', [
                Servers::STATUS_WAIT,
                Servers::STATUS_NOACTIVE,
                Servers::STATUS_ACTIVE,
            ]])
            ->asArray()
            ->all();
        $byId = [];
        $byAddress = [];
        foreach ($rows as $row) {
            if (!empty($row['rust_admin_id'])) {
                $byId[(string)$row['rust_admin_id']] = (int)$row['id'];
            }
            $byAddress[$this->addressKey((string)$row['ip'], (int)$row['port'])] = (int)$row['id'];
        }

        return ['by_id' => $byId, 'by_address' => $byAddress];
    }

    private function mapBanRows(array $rows, array $maps): array
    {
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['steamId']) || empty($row['server'])) {
                continue;
            }
            $remoteServer = $row['server'];
            $serverId = $maps['by_id'][(string)($row['serverId'] ?? '')] ?? null;
            if ($serverId === null && is_array($remoteServer)) {
                $key = $this->addressKey(
                    (string)($remoteServer['ip'] ?? ''),
                    (int)($remoteServer['gamePort'] ?? 0)
                );
                $serverId = $maps['by_address'][$key] ?? null;
            }
            if ($serverId === null) {
                continue;
            }
            $result[] = [
                'username' => !empty($row['username']) ? (string)$row['username'] : 'Unknown',
                'steam_id' => (string)$row['steamId'],
                'reason' => (string)($row['reason'] ?? ''),
                'ip' => $row['ip'] ?? null,
                'date' => $this->dbDate($row['bannedAt'] ?? null),
                'expireDate' => $this->dbDate($row['expiresAt'] ?? null),
                'server_id' => $serverId,
                'project' => 'Rust Admin',
            ];
        }

        return $result;
    }

    private function filterExistingBans(array $candidates): array
    {
        $steamIds = array_values(array_unique(array_column($candidates, 'steam_id')));
        $serverIds = array_values(array_unique(array_column($candidates, 'server_id')));
        $existing = Bans::find()
            ->select(['id', 'steam_id', 'server_id', 'unbanned_at'])
            ->andWhere(['in', 'steam_id', $steamIds])
            ->andWhere(['in', 'server_id', $serverIds])
            ->all();
        $byKey = [];
        foreach ($existing as $ban) {
            $byKey[$ban->steam_id . ':' . $ban->server_id] = $ban;
        }

        $newRows = [];
        foreach ($candidates as $candidate) {
            $key = $candidate['steam_id'] . ':' . $candidate['server_id'];
            if (isset($byKey[$key])) {
                $model = $byKey[$key];
                if ($model->unbanned_at !== $candidate['expireDate']) {
                    $model->unbanned_at = $candidate['expireDate'];
                    $model->save(false, ['unbanned_at']);
                }
                continue;
            }
            $newRows[] = $candidate;
        }

        return $newRows;
    }

    private function resolveBanServers(array $options): array
    {
        $localIds = [];
        if (!empty($options['server_id'])) {
            $localIds[] = (int)$options['server_id'];
        }
        if (!empty($options['local_server_ids']) && is_array($options['local_server_ids'])) {
            $localIds = array_merge($localIds, array_map('intval', $options['local_server_ids']));
        }
        $localIds = array_values(array_unique(array_filter($localIds)));
        if ($localIds === []) {
            return [];
        }

        return Servers::find()
            ->select(['id', 'rust_admin_id', 'ip', 'port'])
            ->andWhere(['in', 'id', $localIds])
            ->asArray()
            ->all();
    }

    private function addressKey(string $ip, int $port): string
    {
        return strtolower(trim($ip)) . ':' . $port;
    }

    private function dbDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            return (new \DateTimeImmutable((string)$value))
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format('Y-m-d H:i:s');
        } catch (\Throwable $throwable) {
            return null;
        }
    }

    private function failure(string $error, string $message): array
    {
        return ['success' => false, 'error' => $error, 'message' => $message];
    }
}
