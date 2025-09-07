<?php
namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use common\components\telegram\TelegramNewsService ;
use common\components\telegram\TelegramNewsCallbackInterface;

class TelegramNewsController extends Controller
{
    public $enableCsrfValidation = false;

    private function validateSecret(): bool
    {
        $expected = Yii::$app->settings->get('telegram_parser_webhook_secret') ?: '';
        if (!$expected) return true;
        $got = Yii::$app->request->headers->get('X-Telegram-Bot-Api-Secret-Token', '');
        return hash_equals($expected, $got);
    }

    /** Вебхук от Telegram: пишем запись (status=0) и зовем твой callback */
    public function actionIncoming(TelegramNewsService $service, TelegramNewsCallbackInterface $callback)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!$this->validateSecret()) {
            return ['ok'=>true,'skipped'=>'bad secret'];
        }

        $raw = file_get_contents('php://input');
        $update = json_decode($raw, true);
        if (!$update) return ['ok'=>true,'skipped'=>'no json'];

        $post = $update['channel_post'] ?? $update['edited_channel_post'] ?? null;
        if (!$post) return ['ok'=>true,'skipped'=>'not channel_post'];

        try {
            $news = $service->createFromTelegramPost($post);
        } catch (\DomainException $e) {
            if ($e->getMessage() === 'source_not_allowed') {
                return ['ok'=>true, 'skipped'=>'source not allowed'];
            }
            throw $e;
        }
        $callback->onNewTelegramNews($news);
        return ['ok'=>true,'news_id'=>$news->id,'status'=>$news->status];
    }

    /** Ты дергаешь это после своей обработки: публикуем и меняем статус */
    public function actionSuccessPost($id, TelegramNewsService $service)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        try {
            return $service->publish((int)$id);
        } catch (\Throwable $e) {
            return ['ok'=>false,'error'=>$e->getMessage()];
        }
    }
}
