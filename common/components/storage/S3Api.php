<?php

namespace common\components\storage;

use GuzzleHttp\Promise\Utils as PromiseUtils;
use Yii;
use Aws\S3\S3Client;
use Aws\Credentials\Credentials;

class S3Api
{
    public $baseUrl;
    public $accessKey;
    public $secretAccessKey;
    public $swift;
    public $swiftSecretAccessKey;
    public $uid;
    public $region;
    public $publicUrl;


    public function createPresignedRequest($key)
    {
        // Получаем настройки напрямую из Settings
        $baseUrl = Yii::$app->settings->get('s3_baseUrl') ?: 'https://s3.timeweb.cloud';
        $accessKey = Yii::$app->settings->get('s3_accessKey') ?: '';
        $secretAccessKey = Yii::$app->settings->get('s3_secretAccessKey') ?: '';
        $region = Yii::$app->settings->get('s3_region') ?: 'ru-1';
        $uid = Yii::$app->settings->get('s3_uid') ?: '';
        
        putenv("AWS_SUPPRESS_PHP_DEPRECATION_WARNING=true");
        $credentials = new Credentials($accessKey, $secretAccessKey);

        $s3 = new S3Client([
                               'version' => '2006-03-01',
                               'region' => $region,
                               'endpoint' => $baseUrl,
                               'use_path_style_endpoint' => true,
                               'credentials' => $credentials,
                           ]);

//        print_r($s3->getBucketAccelerateConfiguration([
//                                                          'Bucket' => $this->uid,
//                                                          'Key'    => $key,
//                                                      ]));
//        $s3->putBucketAccelerateConfiguration([
//            'Bucket' => $this->uid,
//            'Key'    => $key,
//            'CORSRules' => [
//                'AllowedHeaders' => ['*'],
//                'AllowedMethods' => ["GET", "HEAD", "PUT"],
//                'AllowedOrigins' => ['*'],
//            ]
//        ]);

        // Генерация подписанного URL для загрузки
        $command = $s3->getCommand('PutObject', [
            'Bucket' => $uid,
            'Key'    => $key,
        ]);

        // Генерация URL с подписанием на 15 минут
        return (string) $s3->createPresignedRequest($command, '+15 minutes')->getUri();
    }

    /** Кэш по умолчанию для загружаемых файлов: 30 дней */
    const DEFAULT_CACHE_MAX_AGE = 2592000; // 30 * 24 * 60 * 60

    /**
     * @param $body
     *
     * @return mixed
     */
    public function uploadFile($fileName, $body)
    {
        // Получаем настройки напрямую из Settings
        $baseUrl = Yii::$app->settings->get('s3_baseUrl') ?: 'https://s3.timeweb.cloud';
        $accessKey = Yii::$app->settings->get('s3_accessKey') ?: '';
        $secretAccessKey = Yii::$app->settings->get('s3_secretAccessKey') ?: '';
        $region = Yii::$app->settings->get('s3_region') ?: 'ru-1';
        $uid = Yii::$app->settings->get('s3_uid') ?: '';
        
        putenv("AWS_SUPPRESS_PHP_DEPRECATION_WARNING=true");
        $credentials = new Credentials($accessKey, $secretAccessKey);

        $s3 = new S3Client([
                               'version' => '2006-03-01',
                               'region' => $region,
                               'endpoint' => $baseUrl,
                               'use_path_style_endpoint' => true,
                               'credentials' => $credentials,
                           ]);

        $createMultipartUpload = $s3->createMultipartUpload([
                                                                'Bucket' => $uid,
                                                                'Key' => $fileName,
                                                                'CacheControl' => 'public, max-age=' . self::DEFAULT_CACHE_MAX_AGE,
                                                            ]);

        $uploadId = $createMultipartUpload->get('UploadId');

        $partKey = 1;
        $uploadPart = $s3->uploadPart([
                                          'Bucket' => $uid,
                                          'Key' => $fileName,
                                          'Body' => $body,
                                          'PartNumber' => $partKey,
                                          'UploadId' => $uploadId,
        ]);

        $response = $s3->completeMultipartUpload([
                                        'Bucket' => $uid,
                                        'Key' => $fileName,
                                         'MultipartUpload' => [
                                             'Parts' => [
                                                 [
                                                     'ETag' => $uploadPart->get('ETag'),
                                                     'PartNumber' => $partKey
                                                 ]
                                             ]
                                         ],
                                         'UploadId' => $uploadId
                                     ]);

        return $response->get('Key');
    }

    /**
     * Удаляет файл из S3 хранилища
     * 
     * @param string $fileName Путь к файлу в S3
     * @return bool true в случае успеха, false в случае ошибки
     */
    public function deleteFile($fileName)
    {
        try {
            // Получаем настройки напрямую из Settings
            $baseUrl = Yii::$app->settings->get('s3_baseUrl') ?: 'https://s3.timeweb.cloud';
            $accessKey = Yii::$app->settings->get('s3_accessKey') ?: '';
            $secretAccessKey = Yii::$app->settings->get('s3_secretAccessKey') ?: '';
            $region = Yii::$app->settings->get('s3_region') ?: 'ru-1';
            $uid = Yii::$app->settings->get('s3_uid') ?: '';
            
            putenv("AWS_SUPPRESS_PHP_DEPRECATION_WARNING=true");
            $credentials = new Credentials($accessKey, $secretAccessKey);

            $s3 = new S3Client([
                                   'version' => '2006-03-01',
                                   'region' => $region,
                                   'endpoint' => $baseUrl,
                                   'use_path_style_endpoint' => true,
                                   'credentials' => $credentials,
                               ]);

            $s3->deleteObject([
                'Bucket' => $uid,
                'Key' => $fileName,
            ]);

            return true;
        } catch (\Exception $e) {
            Yii::error('Error deleting file from S3: ' . $e->getMessage() . ', file: ' . $fileName, __METHOD__);
            return false;
        }
    }

    /**
     * Загружает файл в S3 хранилище (простая загрузка через putObject)
     * 
     * @param string $fileName Путь к файлу в S3 (ключ)
     * @param string|resource $body Содержимое файла или путь к файлу
     * @param string|null $contentType MIME-тип файла
     * @return string|false Ключ файла в S3 или false в случае ошибки
     */
    public function putFile($fileName, $body, $contentType = null)
    {
        try {
            // Получаем настройки напрямую из Settings
            $baseUrl = Yii::$app->settings->get('s3_baseUrl') ?: 'https://s3.timeweb.cloud';
            $accessKey = Yii::$app->settings->get('s3_accessKey') ?: '';
            $secretAccessKey = Yii::$app->settings->get('s3_secretAccessKey') ?: '';
            $region = Yii::$app->settings->get('s3_region') ?: 'ru-1';
            $uid = Yii::$app->settings->get('s3_uid') ?: '';
            
            putenv("AWS_SUPPRESS_PHP_DEPRECATION_WARNING=true");
            $credentials = new Credentials($accessKey, $secretAccessKey);

            $s3 = new S3Client([
                'version' => '2006-03-01',
                'region' => $region,
                'endpoint' => $baseUrl,
                'use_path_style_endpoint' => true,
                'credentials' => $credentials,
            ]);

            // Определяем, является ли $body путем к файлу или содержимым
            $fileContent = $body;
            if (is_string($body)) {
                // Проверяем, является ли это путем к файлу
                if (strlen($body) < 260 && file_exists($body) && is_file($body)) {
                    $fileContent = file_get_contents($body);
                }
                // Иначе считаем, что это содержимое файла
            }

            $params = [
                'Bucket' => $uid,
                'Key' => $fileName,
                'Body' => $fileContent,
                'CacheControl' => 'public, max-age=' . self::DEFAULT_CACHE_MAX_AGE,
            ];

            if ($contentType) {
                $params['ContentType'] = $contentType;
            }

            $s3->putObject($params);

            return $fileName;
        } catch (\Exception $e) {
            Yii::error('Error uploading file to S3: ' . $e->getMessage() . ', file: ' . $fileName, __METHOD__);
            return false;
        }
    }

    /**
     * Получает публичный URL для файла в S3
     * 
     * @param string $fileName Путь к файлу в S3 (ключ)
     * @return string Публичный URL
     */
    public function getPublicUrl($fileName)
    {
        // Получаем настройки напрямую из Settings
        $publicUrl = Yii::$app->settings->get('s3_publicUrl') ?: '';
        $baseUrl = Yii::$app->settings->get('s3_baseUrl') ?: 'https://s3.timeweb.cloud';
        $uid = Yii::$app->settings->get('s3_uid') ?: '';
        
        // Если указан publicUrl, используем его
        if (!empty($publicUrl)) {
            return rtrim($publicUrl, '/') . '/' . ltrim($fileName, '/');
        }
        // Иначе формируем публичный URL на основе baseUrl и uid (bucket)
        // Для Timeweb Cloud формат: https://s3.timeweb.cloud/{bucket}/{key}
        return rtrim($baseUrl, '/') . '/' . $uid . '/' . ltrim($fileName, '/');
    }

    /**
     * Возвращает настроенный S3Client (настройки из Settings).
     * @return S3Client
     */
    protected function getClient(): S3Client
    {
        $baseUrl = Yii::$app->settings->get('s3_baseUrl') ?: 'https://s3.timeweb.cloud';
        $accessKey = Yii::$app->settings->get('s3_accessKey') ?: '';
        $secretAccessKey = Yii::$app->settings->get('s3_secretAccessKey') ?: '';
        $region = Yii::$app->settings->get('s3_region') ?: 'ru-1';
        putenv("AWS_SUPPRESS_PHP_DEPRECATION_WARNING=true");
        $credentials = new Credentials($accessKey, $secretAccessKey);
        return new S3Client([
            'version' => '2006-03-01',
            'region' => $region,
            'endpoint' => $baseUrl,
            'use_path_style_endpoint' => true,
            'credentials' => $credentials,
        ]);
    }

    /**
     * Возвращает UID бакета из настроек.
     * @return string
     */
    protected function getBucket(): string
    {
        return (string) (Yii::$app->settings->get('s3_uid') ?: '');
    }

    /**
     * Список объектов и «папок» по префиксу (с delimiter).
     * @param string $prefix Префикс (каталог), например "uploads/tasks-v2" или ""
     * @param string $delimiter Разделитель, по умолчанию "/"
     * @return array{prefixes: string[], objects: array{array{key: string, size: int, lastModified: string}}}
     */
    public function listObjects(string $prefix = '', string $delimiter = '/'): array
    {
        $uid = $this->getBucket();
        if ($uid === '') {
            return ['prefixes' => [], 'objects' => []];
        }
        $prefix = ltrim($prefix, '/');
        if ($prefix !== '') {
            $prefix = rtrim($prefix, '/') . '/';
        }
        try {
            $s3 = $this->getClient();
            $result = $s3->listObjectsV2([
                'Bucket' => $uid,
                'Prefix' => $prefix,
                'Delimiter' => $delimiter,
            ]);
            $prefixes = [];
            foreach ((array) ($result->get('CommonPrefixes') ?? []) as $cp) {
                $p = (string) ($cp['Prefix'] ?? '');
                if ($p !== '') {
                    $prefixes[] = rtrim($p, '/');
                }
            }
            $objects = [];
            foreach ((array) ($result->get('Contents') ?? []) as $obj) {
                $key = (string) ($obj['Key'] ?? '');
                if ($key === '' || $key === $prefix) {
                    continue;
                }
                $objects[] = [
                    'key' => $key,
                    'size' => (int) ($obj['Size'] ?? 0),
                    'lastModified' => (string) ($obj['LastModified'] ?? ''),
                ];
            }
            return ['prefixes' => $prefixes, 'objects' => $objects];
        } catch (\Exception $e) {
            Yii::error('S3 listObjects: ' . $e->getMessage() . ', prefix: ' . $prefix, __METHOD__);
            return ['prefixes' => [], 'objects' => []];
        }
    }

    /**
     * Все ключи под префиксом (без delimiter, для массовой установки заголовков).
     * @param string $prefix Префикс (каталог)
     * @return string[]
     */
    public function listAllKeysUnderPrefix(string $prefix): array
    {
        $uid = $this->getBucket();
        if ($uid === '') {
            return [];
        }
        $prefix = ltrim($prefix, '/');
        if ($prefix !== '') {
            $prefix = rtrim($prefix, '/') . '/';
        }
        $keys = [];
        try {
            $s3 = $this->getClient();
            $continuationToken = null;
            do {
                $params = ['Bucket' => $uid, 'Prefix' => $prefix];
                if ($continuationToken !== null) {
                    $params['ContinuationToken'] = $continuationToken;
                }
                $result = $s3->listObjectsV2($params);
                foreach ((array) ($result->get('Contents') ?? []) as $obj) {
                    $key = (string) ($obj['Key'] ?? '');
                    if ($key !== '' && $key !== $prefix) {
                        $keys[] = $key;
                    }
                }
                $continuationToken = $result->get('NextContinuationToken');
            } while ($continuationToken);
        } catch (\Exception $e) {
            Yii::error('S3 listAllKeysUnderPrefix: ' . $e->getMessage(), __METHOD__);
        }
        return $keys;
    }

    /**
     * Метаданные объекта (Content-Type и т.д.).
     * @param string $key Ключ в S3
     * @return array|null Массив с ключами ContentType, ContentLength, CacheControl и т.д. или null при ошибке
     */
    public function headObject(string $key): ?array
    {
        $uid = $this->getBucket();
        if ($uid === '') {
            return null;
        }
        try {
            $s3 = $this->getClient();
            $result = $s3->headObject(['Bucket' => $uid, 'Key' => $key]);
            return [
                'ContentType' => $result->get('ContentType'),
                'ContentLength' => $result->get('ContentLength'),
                'CacheControl' => $result->get('CacheControl'),
            ];
        } catch (\Exception $e) {
            Yii::warning('S3 headObject: ' . $e->getMessage() . ', key: ' . $key, __METHOD__);
            return null;
        }
    }

    /**
     * Устанавливает заголовки кэширования (и при необходимости Content-Type) для существующего объекта.
     * Копирует объект сам на себя с MetadataDirective REPLACE.
     * @param string $key Ключ в S3
     * @return bool Успех
     */
    public function setObjectCacheHeaders(string $key): bool
    {
        $uid = $this->getBucket();
        if ($uid === '') {
            return false;
        }
        try {
            $s3 = $this->getClient();
            $head = $this->headObject($key);
            $contentType = ($head['ContentType'] ?? null) ?: 'application/octet-stream';
            $copySource = $uid . '/' . rawurlencode($key);
            $s3->copyObject([
                'Bucket' => $uid,
                'Key' => $key,
                'CopySource' => $copySource,
                'MetadataDirective' => 'REPLACE',
                'CacheControl' => 'public, max-age=' . self::DEFAULT_CACHE_MAX_AGE,
                'ContentType' => $contentType,
            ]);
            return true;
        } catch (\Exception $e) {
            Yii::error('S3 setObjectCacheHeaders: ' . $e->getMessage() . ', key: ' . $key, __METHOD__);
            return false;
        }
    }

    /**
     * Устанавливает заголовки кэша для многих объектов параллельно (батчами).
     * Быстрее, чем вызывать setObjectCacheHeaders по одному.
     *
     * @param string[] $keys Ключи в S3
     * @param int $concurrency Сколько запросов выполнять одновременно (по умолчанию 25)
     * @return array{ok: int, fail: int} Количество успешных и неудачных
     */
    public function setObjectCacheHeadersBulk(array $keys, int $concurrency = 25): array
    {
        $uid = $this->getBucket();
        if ($uid === '') {
            return ['ok' => 0, 'fail' => count($keys)];
        }
        if (empty($keys)) {
            return ['ok' => 0, 'fail' => 0];
        }
        $s3 = $this->getClient();
        $cacheControl = 'public, max-age=' . self::DEFAULT_CACHE_MAX_AGE;
        $contentTypes = [];
        $chunks = array_chunk($keys, $concurrency);
        foreach ($chunks as $chunk) {
            $promises = [];
            foreach ($chunk as $key) {
                $promises[$key] = $s3->headObjectAsync(['Bucket' => $uid, 'Key' => $key]);
            }
            $results = PromiseUtils::settle($promises)->wait();
            foreach ($results as $key => $result) {
                if ($result['state'] === 'fulfilled') {
                    $ct = $result['value']->get('ContentType');
                    $contentTypes[$key] = $ct ?: 'application/octet-stream';
                }
            }
        }
        $ok = 0;
        $fail = count($keys) - count($contentTypes);
        $copyChunks = array_chunk(array_keys($contentTypes), $concurrency);
        foreach ($copyChunks as $copyChunk) {
            $promises = [];
            foreach ($copyChunk as $key) {
                $copySource = $uid . '/' . rawurlencode($key);
                $promises[$key] = $s3->copyObjectAsync([
                    'Bucket' => $uid,
                    'Key' => $key,
                    'CopySource' => $copySource,
                    'MetadataDirective' => 'REPLACE',
                    'CacheControl' => $cacheControl,
                    'ContentType' => $contentTypes[$key],
                ]);
            }
            $results = PromiseUtils::settle($promises)->wait();
            foreach ($results as $key => $result) {
                if ($result['state'] === 'fulfilled') {
                    $ok++;
                } else {
                    $fail++;
                    $reason = isset($result['reason']) && $result['reason'] instanceof \Throwable
                        ? $result['reason']->getMessage()
                        : 'unknown';
                    Yii::error("S3 setObjectCacheHeadersBulk copy fail: {$reason}, key: {$key}", __METHOD__);
                }
            }
        }
        return ['ok' => $ok, 'fail' => $fail];
    }

}