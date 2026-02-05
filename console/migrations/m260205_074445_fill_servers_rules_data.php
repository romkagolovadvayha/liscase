<?php

use yii\db\Migration;

/**
 * Class m260205_074445_fill_servers_rules_data
 */
class m260205_074445_fill_servers_rules_data extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Создаем категории правил
        $categories = [
            [
                'name' => 'Права и обязанности сторон',
                'icon' => 'shield',
                'sort' => 1,
            ],
            [
                'name' => 'Правила для игроков',
                'icon' => 'user-shield',
                'sort' => 2,
            ],
            [
                'name' => 'Правила лимита игроков в команде',
                'icon' => 'person-add',
                'sort' => 3,
            ],
            [
                'name' => 'Команды на сервере',
                'icon' => 'terminal',
                'sort' => 4,
            ],
        ];

        $categoryIds = [];
        foreach ($categories as $category) {
            $this->insert('{{%servers_rules_categories}}', [
                'name' => $category['name'],
                'icon' => $category['icon'],
                'sort' => $category['sort'],
                'created_at' => time(),
                'updated_at' => time(),
            ]);
            $categoryIds[$category['name']] = $this->db->getLastInsertID();
        }

        // Правила для категории "Права и обязанности сторон"
        $rightsRules = [
            'Администрация оставляет за собой право делать любые изменения на сервере без предварительного уведомления игроков.',
            'Незнание правил не освобождает от ответственности за их нарушение. Играя на сервере, Вы автоматически соглашаетесь с правилами.',
            'Все игроки должны самостоятельно отслеживать изменения в правилах.',
            'Администрация в праве в любой момент, без объяснения причин, прекратить предоставление доступа к серверу конкретному пользователю или подсети в целом.',
            'Администрация не обязана производить возврат вещей, учетных записей, персонажей или их параметров, утерянных игроками по любой возможной причине.',
            'Ответственность несет владелец аккаунта, независимо от того, кто совершал действия под данным аккаунтом.',
            'Все игроки обязаны сами отслеживать отсутствие запрещенного программного обеспечения',
            'Решения и наказания от администрации являются финальной точкой в спорах и разбирательствах, дальнейшие обсуждения решений могут быть удалены, а участники могут получить блокировку чата.',
            'Администрация не вмешивается в игровой процесс. Исключением являются моменты, на мнение администрации, требующие вмешательства.',
            'Все пожертвования в магазин сервера не подлежат возврату или обмену, пополнение баланса сугубо личное желание каждого игрока. Услуги или наборы не исчезают во время вайпа, а после активации действуют ровно столько, сколько написано в описании данной услуги или набора.',
            'Администрация не восстанавливает вещи в случае убийства, бага, отката, вайпа, обмана и т.п.',
            'Администрация не играет на сервере, не выдает/забирает ресурсы.',
        ];

        foreach ($rightsRules as $index => $rule) {
            $this->insert('{{%servers_rules}}', [
                'category_id' => $categoryIds['Права и обязанности сторон'],
                'title' => null,
                'content' => $rule,
                'punishment' => null,
                'sort' => $index + 1,
                'created_at' => time(),
                'updated_at' => time(),
            ]);
        }

        // Правила для категории "Правила для игроков"
        $playerRules = [
            ['content' => 'Читы | макросы на компьютере или их покупка за последний год = бан. Нас не волнует используете их или нет.', 'punishment' => 'ban'],
            ['content' => 'Клан лидеры обязуются проверять соклановцев на читы, в противном случае в бан может уйти весь клан.', 'punishment' => 'ban'],
            ['content' => 'Запрещена любая помощь читерам и игра с ними, багоюзерам и тем кто обошел бан.', 'punishment' => 'ban 14d'],
            ['content' => 'Запрещено юзать/разглашать баги!', 'punishment' => 'mute \\ ban 3d'],
            ['content' => 'Запрещено покупать или заказывать читеров', 'punishment' => 'ban'],
            ['content' => 'Запрещено играть у нас с действующим баном на других проектах за нечестную игру', 'punishment' => 'ban'],
            ['content' => 'Запрещено оскорблять и упоминать в любом виде родных и родственников', 'punishment' => 'mute'],
            ['content' => 'Запрещено разжигать межнациональные розни и ввести политические споры', 'punishment' => 'mute'],
            ['content' => 'Запрещено продавать/покупать вещи за реал у игроков!', 'punishment' => 'ban 3d'],
            ['content' => 'Запрещено использовать ники админов!', 'punishment' => 'предупреждение \\ ban 7d'],
            ['content' => 'Запрещено вводить игроков в заблуждение выдавая себя за администрацию.', 'punishment' => 'mute \\ ban'],
            ['content' => 'Запрещены оскорбления администрации проекта и сервера.', 'punishment' => 'mute \\ ban'],
            ['content' => 'Выход из игры во время проверки, без одобрения администратора, может быть самой большой вашей ошибкой. Выключение Discord во время проверки, попадает под тот же пункт.', 'punishment' => 'ban'],
            ['content' => 'Запрещен стримснайп официальных медиа проекта - использование игровой информации, которая была получена на трансляции другого игрока или целенаправленное преследование игрока, ведущего трансляцию.', 'punishment' => 'kick \\ ban'],
            ['content' => 'Запрещена травля отдельных лиц и целенаправленное домогательство | преследование личности игрока и публичное раскрытие личных данных', 'punishment' => 'mute 7d \\ ban 7d'],
            ['content' => 'Запрещено целенаправленно абузить топ игроков для получения выгоды. (договорённость нескольких игроков для набора убийств и смертей, набивание смертей на гантрапах, турелях и т.п., тушение сачелей водой и т.д.) АФК, убийство ботов, фарм животных - не абузится', 'punishment' => 'аннулирование статистики'],
            ['content' => 'Все проверки проходят через AnyDesk и Discord, отказ будет приравниваться к отказу от проверки', 'punishment' => 'ban'],
            ['content' => 'Запрещено ставить одинаковые ники, в связи с путанницей игроков', 'punishment' => 'на усмотрение администрации'],
        ];

        foreach ($playerRules as $index => $rule) {
            $this->insert('{{%servers_rules}}', [
                'category_id' => $categoryIds['Правила для игроков'],
                'title' => null,
                'content' => $rule['content'],
                'punishment' => $rule['punishment'],
                'sort' => $index + 1,
                'created_at' => time(),
                'updated_at' => time(),
            ]);
        }

        // Добавляем правило для PvE сервера
        $this->insert('{{%servers_rules}}', [
            'category_id' => $categoryIds['Правила для игроков'],
            'title' => null,
            'content' => 'Запрещено обносить чужие дома забором',
            'punishment' => 'снос построек',
            'sort' => count($playerRules) + 1,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $pveRuleId = $this->db->getLastInsertID();

        // Привязываем правило PvE к серверу с tag='pve'
        // Проверяем, что связующая таблица существует
        $tableSchema = $this->db->schema->getTableSchema('{{%servers_rules_servers}}');
        if ($tableSchema) {
            $pveServer = $this->db->createCommand('SELECT id FROM {{%servers}} WHERE tag = :tag', [':tag' => 'pve'])->queryOne();
            if ($pveServer) {
                $this->insert('{{%servers_rules_servers}}', [
                    'rule_id' => $pveRuleId,
                    'server_id' => $pveServer['id'],
                    'created_at' => time(),
                ]);
            }
        }

        // Правила для категории "Правила лимита игроков в команде"
        // Эти правила будут привязаны к серверам с team_limit через админку
        $teamLimitRules = [
            ['content' => 'Запрещено кооперироваться больше {PARAMS_LIMIT_PEOPLE} людей. Исключением считается: сейв зона.', 'punishment' => 'предупреждение \\ ban до конца вайпа'],
            ['content' => 'Запрещено соседям защищать друг друга при рейде или перестрелке, чем разрешено правилами сервера.', 'punishment' => 'предупреждение \\ ban до конца вайпа'],
            ['content' => 'Смена членов команды не чаще, чем раз в сутки', 'punishment' => 'предупреждение \\ ban до конца вайпа'],
            ['content' => 'Запрещены альянсы игроков, если их общее количество превышает {PARAMS_LIMIT_PEOPLE} человека', 'punishment' => 'предупреждение \\ ban до конца вайпа'],
            ['content' => 'Заключение мира между собой, не убивать друг друга, обмен (кроме сейв-зоны), подсад и т.д', 'punishment' => 'предупреждение \\ ban до конца вайпа'],
        ];

        $teamLimitRuleIds = [];
        foreach ($teamLimitRules as $index => $rule) {
            $this->insert('{{%servers_rules}}', [
                'category_id' => $categoryIds['Правила лимита игроков в команде'],
                'title' => null,
                'content' => $rule['content'],
                'punishment' => $rule['punishment'],
                'sort' => $index + 1,
                'created_at' => time(),
                'updated_at' => time(),
            ]);
            $teamLimitRuleIds[] = $this->db->getLastInsertID();
        }

        // Привязываем правила лимита команды к серверам с team_limit
        // Проверяем, что связующая таблица существует
        $tableSchema = $this->db->schema->getTableSchema('{{%servers_rules_servers}}');
        if ($tableSchema) {
            $serversWithTeamLimit = $this->db->createCommand('SELECT id FROM {{%servers}} WHERE team_limit > 0')->queryAll();
            foreach ($serversWithTeamLimit as $server) {
                foreach ($teamLimitRuleIds as $ruleId) {
                    $this->insert('{{%servers_rules_servers}}', [
                        'rule_id' => $ruleId,
                        'server_id' => $server['id'],
                        'created_at' => time(),
                    ]);
                }
            }
        }

        // Правила для категории "Команды на сервере"
        // Базовые команды, которые могут быть на серверах
        $commandsRules = [
            ['content' => '<span class="rules_command">/report</span> - Отправить репорт на игрока', 'punishment' => null, 'command' => 'report'],
            ['content' => '<span class="rules_command">/time</span> - Время на сервере', 'punishment' => null, 'command' => 'time'],
            ['content' => '<span class="rules_command">/pop</span> - Онлайн на сервере', 'punishment' => null, 'command' => 'pop'],
            ['content' => '<span class="rules_command">/wipe</span> - Информация о вайпе', 'punishment' => null, 'command' => 'wipe'],
            ['content' => '<span class="rules_command">/store</span> - Открыть корзину товаров', 'punishment' => null, 'command' => 'store'],
            ['content' => '<span class="rules_command">/sil URL_IMAGE</span> - Загрузить свое изображение в фоторамку', 'punishment' => null, 'command' => 'sil'],
            ['content' => '<span class="rules_command">/rate</span> - Текущие рейты', 'punishment' => null, 'command' => 'xrates'],
            ['content' => '<span class="rules_command">/fmenu</span> - Друзья', 'punishment' => null, 'command' => 'fmenu'],
            ['content' => '<span class="rules_command">/remove</span> - Ремув стен', 'punishment' => null, 'command' => 'remove'],
            ['content' => '<span class="rules_command">/vlock</span> - Поставить код лок на коптер/лошадь', 'punishment' => null, 'command' => 'vlock'],
            ['content' => '<span class="rules_command">/help</span> - Помощь', 'punishment' => null, 'command' => 'help'], // help всегда показывается
        ];

        $commandRuleIds = [];
        foreach ($commandsRules as $index => $rule) {
            $this->insert('{{%servers_rules}}', [
                'category_id' => $categoryIds['Команды на сервере'],
                'title' => null,
                'content' => $rule['content'],
                'punishment' => $rule['punishment'],
                'sort' => $index + 1,
                'created_at' => time(),
                'updated_at' => time(),
            ]);
            $ruleId = $this->db->getLastInsertID();
            $commandRuleIds[$rule['command']] = $ruleId;
        }

        // Привязываем команды к серверам, где они включены
        // Проверяем, что связующая таблица существует
        $tableSchema = $this->db->schema->getTableSchema('{{%servers_rules_servers}}');
        if ($tableSchema) {
            $allServers = $this->db->createCommand('SELECT id, commands FROM {{%servers}}')->queryAll();
            foreach ($allServers as $server) {
                $commands = json_decode($server['commands'], true);
                if (!is_array($commands)) {
                    $commands = [];
                }

                // /help показывается всегда
                if (isset($commandRuleIds['help'])) {
                    $this->insert('{{%servers_rules_servers}}', [
                        'rule_id' => $commandRuleIds['help'],
                        'server_id' => $server['id'],
                        'created_at' => time(),
                    ]);
                }

                // Остальные команды привязываем только если они есть в commands сервера
                $commandMapping = [
                    'report' => 'report',
                    'time' => 'time',
                    'pop' => 'pop',
                    'wipe' => 'wipe',
                    'store' => 'store',
                    'sil' => 'sil',
                    'xrates' => 'xrates',
                    'fmenu' => 'fmenu',
                    'remove' => 'remove',
                    'vlock' => 'vlock',
                ];

                foreach ($commandMapping as $commandKey => $commandName) {
                    if (in_array($commandName, $commands) && isset($commandRuleIds[$commandKey])) {
                        $this->insert('{{%servers_rules_servers}}', [
                            'rule_id' => $commandRuleIds[$commandKey],
                            'server_id' => $server['id'],
                            'created_at' => time(),
                        ]);
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->delete('{{%servers_rules}}');
        $this->delete('{{%servers_rules_categories}}');
    }
}

