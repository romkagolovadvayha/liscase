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


    public function createPresignedRequest($key)
    {
        putenv("AWS_SUPPRESS_PHP_DEPRECATION_WARNING=true");
        $credentials = new Credentials($this->accessKey, $this->secretAccessKey);

        $s3 = new S3Client([
                               'version' => '2006-03-01',
                               'region' => $this->region,
                               'endpoint' => $this->baseUrl,
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
            'Bucket' => $this->uid,
            'Key'    => $key,
        ]);

        // Генерация URL с подписанием на 15 минут
        return (string) $s3->createPresignedRequest($command, '+15 minutes')->getUri();
    }

    /**
     * @param $body
     *
     * @return mixed
     */
    public function uploadFile($fileName, $body)
    {
        putenv("AWS_SUPPRESS_PHP_DEPRECATION_WARNING=true");
        $credentials = new Credentials($this->accessKey, $this->secretAccessKey);

        $s3 = new S3Client([
                               'version' => '2006-03-01',
                               'region' => $this->region,
                               'endpoint' => $this->baseUrl,
                               'use_path_style_endpoint' => true,
                               'credentials' => $credentials,
                           ]);

        $createMultipartUpload = $s3->createMultipartUpload([
                                                                'Bucket' => $this->uid,
                                                                'Key' => $fileName,
                                                            ]);

        $uploadId = $createMultipartUpload->get('UploadId');

        $partKey = 1;
        $uploadPart = $s3->uploadPart([
                                          'Bucket' => $this->uid,
                                          'Key' => $fileName,
                                          'Body' => $body,
                                          'PartNumber' => $partKey,
                                          'UploadId' => $uploadId,
        ]);

        $response = $s3->completeMultipartUpload([
                                        'Bucket' => $this->uid,
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

        return $response->getIterator()->offsetGet('Key');
    }


}