<?php

namespace frontend\controllers;

use common\components\yearreview\YearReviewGenerator;
use common\controllers\WebController;
use common\models\user\User;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class YearReviewController extends WebController
{
    /**
     * Генерация изображения итогов года для игрока
     * @param int $userId ID пользователя
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionGenerate($userId)
    {
        $user = User::findOne($userId);
        if (!$user) {
            throw new NotFoundHttpException('Пользователь не найден');
        }

        // Кэшируем результат на 5 минут
        $cacheKey = 'year_review_generate_' . $userId;
        $image = Yii::$app->cache->get($cacheKey);
        
        if ($image === false) {
            /** @var YearReviewGenerator $generator */
            $generator = Yii::createObject(YearReviewGenerator::class);

            // Получаем статистику игрока за все время
            $stats = $generator->getPlayerYearStats($user);

            // Путь к фоновому изображению из настроек
            $backgroundPath = Yii::$app->settings->get('design_year_review');
            
            if (empty($backgroundPath)) {
                throw new NotFoundHttpException('Фоновое изображение не настроено в настройках');
            }
            
            // Если путь относительный, добавляем путь к web директории
            if (strpos($backgroundPath, '/') !== 0 && strpos($backgroundPath, 'http') !== 0) {
                $backgroundPath = Yii::getAlias('@app/web/' . ltrim($backgroundPath, '/'));
            } elseif (strpos($backgroundPath, '/') === 0) {
                $backgroundPath = Yii::getAlias('@app/web' . $backgroundPath);
            }
            
            if (!file_exists($backgroundPath)) {
                throw new NotFoundHttpException('Фоновое изображение не найдено: ' . $backgroundPath);
            }

            // Создаем изображение
            $image = $generator->generateImage($backgroundPath, $stats, $user);
            
            // Кэшируем на 5 минут (300 секунд)
            Yii::$app->cache->set($cacheKey, $image, 300);
        }

        // Отдаем изображение
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'image/png');
        Yii::$app->response->headers->set('Content-Disposition', 'inline; filename="year-review-' . $userId . '.png"');
        Yii::$app->response->headers->set('Cache-Control', 'public, max-age=300');

        return $image;
    }
}
