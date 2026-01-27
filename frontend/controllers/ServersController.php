<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\servers\Servers;
use common\models\servers\ServersTags;
use common\models\stats\Teams;
use common\models\stats\Wipe;
use frontend\assets\MapsV2Asset;
use yii\web\NotFoundHttpException;
use Yii;

class ServersController extends WebController
{

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionIndex()
    {
        $cache = Yii::$app->cache;
        $cacheKey = 'servers/index:data';

        $cached = $cache->get($cacheKey);
        if ($cached) {
            [$servers, $projectStats, $serversLd] = $cached;
        } else {
            $servers = Servers::find()
                              ->with([
                                  'serversTags',
                                  'mapEntity',
                                  'mapList',
                              ])
                              ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                              ->orderBy(['sort' => SORT_ASC])
                              ->all();

            $projectStats = \common\models\statistics\Statistics::projectStats();

            $serversLd = [];
            foreach ($servers as $s) {
                $status = $s->status == Servers::STATUS_ACTIVE ? 'Online' :
                    ($s->status == Servers::STATUS_WAIT ? 'Maintenance' : 'Offline');

                $serversLd[] = [
                    '@type' => 'GameServer',
                    'name'  => Yii::t('database', $s->monitoring_description) . ' [' . Yii::t('database', $s->monitoring_name) . ']',
                    'game'  => ['@type' => 'VideoGame', 'name' => 'Rust'],
                    'serverStatus' => 'https://schema.org/' . $status,
                    'playersOnline' => (int)($s->players + $s->joined),
                    'maximumAttendeeCapacity' => (int)$s->max,
                    'url'   => Yii::$app->params['homePage'] . $s->getLink('stats'),
                    'address' => $s->ip . ':' . $s->port
                ];
            }

            $cache->set($cacheKey, [$servers, $projectStats, $serversLd], 180);
        }
        $this->view->title = Yii::t('common', 'Сервера Rust | Выберите сервер для комфортной игры');
        $this->view->params['page'] = 'servers';
        $this->view->params['meta_description'] = Yii::t('common', "Список всех наших серверов Rust с подробным описанием, датами вайпов и IP-адресами. Узнайте, когда следующий вайп, подключитесь к любимому серверу и начните играть уже сегодня!");

        $view = Yii::$app->view;
        $canonical = Yii::$app->params['homePage'] . '/servers';
        $view->registerLinkTag(['rel' => 'canonical', 'href' => $canonical]);
        $view->registerMetaTag(['name' => 'robots', 'content' => 'index,follow,max-image-preview:large']);

        // Open Graph
        $view->registerMetaTag(['property'=>'og:type','content'=>'website']);
        $view->registerMetaTag(['property'=>'og:title','content'=>$this->view->title]);
        $view->registerMetaTag(['property'=>'og:description','content'=>$this->view->params['meta_description']]);
        $view->registerMetaTag(['property'=>'og:url','content'=>$canonical]);
        $view->registerMetaTag(['property'=>'og:site_name','content'=>'Prostoj']);

        // Twitter
        $view->registerMetaTag(['name'=>'twitter:card','content'=>'summary_large_image']);
        $view->registerMetaTag(['name'=>'twitter:title','content'=>$this->view->title]);
        $view->registerMetaTag(['name'=>'twitter:description','content'=>$this->view->params['meta_description']]);

        // Хлебные крошки — ок
        $breadcrumbLd = [
            '@context' => 'https://schema.org',
            '@type'    => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => Yii::t('common','Главная'),       'item' => Yii::$app->params['homePage'].'/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => Yii::t('common','Сервера Rust'),  'item' => Yii::$app->params['homePage'].'/servers'],
            ],
        ];

        // $serversLd должен быть массивом вида:
        // [['name'=>'ПРОСТОЙ #1 [MAX3]', 'url'=>'https://prostoj.store/servers/max3'], ...]

        // Исправленный ItemList для rich result
        $itemListLd = [
            '@context' => 'https://schema.org',
            '@type'    => 'ItemList',
            'name'     => 'Список серверов Rust Prostoj',
            'itemListElement' => array_map(function($srv, $idx){
                return [
                    '@type'    => 'ListItem',
                    'position' => $idx + 1,
                    // ВАЖНО: сюда — ТОЛЬКО URL (или Thing с name+url), НЕ объект GameServer
                    'item'     => (string)$srv['url'],
                    'name'     => (string)$srv['name'],
                ];
            }, $serversLd, array_keys($serversLd)),
        ];

        // Если хочешь оставить подробности серверов — вынеси во второй скрипт (не для rich result):
        $gameServersGraph = [
            '@context' => 'https://schema.org',
            '@graph'   => array_map(function($srv){
                return [
                    '@type'                     => 'GameServer',
                    'name'                      => (string)$srv['name'],
                    'game'                      => ['@type'=>'VideoGame','name'=>'Rust'],
                    'serverStatus'              => 'https://schema.org/Online',
                    'playersOnline'             => $srv['playersOnline'] ?? null,
                    'maximumAttendeeCapacity'   => $srv['maximumAttendeeCapacity'] ?? null,
                    'url'                       => (string)$srv['url'],
                    'address'                   => $srv['address'] ?? null,
                ];
            }, $serversLd),
        ];

        // Отдавай оба (двумя <script>), если нужно:
        $this->view->params['ld_json'] = [$breadcrumbLd, $itemListLd, $gameServersGraph];

        // Регистрируем MapsV2Asset для работы модального окна с деталями карты
        $hasFixedMap = false;
        foreach ($servers as $server) {
            if ($server->map_list_id && $server->mapList) {
                $hasFixedMap = true;
                break;
            }
        }
        if ($hasFixedMap) {
            MapsV2Asset::register($this->view);
        }

        return $this->render('servers-list.twig', [
            'SERVERS' => $servers,
            'PROJECT_STATS' => $projectStats,
            'SETTINGS' => Yii::$app->settings
        ]);
    }

    public function actionWipeInfo($serverTag)
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        foreach ($servers as $item) {
            if ($item->tag === $serverTag) {
                $server = $item;
                break;
            }
        }

        if (empty($server)) {
            throw new NotFoundHttpException(Yii::t('common', 'Сервер не найден!'));
        }
        return $this->renderAjax('wipe-info.twig', [
            'SERVERS' => $servers,
            'SERVER' => $server,
            'SETTINGS' => Yii::$app->settings
        ]);
    }

    /**
     * @param $serverTag
     *
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionRules($serverTag)
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        foreach ($servers as $item) {
            if ($item->tag === $serverTag) {
                $server = $item;
                break;
            }
        }

        if (empty($server)) {
            throw new NotFoundHttpException(Yii::t('common', 'Сервер не найден!'));
        }
        $commands = json_decode($server->commands, 1);
        $this->view->title = Yii::t('common', 'Правила сервера') . " " . Yii::t('database', $server->name);
        $this->view->params['page'] = 'rules';
        $this->view->params['meta_description'] = Yii::t('common', "Правила сервера {PARAM_SERVER_NAME_SHORT}. Узнайте все ограничения и возможности на серверах, чтобы не попасть в блокировку.", [
            'PARAM_SERVER_NAME_SHORT' => Yii::t('database', $server->monitoring_name),
        ]);

        return $this->render('rules.twig', [
            'SERVER' => $server,
            'SERVERS' => $servers,
            'COMMANDS' => $commands,
        ]);
    }

    /**
     * @param $tagLink
     *
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionTag($tagLink)
    {
        /** @var ServersTags $serversTag */
        $serversTag = ServersTags::find()
                          ->with([
                              'servers' => function($query) {
                                  $query->with('mapList');
                              }
                          ])
                          ->cache(30)
                          ->andWhere(['IN', 'status', [ServersTags::STATUS_ACTIVE]])
                          ->andWhere(['link_name' => $tagLink])
                          ->one();

        if (empty($serversTag)) {
            throw new NotFoundHttpException(Yii::t('common', 'Страница не найдена!'));
        }
        $this->view->title = Yii::t('database', $serversTag->title);
        $this->view->params['meta_description'] = Yii::t('database', $serversTag->short_description);

        // Регистрируем MapsV2Asset для работы модального окна с деталями карты
        $hasFixedMap = false;
        if ($serversTag->servers) {
            foreach ($serversTag->servers as $server) {
                if ($server->map_list_id && $server->mapList) {
                    $hasFixedMap = true;
                    break;
                }
            }
        }
        if ($hasFixedMap) {
            MapsV2Asset::register($this->view);
        }

        return $this->render('tag.twig', [
            'TAG' => $serversTag,
        ]);
    }

    public function actionWipeBlock()
    {
        $this->layout = 'service';
        return $this->renderAjax('wipe-block');
    }

}
