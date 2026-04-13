<?php

namespace api\controllers;

use common\helpers\ApiPublicCacheTtl;
use common\models\rustplugin\RustPluginConfig;
use common\models\servers\Servers;
use yii\web\Controller;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;

class RustPluginConfigController extends Controller
{
    public $enableCsrfValidation = false;

    public function beforeAction($action)
    {
        // Устанавливаем CORS заголовки
        Yii::$app->response->headers->set('Access-Control-Allow-Origin', '*');
        Yii::$app->response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        Yii::$app->response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        Yii::$app->response->headers->set('Access-Control-Max-Age', '3600');
        
        // Обработка preflight запросов
        if (Yii::$app->request->method === 'OPTIONS') {
            Yii::$app->response->statusCode = 200;
            Yii::$app->end();
        }
        
        return parent::beforeAction($action);
    }

    /**
     * Получить конфиг для сервера по IP и PORT с заменой тегов
     * @param string $ip IP адрес сервера
     * @param int $port Порт сервера
     * @param string $name Название плагина (обязательный параметр)
     * @param int $id ID конфига (опционально, если не указано name)
     * @return array
     * @throws NotFoundHttpException
     * @throws BadRequestHttpException
     */
    public function actionGet()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $ip = Yii::$app->request->get('ip');
        $port = Yii::$app->request->get('port');
        $pluginName = Yii::$app->request->get('name'); // Название плагина
        $configId = Yii::$app->request->get('id');
        
        // Валидация входных параметров
        if (empty($ip) || empty($port)) {
            throw new BadRequestHttpException('Необходимо указать IP и PORT сервера');
        }
        
        if (empty($pluginName) && empty($configId)) {
            throw new BadRequestHttpException('Необходимо указать название плагина (name) или ID конфига (id)');
        }
        
        // Поиск сервера по IP и PORT
        $server = Servers::find()
            ->andWhere(['ip' => $ip, 'port' => (int)$port])
            ->one();
        
        if (!$server) {
            throw new NotFoundHttpException('Сервер не найден');
        }
        
        // Поиск конфига по названию плагина (приоритет) или по ID
        $config = null;
        if (!empty($pluginName)) {
            $config = RustPluginConfig::findOne(['name' => $pluginName]);
        } elseif (!empty($configId)) {
            $config = RustPluginConfig::findOne($configId);
        }
        
        if (!$config) {
            $errorMsg = !empty($pluginName) 
                ? 'Конфиг для плагина "' . $pluginName . '" не найден'
                : 'Конфиг не найден';
            throw new NotFoundHttpException($errorMsg);
        }

        // Получаем декодированный контент
        $decodedContent = $config->getDecodedContent();
        if ($decodedContent === null) {
            $decodedContent = json_decode($config->content, true);
        }
        
        // Заменяем теги в контенте
        $replacedContent = $this->replaceTags($decodedContent, $server);
        
        return [
            'id' => $config->id,
            'name' => $config->name,
            'content' => $replacedContent,
        ];
    }

    /**
     * Получить конфиг по ID (старый метод, оставлен для совместимости)
     * @param int $id
     * @return array
     * @throws NotFoundHttpException
     */
    public function actionGetById($id)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $config = RustPluginConfig::findOne($id);
        
        if (!$config) {
            throw new NotFoundHttpException('Конфиг не найден');
        }

        $decodedContent = $config->getDecodedContent();
        
        return [
            'id' => $config->id,
            'name' => $config->name,
            'content' => $decodedContent !== null ? $decodedContent : json_decode($config->content, true),
        ];
    }

    /**
     * Получить все конфиги
     * @return array
     */
    public function actionIndex()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        // Кэшируем результат на 5 минут
        $cacheKey = 'rust_plugin_configs_list';
        $items = Yii::$app->cache->get($cacheKey);
        
        if ($items === false) {
            /** @var RustPluginConfig[] $list */
            $list = RustPluginConfig::find()
                ->orderBy(['id' => SORT_ASC])
                ->all();

            $items = [];
            foreach ($list as $item) {
                $decodedContent = $item->getDecodedContent();
                $items[] = [
                    'id' => $item->id,
                    'name' => $item->name,
                    'content' => $decodedContent !== null ? $decodedContent : json_decode($item->content, true),
                ];
            }
            
            Yii::$app->cache->set($cacheKey, $items, ApiPublicCacheTtl::SECONDS);
        }

        return $items;
    }

    /**
     * Рекурсивная замена тегов в массиве/значении
     * @param mixed $data Данные для обработки
     * @param Servers $server Объект сервера
     * @return mixed
     */
    protected function replaceTags($data, Servers $server)
    {
        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                // Рекурсивно обрабатываем ключи и значения
                $newKey = is_string($key) ? $this->replaceTagsInString($key, $server) : $key;
                $result[$newKey] = $this->replaceTags($value, $server);
            }
            return $result;
        } elseif (is_string($data)) {
            return $this->replaceTagsInString($data, $server);
        } else {
            return $data;
        }
    }

    /**
     * Замена тегов в строке
     * @param string $string Строка с тегами
     * @param Servers $server Объект сервера
     * @return string
     */
    protected function replaceTagsInString($string, Servers $server)
    {
        // Определяем текстовое значение типа вайпа
        $wipeTypeText = '';
        if ($server->wipe_type === 7) {
            $wipeTypeText = 'Недельный';
        } elseif ($server->wipe_type === 14) {
            $wipeTypeText = 'Двухнедельный';
        } elseif ($server->wipe_type === 30) {
            $wipeTypeText = 'Месячный';
        }
        
        // Словарь замены тегов
        $replacements = [
            '{SERVER_NAME}' => $server->name,
            '{SERVER_IP}' => $server->ip,
            '{SERVER_PORT}' => (string)$server->port,
            '{SERVER_TAG}' => $server->tag ?? '',
            '{SERVER_QUERY_PORT}' => (string)($server->query ?? $server->port),
            '{SERVER_RCON_PORT}' => (string)($server->rcon ?? ''),
            '{SERVER_MONITORING_NAME}' => $server->monitoring_name ?? $server->name,
            '{SERVER_DESCRIPTION}' => $server->description ?? '',
            '{SERVER_MAP}' => $server->map ?? '',
            '{SERVER_MAX_PLAYERS}' => (string)($server->max ?? 0),
            '{SERVER_TEAM_LIMIT}' => (string)($server->team_limit ?? 0),
            '{TEXT_IP}' => $server->text_ip ?? $server->ip,
            '{WIPE_TYPE}' => $wipeTypeText,
        ];
        
        // Заменяем теги (регистронезависимо)
        foreach ($replacements as $tag => $value) {
            $string = str_ireplace($tag, $value, $string);
        }
        
        return $string;
    }
}

