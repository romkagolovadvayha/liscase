<?php

namespace api\modules\swagger\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use Symfony\Component\Finder\Finder;

/**
 * Контроллер для Swagger документации
 */
class IndexController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * Swagger UI интерфейс
     */
    public function actionIndex()
    {
        $this->layout = false;
        Yii::$app->response->format = Response::FORMAT_HTML;

        $swaggerJsonUrl = Yii::$app->urlManager->createUrl(['swagger/json']);

        return $this->render('index', [
            'swaggerJsonUrl' => $swaggerJsonUrl,
        ]);
    }

    /**
     * OpenAPI JSON спецификация
     */
    public function actionJson()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->headers->set('Content-Type', 'application/json');

        try {
            // Проверяем наличие класса через autoload
            if (!class_exists('OpenApi\Generator', true)) {
                throw new \Exception('OpenApi\Generator class not found. Please run: composer require --dev zircote/swagger-php && composer dump-autoload');
            }

            // Используем Symfony Finder для сканирования PHP файлов
            $finder = new \Symfony\Component\Finder\Finder();
            $finder->files()
                ->name('*.php')
                ->in(Yii::getAlias('@api/controllers/v1'))
                ->exclude(['tests', 'Test']);

            $openapi = \OpenApi\Generator::scan(iterator_to_array($finder), [
                'validate' => false, // Отключаем валидацию для ускорения
            ]);

            if ($openapi === null) {
                return [
                    'openapi' => '3.0.0',
                    'info' => [
                        'title' => 'Liscase API',
                        'version' => '1.0.0',
                        'description' => 'No OpenAPI annotations found',
                    ],
                    'paths' => [],
                ];
            }

            return json_decode($openapi->toJson(), true);
        } catch (\Exception $e) {
            Yii::error('Swagger generation error: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'swagger');
            return [
                'openapi' => '3.0.0',
                'info' => [
                    'title' => 'Liscase API',
                    'version' => '1.0.0',
                    'description' => 'API documentation generation error: ' . $e->getMessage(),
                ],
                'paths' => [],
            ];
        }
    }
}

