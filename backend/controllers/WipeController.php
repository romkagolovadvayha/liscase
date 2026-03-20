<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\components\queue\process\MapGenerateJob;
use common\components\queue\process\MapFixJob;
use common\models\box\Drop;
use common\models\box\DropBlocked;
use common\models\map\Map;
use common\models\map\MapList;
use common\models\map\MapListVote;
use common\models\profit\Profit;
use common\models\promocode\Promocode;
use common\models\rcon\RconTasks;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\stats\Wipe;
use common\models\user\User;
use common\models\user\UserPromocode;
use common\models\user\UserTask;
use common\models\user\UserTop;
use WebSocket\Client;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use Yii;

class WipeController extends Controller
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN],
                    ],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="bi bi-play-circle"></i> ' . Yii::t('common', 'Вайп через RCON'),
                'url' => ['/wipe/run-wipe'],
                'class' => 'ds-btn ds-btn--danger ds-btn--sm',
            ],
            [
                'label' => '<i class="bi bi-lightning-charge"></i> ' . Yii::t('common', 'Комплексный вайп'),
                'url' => ['/wipe/wipe-servers'],
                'class' => 'ds-btn ds-btn--success ds-btn--sm',
            ],
            [
                'label' => '<i class="bi bi-pin-map-fill"></i> ' . Yii::t('common', 'Фиксация карт'),
                'url' => ['/wipe/fix-map-form'],
                'class' => 'ds-btn ds-btn--primary ds-btn--sm',
            ],
            [
                'label' => '<i class="bi bi-trash"></i> ' . Yii::t('common', 'Удалить незафиксированные карты'),
                'url' => ['/wipe/delete-unfixed-maps'],
                'class' => 'ds-btn ds-btn--secondary ds-btn--sm',
                'data' => ['confirm' => Yii::t('common', 'Удалить все незафиксированные карты?'), 'method' => 'post'],
            ],
            [
                'label' => '<i class="bi bi-arrow-counterclockwise"></i> ' . Yii::t('common', 'Обнулить промокод WIPE'),
                'url' => ['/wipe/promocode'],
                'class' => 'ds-btn ds-btn--secondary ds-btn--sm',
                'data' => ['confirm' => Yii::t('common', 'Обнулить промокод WIPE? Пользователи смогут ввести его заново.'), 'method' => 'post'],
            ],
            [
                'label' => '<i class="bi bi-trophy-fill"></i> ' . Yii::t('common', 'Начисления за прошлый вайп'),
                'url' => ['/wipe/top-rewards'],
                'class' => 'ds-btn ds-btn--primary ds-btn--sm',
            ],
        ];
        return $this->render('index');
    }

    /**
     * @throws \yii\db\StaleObjectException
     * @throws \Throwable
     */
    public function actionBlock($id)
    {
        $cacheKey = "WIPE_actionBlock_{$id}";
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        /** @var Drop[] $drops */
        $drops = Drop::find()
            ->all();
        DropBlocked::unBlocked($id);
        foreach ($drops as $drop) {
            if (!empty($drop->blocked_hour)) {
                $date = new \DateTime();
                $date->modify("+{$drop->blocked_hour} hour");
                DropBlocked::createRecord($drop->id, $id, $date->format('Y-m-d H:i:s'));
            }
        }
        Yii::$app->cache->set($cacheKey, 1, 5*60);

        $cacheKeyGetBlocked = "DropBlocked_getBlocked_" . $id;
        Yii::$app->cache->delete($cacheKeyGetBlocked);

        \console\controllers\ChatServer::broadcastLauncherUpdate();

        Yii::$app->session->addFlash('success', 'Предметы успешно заблокированы!');
        return $this->redirect('index');
    }

    public function actionSelectMap($id)
    {

        \Yii::$app->queueProcess->push(new MapFixJob(['serverId' => $id]));
        Yii::$app->session->addFlash('success', 'Фиксация карты запущена!');
        return $this->redirect('index');
    }

    public function actionGenerateMap($id)
    {
        $cacheKey = "WIPE_actionGenerateMap6_{$id}";
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        Yii::$app->cache->set($cacheKey, 1, 5*60);

        \Yii::$app->queueProcess->push(new MapGenerateJob(['serverId'  => $id]));
        Yii::$app->session->addFlash('success', 'Генерирация запущена!');
        return $this->redirect('index');
    }

    public function actionDeleteUnfixedMaps()
    {
        MapList::deleteUnfixedMaps();
        Yii::$app->session->addFlash('success', 'Не зафиксированные карты удалены!');
        return $this->redirect('index');
    }

    /**
     * Массовая фиксация карт для всех серверов
     * Сначала определяет соответствия сервер-карта, затем фиксирует
     */
    public function actionMassFixMaps()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        /** @var Servers[] $servers */
        $servers = Servers::find()
            ->andWhere(['IN', 'status', [Servers::STATUS_NOACTIVE, Servers::STATUS_ACTIVE]])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        if (empty($servers)) {
            return [
                'success' => false,
                'message' => 'Серверы не найдены',
            ];
        }

        $results = [];
        $overallSuccess = true;
        $serverMapMapping = []; // Массив соответствий server_id => map_id

        // Шаг 1: Определяем соответствия сервер-карта
        foreach ($servers as $server) {
            try {
                if ($server->secret_map) {
                    $results[$server->id] = [
                        'success' => true,
                        'message' => 'Пропущено (секретная карта)',
                    ];
                    continue;
                }

                // Определяем выигрышную карту для сервера (без фиксации)
                $winningMap = MapList::getWinningMapForServer($server->id);
                
                if ($winningMap) {
                    $serverMapMapping[$server->id] = $winningMap->id;
                } else {
                    // Нет выигрышной карты для этого сервера
                    $results[$server->id] = [
                        'success' => true,
                        'message' => 'Пропущено (нет голосов за карты)',
                    ];
                }
            } catch (\Exception $e) {
                $results[$server->id] = [
                    'success' => false,
                    'message' => 'Ошибка при определении карты: ' . $e->getMessage(),
                ];
                $overallSuccess = false;
            }
        }

        // Шаг 2: Фиксируем карты для серверов, для которых определили карты
        foreach ($serverMapMapping as $serverId => $mapId) {
            // Пропускаем серверы, для которых уже установлен результат
            if (isset($results[$serverId])) {
                continue;
            }
            
            try {
                $server = Servers::findOne($serverId);
                if (!$server) {
                    $results[$serverId] = [
                        'success' => false,
                        'message' => 'Сервер не найден',
                    ];
                    $overallSuccess = false;
                    continue;
                }

                // Используем MapFixJob через очередь
                \Yii::$app->queueProcess->push(new MapFixJob(['serverId' => $serverId]));
                
                // Также фиксируем напрямую для немедленного результата
                $fixedMap = MapList::fixWinningMapForServer($serverId);
                
                if ($fixedMap) {
                    // Формируем описание карты из доступных полей
                    $mapDescription = "Seed: {$fixedMap->seed}";
                    if ($fixedMap->size) {
                        $mapDescription .= ", Size: {$fixedMap->size}";
                    }
                    if ($fixedMap->size_int) {
                        $mapDescription .= " ({$fixedMap->size_int})";
                    }
                    
                    $results[$serverId] = [
                        'success' => true,
                        'message' => "Карта (ID: {$fixedMap->id}, {$mapDescription}) успешно зафиксирована",
                    ];
                } else {
                    $results[$serverId] = [
                        'success' => false,
                        'message' => 'Не удалось зафиксировать карту',
                    ];
                    $overallSuccess = false;
                }
            } catch (\Exception $e) {
                $results[$serverId] = [
                    'success' => false,
                    'message' => 'Ошибка при фиксации: ' . $e->getMessage(),
                ];
                $overallSuccess = false;
            }
        }

        return [
            'success' => $overallSuccess,
            'message' => $overallSuccess ? 'Массовая фиксация карт выполнена успешно' : 'Массовая фиксация карт выполнена с ошибками',
            'results' => $results,
        ];
    }

    /**
     * Форма фиксации карт: для каждого сервера подставляется карта, которая
     * выигрывает в голосовании и ещё не зафиксирована ни на одном сервере.
     * Поля: ID карты (редактируемое), справочно seed и кол-во голосов.
     */
    public function actionFixMapForm()
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()
            ->andWhere(['IN', 'status', [Servers::STATUS_NOACTIVE, Servers::STATUS_ACTIVE]])
            ->andWhere(['secret_map' => 0])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post('map_list_id', []);
            $fixOnlyServerId = (int)Yii::$app->request->post('fix_server', 0);
            $fixed = 0;
            foreach ($servers as $server) {
                if ($fixOnlyServerId > 0 && (int)$server->id !== $fixOnlyServerId) {
                    continue;
                }
                $mapId = isset($post[$server->id]) ? (int)$post[$server->id] : 0;
                if ($mapId > 0 && MapList::find()->where(['id' => $mapId])->exists()) {
                    $server->map_list_id = $mapId;
                    $server->save(false);
                    $fixed++;
                }
            }
            if ($fixOnlyServerId > 0) {
                Yii::$app->session->addFlash('success', Yii::t('common', 'Карта для сервера зафиксирована.'));
            } else {
                Yii::$app->session->addFlash('success', Yii::t('common', 'Карты зафиксированы. Обработано серверов: {n}.', ['n' => $fixed]));
            }
            return $this->redirect(['/wipe/fix-map-form']);
        }

        $rows = [];
        foreach ($servers as $server) {
            // Только карты, которые ещё не зафиксированы ни на одном сервере
            $winningMap = MapList::getWinningMapForServerUnfixedOnly($server->id);
            $voteCount = 0;
            if ($winningMap) {
                $voteCount = MapListVote::find()
                    ->where(['server_id' => $server->id, 'map_list_id' => $winningMap->id])
                    ->count();
            }
            $rows[] = [
                'server' => $server,
                'winningMap' => $winningMap,
                'voteCount' => $voteCount,
            ];
        }

        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="bi bi-arrow-left"></i> ' . Yii::t('common', 'Назад к вайпу'),
                'url' => ['/wipe/index'],
                'class' => 'ds-btn ds-btn--secondary ds-btn--sm',
            ],
        ];

        return $this->render('fix-map-form', [
            'rows' => $rows,
        ]);
    }

    public function actionTop($server, $wipe = null)
    {
        $cacheKey = "WIPE_actionTop_{$server}";
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        Yii::$app->cache->set($cacheKey, 1, 30*60);
        ini_set('memory_limit', '512M');
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['IN', 'tag', $server])
                          ->andWhere(['IN', 'status', [Servers::STATUS_NOACTIVE, Servers::STATUS_ACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();
        foreach ($servers as $server) {
            if (empty($wipe)) {
                $wipe = $server->currentWipe();
            }
            $tops = UserTop::getUserTops($server, $wipe);
            foreach ($tops as $top) {
                $value = $top['label'];
                foreach ($top['items'] as $i => $item) {
                    $user                    = User::findBySteamId($item['steam_id'], false, 'top');
                    $profit                  = new Profit();
                    $profit->status          = 1;
                    $profit->type            = Profit::TYPE_TOP;
                    $profit->amount          = $item['amount'];
                    $profit->user_balance_id = $user->getPersonalBalance()->id;
                    $profit->comment         = "Награда за первое место в топе \"{$value}\"";
                    if ($i === 1) {
                        $profit->comment = "Награда за второе место в топе \"{$value}\"";
                    } elseif ($i === 2) {
                        $profit->comment = "Награда за третье место в топе \"{$value}\"";
                    }
                    if (!empty($user->telegram_chat_id)) {
                        $text = "🥇 Награда за первое место в топе \"{$value}\" - <b>{$profit->amount} РУБ</b>";
                        if ($i === 1) {
                            $text = "🥈 Награда за второе место в топе \"{$value}\" - <b>{$profit->amount} РУБ</b>";
                        } elseif ($i === 2) {
                            $text = "🥉 Награда за третье место в топе \"{$value}\" - <b>{$profit->amount} РУБ</b>";
                        }
                        if (!empty($tgMessage[$user->steam_id])) {
                            $tgMessage[$user->steam_id] .= PHP_EOL . $text;
                        } else {
                            $tgMessage[$user->steam_id] = "Вам начислены награды за ТОП на сервере "
                                . $server->name . PHP_EOL . $text;
                        }
                    }
                    $profit->created_at = date('Y-m-d H:i:s');
                    $profit->save(false);
                }
            }
        }
        if (YII_ENV_PROD) {
            foreach ($tgMessage as $steamId => $message) {
                $user = User::findBySteamId($steamId, false, 'top2');
                Yii::$app->personalBotTelegram->sendMessage($user->telegram_chat_id, $message);
            }
        }

        Yii::$app->session->addFlash('success', 'Награды распределены успешно!');
        return $this->redirect('index');
    }

    public function actionTopRewards()
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()
            ->cache(30)
            ->andWhere(['IN', 'status', [Servers::STATUS_NOACTIVE, Servers::STATUS_ACTIVE]])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        $serverOptions = ArrayHelper::map($servers, 'tag', function (Servers $server) {
            return "{$server->name} ({$server->tag})";
        });

        $selectedServerTags = (array)Yii::$app->request->post('server_tags', Yii::$app->request->get('server_tags', []));
        $selectedServerTags = array_values(array_intersect($selectedServerTags, array_keys($serverOptions)));
        if (empty($selectedServerTags)) {
            $selectedServerTags = array_keys($serverOptions);
        }

        $wipe = (string)Yii::$app->request->post('wipe', Yii::$app->request->get('wipe', ''));
        $wipe = trim($wipe);

        $availableWipes = UserTop::find()
            ->select('wipe')
            ->distinct()
            ->orderBy(['wipe' => SORT_DESC])
            ->limit(200)
            ->column();

        if (empty($wipe) && !empty($availableWipes)) {
            $wipe = (string)$availableWipes[0];
        }

        $selectedServers = array_values(array_filter($servers, static function (Servers $server) use ($selectedServerTags) {
            return in_array($server->tag, $selectedServerTags, true);
        }));

        $plan = null;
        if (!empty($wipe) && !empty($selectedServers)) {
            $plan = $this->buildTopRewardsPlan($selectedServers, $wipe);
        }

        if (Yii::$app->request->isPost && Yii::$app->request->post('confirm') === '1') {
            if (empty($plan) || empty($plan['payableRows'])) {
                Yii::$app->session->addFlash('warning', 'Нет доступных начислений для подтверждения.');
                return $this->redirect(['top-rewards', 'wipe' => $wipe, 'server_tags' => $selectedServerTags]);
            }

            $result = $this->applyTopRewardsPlan($plan);
            Yii::$app->session->addFlash('success', "Начисления выполнены. Создано выплат: {$result['count']}, сумма: {$result['amount']} РУБ.");
            return $this->redirect(['top-rewards', 'wipe' => $wipe, 'server_tags' => $selectedServerTags]);
        }

        return $this->render('top-rewards', [
            'servers' => $servers,
            'serverOptions' => $serverOptions,
            'selectedServerTags' => $selectedServerTags,
            'availableWipes' => $availableWipes,
            'wipe' => $wipe,
            'plan' => $plan,
        ]);
    }

    public function actionPromocode()
    {
        /** @var UserPromocode[] $uPromocodes */
        $uPromocodes = UserPromocode::find()
            ->andWhere(['promocode_id' => 2])
            ->all();

        foreach ($uPromocodes as $item) {
            $item->delete();
        }

        Yii::$app->session->addFlash('success', 'Промокод теперь можно ввести заного!');
        return $this->redirect('index');
    }

    public function actionTaskClear()
    {
        /** @var UserTask[] $items */
        $items = UserTask::find()
            ->all();

        foreach ($items as $item) {
            $item->delete();
        }

        Yii::$app->session->addFlash('success', 'Задания обнулены!');
        return $this->redirect('index');
    }

    public function actionClearCache()
    {
        Yii::$app->runAction('translate/clear-translate-cache');

        Yii::$app->session->addFlash('success', 'Кэш очищен!');
        return $this->redirect('index');
    }

    /**
     * Страница для комплексного вайпа серверов
     */
    public function actionWipeServers()
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()
            ->cache(30)
            ->andWhere(['IN', 'status', [Servers::STATUS_NOACTIVE, Servers::STATUS_ACTIVE]])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        return $this->render('wipe-servers', [
            'servers' => $servers,
        ]);
    }

    /**
     * AJAX endpoint для выполнения комплексного вайпа
     * Выполняет этапы последовательно и возвращает результаты в реальном времени
     */
    public function actionExecuteWipe()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $serverIds = Yii::$app->request->post('server_ids', []);
        // Если пришел не массив, преобразуем
        if (!is_array($serverIds)) {
            $serverIds = [$serverIds];
        }
        $rconCommand = Yii::$app->request->post('rcon_command', '');

        if (empty($serverIds)) {
            return [
                'success' => false,
                'message' => 'Не выбраны серверы для вайпа',
            ];
        }

        /** @var Servers[] $servers */
        $servers = Servers::find()
            ->andWhere(['id' => $serverIds])
            ->andWhere(['IN', 'status', [Servers::STATUS_NOACTIVE, Servers::STATUS_ACTIVE]])
            ->all();

        if (empty($servers)) {
            return [
                'success' => false,
                'message' => 'Серверы не найдены',
            ];
        }

        $results = [];
        $overallSuccess = true;

        // Этап 1: Блокировка предметов в магазине
        $results['step1_block_items'] = [];
        foreach ($servers as $server) {
            try {
                $cacheKey = "WIPE_actionBlock4_{$server->id}";
                if (Yii::$app->cache->get($cacheKey)) {
                    $results['step1_block_items'][$server->id] = [
                        'success' => false,
                        'message' => 'Блокировка уже выполнена недавно (кэш активен)',
                    ];
                    $overallSuccess = false;
                    continue;
                }

                /** @var Drop[] $drops */
                $drops = Drop::find()->all();
                DropBlocked::unBlocked($server->id);
                
                foreach ($drops as $drop) {
                    if (!empty($drop->blocked_hour)) {
                        $date = new \DateTime();
                        $date->modify("+{$drop->blocked_hour} hour");
                        DropBlocked::createRecord($drop->id, $server->id, $date->format('Y-m-d H:i:s'));
                    }
                }
                
                Yii::$app->cache->set($cacheKey, 1, 1*60);
                $cacheKeyGetBlocked = "DropBlocked_getBlocked_" . $server->id;
                Yii::$app->cache->delete($cacheKeyGetBlocked);
                
                \console\controllers\ChatServer::broadcastLauncherUpdate();
                
                $results['step1_block_items'][$server->id] = [
                    'success' => true,
                    'message' => 'Предметы успешно заблокированы',
                ];
            } catch (\Exception $e) {
                $results['step1_block_items'][$server->id] = [
                    'success' => false,
                    'message' => 'Ошибка: ' . $e->getMessage(),
                ];
                $overallSuccess = false;
            }
        }

        // Если этап 1 не прошел, останавливаемся
        if (!$overallSuccess) {
            return [
                'success' => false,
                'message' => 'Ошибка на этапе 1: Блокировка предметов',
                'results' => $results,
            ];
        }

        // Этап 2: Начисление наград за топы
        $results['step2_top_rewards'] = [];
        foreach ($servers as $server) {
            try {
                $cacheKey = "WIPE_actionTop4_{$server->tag}";
                if (Yii::$app->cache->get($cacheKey)) {
                    $results['step2_top_rewards'][$server->id] = [
                        'success' => false,
                        'message' => 'Награды уже начислены недавно (кэш активен)',
                    ];
                    $overallSuccess = false;
                    continue;
                }

                Yii::$app->cache->set($cacheKey, 1, 1*60);
                ini_set('memory_limit', '512M');

                $wipe = $server->currentWipe();
                $tops = UserTop::getUserTops($server, $wipe);
                $tgMessage = [];

                foreach ($tops as $top) {
                    $value = $top['label'];
                    foreach ($top['items'] as $i => $item) {
                        $user = User::findBySteamId($item['steam_id'], false, 'top');
                        
                        // Проверяем, что пользователь найден
                        if (empty($user)) {
                            Yii::warning("User not found for steam_id: {$item['steam_id']} in top rewards", __METHOD__);
                            continue;
                        }
                        
                        // Получаем или создаем баланс пользователя
                        $personalBalance = $user->getPersonalBalance();
                        if (empty($personalBalance) || empty($personalBalance->id)) {
                            Yii::warning("Personal balance not found for user ID: {$user->id}, steam_id: {$item['steam_id']}", __METHOD__);
                            continue;
                        }
                        
                        $profit = new Profit();
                        $profit->status = 1;
                        $profit->type = Profit::TYPE_TOP;
                        $profit->amount = $item['amount'];
                        $profit->user_balance_id = $personalBalance->id;
                        $profit->comment = "Награда за первое место в топе \"{$value}\"";
                        if ($i === 1) {
                            $profit->comment = "Награда за второе место в топе \"{$value}\"";
                        } elseif ($i === 2) {
                            $profit->comment = "Награда за третье место в топе \"{$value}\"";
                        }
                        if (!empty($user->telegram_chat_id)) {
                            $text = "🥇 Награда за первое место в топе \"{$value}\" - <b>{$profit->amount} РУБ</b>";
                            if ($i === 1) {
                                $text = "🥈 Награда за второе место в топе \"{$value}\" - <b>{$profit->amount} РУБ</b>";
                            } elseif ($i === 2) {
                                $text = "🥉 Награда за третье место в топе \"{$value}\" - <b>{$profit->amount} РУБ</b>";
                            }
                            if (!empty($tgMessage[$user->steam_id])) {
                                $tgMessage[$user->steam_id] .= PHP_EOL . $text;
                            } else {
                                $tgMessage[$user->steam_id] = "Вам начислены награды за ТОП на сервере "
                                    . $server->name . PHP_EOL . $text;
                            }
                        }
                        $profit->created_at = date('Y-m-d H:i:s');
                        $profit->save(false);
                    }
                }

                if (YII_ENV_PROD) {
                    foreach ($tgMessage as $steamId => $message) {
                        $user = User::findBySteamId($steamId, false, 'top2');
                        Yii::$app->personalBotTelegram->sendMessage($user->telegram_chat_id, $message);
                    }
                }

                $results['step2_top_rewards'][$server->id] = [
                    'success' => true,
                    'message' => 'Награды за топы успешно начислены',
                ];
            } catch (\Exception $e) {
                $results['step2_top_rewards'][$server->id] = [
                    'success' => false,
                    'message' => 'Ошибка: ' . $e->getMessage(),
                ];
                $overallSuccess = false;
            }
        }

        // Если этап 2 не прошел, останавливаемся
        if (!$overallSuccess) {
            return [
                'success' => false,
                'message' => 'Ошибка на этапе 2: Начисление наград за топы',
                'results' => $results,
            ];
        }

        // Этап 3: Обнуление промокода WIPE
        $results['step3_reset_promocode'] = [];
        try {
            /** @var UserPromocode[] $uPromocodes */
            $uPromocodes = UserPromocode::find()
                ->andWhere(['promocode_id' => 2])
                ->all();

            $deletedCount = 0;
            foreach ($uPromocodes as $item) {
                $item->delete();
                $deletedCount++;
            }

            $results['step3_reset_promocode'] = [
                'success' => true,
                'message' => "Промокод WIPE обнулен. Удалено записей: {$deletedCount}",
            ];
        } catch (\Exception $e) {
            $results['step3_reset_promocode'] = [
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
            $overallSuccess = false;
        }

        // Если этап 3 не прошел, останавливаемся
        if (!$overallSuccess) {
            return [
                'success' => false,
                'message' => 'Ошибка на этапе 3: Обнуление промокода',
                'results' => $results,
            ];
        }

        // Этап 4: Выполнение RCON команды
        $results['step4_rcon'] = [];
        if (!empty($rconCommand)) {
            $serverTags = array_map(function($server) {
                return $server->tag;
            }, $servers);

            try {
                $rconResults = RconTasks::executeWithResults($rconCommand, $serverTags);
                
                foreach ($rconResults as $tag => $rconResult) {
                    $server = $rconResult['server'];
                    if (!empty($rconResult['error'])) {
                        $results['step4_rcon'][$server->id] = [
                            'success' => false,
                            'message' => 'Ошибка RCON: ' . $rconResult['error'],
                        ];
                        $overallSuccess = false;
                    } else {
                        $results['step4_rcon'][$server->id] = [
                            'success' => true,
                            'message' => 'RCON команда выполнена успешно',
                            'result' => $rconResult['result'],
                        ];
                    }
                }
            } catch (\Exception $e) {
                foreach ($servers as $server) {
                    $results['step4_rcon'][$server->id] = [
                        'success' => false,
                        'message' => 'Ошибка: ' . $e->getMessage(),
                    ];
                }
                $overallSuccess = false;
            }
        } else {
            // RCON команда не указана - пропускаем этап
            foreach ($servers as $server) {
                $results['step4_rcon'][$server->id] = [
                    'success' => true,
                    'message' => 'Пропущено (команда не указана)',
                ];
            }
        }

        return [
            'success' => $overallSuccess,
            'message' => $overallSuccess ? 'Вайп успешно выполнен для всех серверов' : 'Вайп выполнен с ошибками',
            'results' => $results,
        ];
    }

    /**
     * Страница для выбора сервера и выполнения вайпа через RCON команду
     */
    public function actionRunWipe()
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()
            ->cache(30)
            ->andWhere(['IN', 'status', [Servers::STATUS_NOACTIVE, Servers::STATUS_ACTIVE]])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        return $this->render('run-wipe', [
            'servers' => $servers,
        ]);
    }

    /**
     * Страница подтверждения вайпа с отображением параметров и команды
     */
    public function actionConfirmWipe()
    {
        $serverId = Yii::$app->request->get('server_id');
        $wipeType = Yii::$app->request->get('wipe_type', 'wipe'); // 'wipe' или 'global'

        if (empty($serverId)) {
            Yii::$app->session->addFlash('danger', 'Сервер не выбран');
            return $this->redirect(['run-wipe']);
        }

        /** @var Servers $server */
        $server = Servers::findOne($serverId);
        if (!$server) {
            Yii::$app->session->addFlash('danger', 'Сервер не найден');
            return $this->redirect(['run-wipe']);
        }

        // Получаем данные карты
        $mapList = null;
        $seed = null;
        $worldsize = null;

        if (!empty($server->map_list_id)) {
            $mapList = MapList::findOne($server->map_list_id);
            if ($mapList) {
                $seed = $mapList->seed;
                $worldsize = $mapList->size_int;
            }
        }

        // Формируем параметры команды
        $preset = $wipeType; // 'wipe' или 'global'
        $gamemode = $server->game_mode ?? 'vanilla';
        $description = $server->wipe_server_description ?? '';
        // Заменяем переносы строк на \n
        $description = str_replace(["\r\n", "\r", "\n"], "\\n", $description);
        $maxplayers = $server->max ?? 0;
        $hostname = $server->wipe_server_name ?? '';
        $tags = $server->monitoring_tags ?? 'weekly, vanilla, EU, tut';

        // Формируем команду: autowipe.runnow <seed> <worldsize> [имя_пресета] [gamemode] [description] [maxplayers] [hostname] [tags]
        $commandParts = ['autowipe.runnow'];

        // Обязательные параметры
        $commandParts[] = $seed !== null ? $seed : '0';
        $commandParts[] = $worldsize !== null ? $worldsize : '0';

        // Опциональные параметры в правильном порядке
        // Если хотим передать параметр дальше, нужно передать все предыдущие (даже пустые)
        // Но обычно можно пропускать, если они не нужны
        $commandParts[] = $preset; // имя_пресета
        $commandParts[] = $gamemode; // gamemode
        $commandParts[] = $description; // description
        $commandParts[] = $maxplayers; // maxplayers
        $commandParts[] = $hostname; // hostname
        $commandParts[] = $tags; // tags

        $rconCommand = implode(' ', array_map(function($part) {
            // Экранируем пробелы в параметрах
            if (strpos($part, ' ') !== false || strpos($part, '\\n') !== false) {
                return '"' . str_replace('"', '\\"', $part) . '"';
            }
            return $part;
        }, $commandParts));

        return $this->render('confirm-wipe', [
            'server' => $server,
            'wipeType' => $wipeType,
            'mapList' => $mapList,
            'seed' => $seed,
            'worldsize' => $worldsize,
            'gamemode' => $gamemode,
            'description' => $server->wipe_server_description ?? '',
            'maxplayers' => $maxplayers,
            'hostname' => $hostname,
            'tags' => $tags,
            'rconCommand' => $rconCommand,
        ]);
    }

    /**
     * Выполнение вайпа через RCON команду
     */
    public function actionExecuteRunWipe()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $serverId = Yii::$app->request->post('server_id');
        $wipeType = Yii::$app->request->post('wipe_type', 'wipe');

        if (empty($serverId)) {
            return [
                'success' => false,
                'message' => 'Сервер не выбран',
            ];
        }

        /** @var Servers $server */
        $server = Servers::findOne($serverId);
        if (!$server) {
            return [
                'success' => false,
                'message' => 'Сервер не найден',
            ];
        }

        // Получаем данные карты
        $mapList = null;
        $seed = null;
        $worldsize = null;

        if (!empty($server->map_list_id)) {
            $mapList = MapList::findOne($server->map_list_id);
            if ($mapList) {
                $seed = $mapList->seed;
                $worldsize = $mapList->size_int;
            }
        }

        // Формируем параметры команды
        $preset = $wipeType;
        $gamemode = $server->game_mode ?? 'vanilla';
        $description = $server->wipe_server_description ?? '';
        // Заменяем переносы строк на \n
        $description = str_replace(["\r\n", "\r", "\n"], "\\n", $description);
        $maxplayers = $server->max ?? 0;
        $hostname = $server->wipe_server_name ?? '';
        $tags = $server->monitoring_tags ?? 'weekly, vanilla, EU, tut';

        // Формируем команду: autowipe.runnow <seed> <worldsize> [имя_пресета] [gamemode] [description] [maxplayers] [hostname] [tags]
        $commandParts = ['autowipe.runnow'];

        // Обязательные параметры
        $commandParts[] = $seed !== null ? $seed : '0';
        $commandParts[] = $worldsize !== null ? $worldsize : '0';

        // Опциональные параметры в правильном порядке
        $commandParts[] = $preset; // имя_пресета
        $commandParts[] = $gamemode; // gamemode
        $commandParts[] = $description; // description
        $commandParts[] = $maxplayers; // maxplayers
        $commandParts[] = $hostname; // hostname
        $commandParts[] = $tags; // tags

        $rconCommand = implode(' ', array_map(function($part) {
            // Экранируем пробелы в параметрах
            if (strpos($part, ' ') !== false || strpos($part, '\\n') !== false) {
                return '"' . str_replace('"', '\\"', $part) . '"';
            }
            return $part;
        }, $commandParts));

        try {
            // Выполняем команду через RCON
            $results = RconTasks::executeWithResults($rconCommand, [$server->tag]);

            $result = $results[$server->tag] ?? null;

            if ($result && !empty($result['error'])) {
                return [
                    'success' => false,
                    'message' => 'Ошибка RCON: ' . $result['error'],
                    'command' => $rconCommand,
                ];
            }

            return [
                'success' => true,
                'message' => 'Вайп успешно выполнен',
                'command' => $rconCommand,
                'result' => $result['result'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
                'command' => $rconCommand,
            ];
        }
    }

    /**
     * @param Servers[] $servers
     * @param string $wipe
     * @return array
     */
    private function buildTopRewardsPlan(array $servers, string $wipe): array
    {
        $rows = [];
        $payableRows = [];
        $totalAmount = 0;
        $payableAmount = 0;
        $skippedCount = 0;

        foreach ($servers as $server) {
            $tops = UserTop::getUserTops($server, $wipe);
            foreach ($tops as $top) {
                $label = (string)$top['label'];
                foreach ($top['items'] as $position => $item) {
                    $steamId = (string)$item['steam_id'];
                    $amount = (int)$item['amount'];
                    $user = User::findBySteamId($steamId, false, 'top-rewards-plan');
                    $balance = $user ? $user->getPersonalBalance() : null;

                    $comment = "Награда за первое место в топе \"{$label}\"";
                    if ($position === 1) {
                        $comment = "Награда за второе место в топе \"{$label}\"";
                    } elseif ($position === 2) {
                        $comment = "Награда за третье место в топе \"{$label}\"";
                    }

                    $skipReason = null;
                    if (empty($user)) {
                        $skipReason = 'Пользователь не найден';
                    } elseif (empty($balance) || empty($balance->id)) {
                        $skipReason = 'Не найден персональный баланс';
                    }

                    $row = [
                        'server_id' => $server->id,
                        'server_name' => $server->name,
                        'server_tag' => $server->tag,
                        'wipe' => $wipe,
                        'label' => $label,
                        'position' => $position + 1,
                        'steam_id' => $steamId,
                        'username' => $item['username'] ?? $steamId,
                        'amount' => $amount,
                        'comment' => $comment,
                        'user_id' => $user ? $user->id : null,
                        'user_balance_id' => $balance ? $balance->id : null,
                        'telegram_chat_id' => $user ? $user->telegram_chat_id : null,
                        'can_pay' => $skipReason === null,
                        'skip_reason' => $skipReason,
                    ];

                    $rows[] = $row;
                    $totalAmount += $amount;

                    if ($row['can_pay']) {
                        $payableRows[] = $row;
                        $payableAmount += $amount;
                    } else {
                        $skippedCount++;
                    }
                }
            }
        }

        return [
            'wipe' => $wipe,
            'rows' => $rows,
            'payableRows' => $payableRows,
            'totalAmount' => $totalAmount,
            'payableAmount' => $payableAmount,
            'totalCount' => count($rows),
            'payableCount' => count($payableRows),
            'skippedCount' => $skippedCount,
        ];
    }

    /**
     * @param array $plan
     * @return array{count:int,amount:int}
     */
    private function applyTopRewardsPlan(array $plan): array
    {
        $createdCount = 0;
        $createdAmount = 0;
        $tgMessage = [];

        foreach ($plan['payableRows'] as $row) {
            $profit = new Profit();
            $profit->status = 1;
            $profit->type = Profit::TYPE_TOP;
            $profit->amount = (int)$row['amount'];
            $profit->user_balance_id = (int)$row['user_balance_id'];
            $profit->comment = $row['comment'];
            $profit->created_at = date('Y-m-d H:i:s');
            $profit->save(false);

            $createdCount++;
            $createdAmount += (int)$row['amount'];

            if (!empty($row['telegram_chat_id'])) {
                $emoji = '🥇';
                if ((int)$row['position'] === 2) {
                    $emoji = '🥈';
                } elseif ((int)$row['position'] === 3) {
                    $emoji = '🥉';
                }
                $text = "{$emoji} {$row['comment']} - <b>{$row['amount']} РУБ</b>";
                if (!empty($tgMessage[$row['steam_id']])) {
                    $tgMessage[$row['steam_id']] .= PHP_EOL . $text;
                } else {
                    $tgMessage[$row['steam_id']] = "Вам начислены награды за ТОП на сервере {$row['server_name']}" . PHP_EOL . $text;
                }
            }
        }

        if (YII_ENV_PROD) {
            foreach ($tgMessage as $steamId => $message) {
                $user = User::findBySteamId($steamId, false, 'top-rewards-notify');
                if (!empty($user) && !empty($user->telegram_chat_id)) {
                    Yii::$app->personalBotTelegram->sendMessage($user->telegram_chat_id, $message);
                }
            }
        }

        return [
            'count' => $createdCount,
            'amount' => $createdAmount,
        ];
    }

}
