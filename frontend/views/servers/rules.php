<?php

/** @var yii\web\View $this */
/** @var Servers $server */

use common\models\servers\Servers;

$this->title = Yii::t('common', 'Правила сервера') . ' ' . Yii::t('database', $server->name);

\frontend\assets\LastDropAsset::register($this);

$commands = json_decode($server->commands, 1);

/** @var Servers[] $servers */
$servers = Servers::find()
                  ->cache(30)
                  ->all();
?>
<div class="last_drops_wrapper">
    <?php if ($this->beginCache('_last_drops' . Yii::$app->language, ['duration' => 10])): ?>
        <?= $this->render('@frontend/views/widgets/_last_drops'); ?>
        <?php $this->endCache(); ?>
    <?php endif; ?>
</div>

<div class="container-fluid mb-5">
    <div class="main_wrap server_info_page">
        <aside>
            <?= $this->render('@frontend/views/widgets/_servers'); ?>
            <?php echo $this->render('@frontend/views/layouts/_promocode_line'); ?>
            <?= $this->render('@frontend/views/widgets/_live'); ?>
        </aside>
        <main id="main" role="main">
            <div class="main_child">
                <div class="page_content">
                    <div class="page_content_header">
                        <h1><?=$this->title?></h1>
                    </div>
                    <div class="page_content_body">
                        <div class="page_stats_servers_wrap">
                            <div class="page_stats_servers">
                                <?php foreach ($servers as $item): ?>
                                    <a href="/servers/rules?server=<?=$item->tag?>" class="page_stats_servers_item<?=$item->tag === $server->tag ? ' page_stats_servers_item_active' : ''?>">
                                        <?=Yii::t('database', $item->name)?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="rules">
                            <p class="rules_snoska">
                                <?=Yii::t('common', 'Правила являются обязательными для всех игроков, за их соблюдением следит Администрация сервера.')?>
                                <br/><?=Yii::t('common', 'Наказание за нарушение правил определяется администрацией проекта, в зависимости от нарушения, вплоть до полного бана игрока без возможности амнистии.')?>
                                <br/><?=Yii::t('common', 'Предупреждение выдается только 1 раз и навсегда')?>
                            </p>
                            <ol>
                                <li class="not_num">
                                    <span class="rules_title"><?=Yii::t('common', 'Права и обязанности сторон')?></span>
                                    <ol>
                                        <li><?=Yii::t('common', 'Администрация оставляет за собой право делать любые изменения на сервере без предварительного уведомления игроков.')?></li>
                                        <li><?=Yii::t('common', 'Незнание правил не освобождает от ответственности за их нарушение. Играя на сервере, Вы автоматически соглашаетесь с правилами.')?></li>
                                        <li><?=Yii::t('common', 'Все игроки должны самостоятельно отслеживать изменения в правилах.')?></li>
                                        <li><?=Yii::t('common', 'Администрация в праве в любой момент, без объяснения причин, прекратить предоставление доступа к серверу конкретному пользователю или подсети в целом.')?></li>
                                        <li><?=Yii::t('common', 'Администрация не обязана производить возврат вещей, учетных записей, персонажей или их параметров, утерянных игроками по любой возможной причине.')?></li>
                                        <li><?=Yii::t('common', 'Ответственность несет владелец аккаунта, независимо от того, кто совершал действия под данным аккаунтом.')?></li>
                                        <li><?=Yii::t('common', 'Все игроки обязаны сами отслеживать отсутствие запрещенного программного обеспечения')?></li>
                                        <li><?=Yii::t('common', 'Решения и наказания от администрации являются финальной точкой в спорах и разбирательствах, дальнейшие обсуждения решений могут быть удалены, а участники могут получить блокировку чата.')?></li>
                                        <li><?=Yii::t('common', 'Администрация не вмешивается в игровой процесс. Исключением являются моменты, на мнение администрации, требующие вмешательства.')?></li>
                                        <li><?=Yii::t('common', 'Все пожертвования в магазин сервера не подлежат возврату или обмену, пополнение баланса сугубо личное желание каждого игрока. Услуги или наборы не исчезают во время вайпа, а после активации действуют ровно столько, сколько написано в описании данной услуги или набора.')?></li>
                                        <li><?=Yii::t('common', 'Администрация не восстанавливает вещи в случае убийства, бага, отката, вайпа, обмана и т.п.')?></li>
                                        <li><?=Yii::t('common', 'Администрация не играет на сервере, не выдает/забирает ресурсы.')?></li>
                                    </ol>
                                </li>
                                <li class="not_num">
                                    <span class="rules_title"><?=Yii::t('common', 'Правила для игроков')?></span>
                                    <ol>
                                        <li><?=Yii::t('common', 'Читы | макросы на компьютере или их покупка за последний год = бан. Нас не волнует используете их или нет.')?> <span class="rules_punishment">[ban]</span></li>
                                        <li><?=Yii::t('common', 'Клан лидеры обязуются проверять соклановцев на читы, в противном случае в бан может уйти весь клан.')?> <span class="rules_punishment">[ban]</span></li>
                                        <li><?=Yii::t('common', 'Запрещена любая помощь читерам и игра с ними, багоюзерам и тем кто обошел бан.')?> <span class="rules_punishment">[ban 14d]</span></li>
                                        <li><?=Yii::t('common', 'Запрещено юзать/разглашать баги!')?> <span class="rules_punishment">[mute \ ban 3d]</span></li>
                                        <li><?=Yii::t('common', 'Запрещено покупать или заказывать читеров')?> <span class="rules_punishment">[ban]</span></li>
                                        <li><?=Yii::t('common', 'Запрещено оскорблять и упоминать в любом виде родных и родственников')?> <span class="rules_punishment">[mute]</span></li>
                                        <li><?=Yii::t('common', 'Запрещено разжигать межнациональные розни и ввести политические споры')?> <span class="rules_punishment">[mute]</span></li>
                                        <li><?=Yii::t('common', 'Запрещено продавать/покупать вещи за реал у игроков!')?> <span class="rules_punishment">[ban 3d]</span></li>
                                        <li><?=Yii::t('common', 'Запрещено использовать ники админов!')?> <span class="rules_punishment">[<?=Yii::t('common', 'предупреждение')?> \ ban 7d]</span></li>
                                        <li><?=Yii::t('common', 'Запрещено вводить игроков в заблуждение выдавая себя за администрацию.')?> <span class="rules_punishment">[mute \ ban]</span></li>
                                        <li><?=Yii::t('common', 'Запрещены оскорбления администрации проекта и сервера.')?> <span class="rules_punishment">[mute \ ban]</span></li>
                                        <li><?=Yii::t('common', 'Выход из игры во время проверки, без одобрения администратора, может быть самой большой вашей ошибкой. Выключение Discord во время проверки, попадает под тот же пункт.')?> <span class="rules_punishment">[ban]</span></li>
                                        <li><?=Yii::t('common', 'Запрещен стримснайп официальных медиа проекта - использование игровой информации, которая была получена на трансляции другого игрока или целенаправленное преследование игрока, ведущего трансляцию.')?> <span class="rules_punishment">[kick \ ban]</span></li>
                                        <li><?=Yii::t('common', 'Запрещена травля отдельных лиц и целенаправленное домогательство | преследование личности игрока и публичное раскрытие личных данных')?> <span class="rules_punishment">[mute 7d \ ban 7d]</span></li>
                                        <li><?=Yii::t('common', 'Запрещено целенаправленно абузить топ игроков для получения выгоды. (договорённость нескольких игроков для набора убийств и смертей, набивание смертей на гантрапах, турелях и т.п., тушение сачелей водой и т.д.) АФК, убийство ботов, фарм животных - не абузится')?> <span class="rules_punishment">[<?=Yii::t('common', 'аннулирование статистики')?>]</span></li>
                                        <li><?=Yii::t('common', 'Запрещено обносить сейв зоны забором')?> <span class="rules_punishment">[<?=Yii::t('common', 'снос построек')?>]</span></li>
                                        <?php if ($server->tag === 'pve'): ?>
                                            <li><?=Yii::t('common', 'Запрещено обносить чужие дома забором')?> <span class="rules_punishment">[<?=Yii::t('common', 'снос построек')?>]</span></li>
                                        <?php endif; ?>
                                    </ol>
                                </li>
                                <?php if (!empty($server->team_limit)): ?>
                                <li class="not_num">
                                    <span class="rules_title"><?=Yii::t('common', 'Правила лимита игроков в команде')?></span>
                                    <ol>
                                        <li><?=Yii::t('common', 'Запрещено кооперироваться больше {PARAMS_LIMIT_PEOPLE} людей. Исключением считается: сейв зона.', ['PARAMS_LIMIT_PEOPLE' => $server->team_limit])?> <span class="rules_punishment">[<?=Yii::t('common', 'предупреждение')?> \ <?=Yii::t('common', 'ban до конца вайпа')?>]</span></li>
                                        <li><?=Yii::t('common', 'Запрещено соседям защищать друг друга при рейде или перестрелке, чем разрешено правилами сервера.')?> <span class="rules_punishment">[<?=Yii::t('common', 'предупреждение')?> \ <?=Yii::t('common', 'ban до конца вайпа')?>]</span></li>
                                        <li><?=Yii::t('common', 'Смена членов команды не чаще, чем раз в сутки')?> <span class="rules_punishment">[<?=Yii::t('common', 'предупреждение')?> \ <?=Yii::t('common', 'ban до конца вайпа')?>]</span></li>
                                        <li><?=Yii::t('common', 'Запрещены альянсы игроков, если их общее количество превышает {PARAMS_LIMIT_PEOPLE} человека', ['PARAMS_LIMIT_PEOPLE' => $server->team_limit])?> <span class="rules_punishment">[<?=Yii::t('common', 'предупреждение')?> \ <?=Yii::t('common', 'ban до конца вайпа')?>]</span></li>
                                        <li><?=Yii::t('common', 'Заключение мира между собой, не убивать друг друга, обмен (кроме сейв-зоны), подсад и т.д')?> <span class="rules_punishment">[<?=Yii::t('common', 'предупреждение')?> \ <?=Yii::t('common', 'ban до конца вайпа')?>]</span></li>
                                    </ol>
                                </li>
                                <?php endif; ?>
                                <li class="not_num rules_commands">
                                    <span class="rules_title"><?=Yii::t('common', 'Команды на сервере')?></span>
                                    <ol>
                                        <?php if (in_array('report', $commands)): ?>
                                            <li><span class="rules_command">/report</span> - <?=Yii::t('common', 'Отправить репорт на игрока')?></li>
                                        <?php endif; ?>
                                        <?php if (in_array('time', $commands)): ?>
                                            <li><span class="rules_command">/time</span> - <?=Yii::t('common', 'Время на сервере')?></li>
                                        <?php endif; ?>
                                        <?php if (in_array('pop', $commands)): ?>
                                            <li><span class="rules_command">/pop</span> - <?=Yii::t('common', 'Онлайн на сервере')?></li>
                                        <?php endif; ?>
                                        <?php if (in_array('wipe', $commands)): ?>
                                            <li><span class="rules_command">/wipe</span> - <?=Yii::t('common', 'Информация о вайпе')?></li>
                                        <?php endif; ?>
                                        <?php if (in_array('store', $commands)): ?>
                                            <li><span class="rules_command">/store</span> - <?=Yii::t('common', 'Открыть корзину товаров')?></li>
                                        <?php endif; ?>
                                        <?php if (in_array('xrates', $commands)): ?>
                                            <li><span class="rules_command">/rate</span> - <?=Yii::t('common', 'Текущие рейты')?></li>
                                        <?php endif; ?>
                                        <?php if (in_array('fmenu', $commands)): ?>
                                            <li><span class="rules_command">/fmenu</span> - <?=Yii::t('common', 'Друзья')?></li>
                                        <?php endif; ?>
                                        <?php if (in_array('remove', $commands)): ?>
                                            <li><span class="rules_command">/remove</span> - <?=Yii::t('common', 'Ремув стен')?></li>
                                        <?php endif; ?>
                                        <?php if (in_array('sil', $commands)): ?>
                                            <li><span class="rules_command">/sil URL_IMAGE</span> - <?=Yii::t('common', 'Загрузить свое изображение в фоторамку')?></li>
                                        <?php endif; ?>
                                        <?php if (in_array('vlock', $commands)): ?>
                                            <li><span class="rules_command">/vlock</span> - <?=Yii::t('common', 'Поставить код лок на коптер/лошадь')?></li>
                                        <?php endif; ?>
                                        <li><span class="rules_command">/help</span> - <?=Yii::t('common', 'Помощь')?></li>
                                    </ol>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>