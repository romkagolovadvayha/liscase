<?php

namespace common\components\storage;

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
                                                                'ContentDisposition' => 'inline',
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
                'ContentDisposition' => 'inline',
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

}