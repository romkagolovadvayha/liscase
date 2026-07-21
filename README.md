# Prostoj — установка

Минимальная установка проекта (Yii2 Advanced):

## Требования  
- PHP 7.4+ с расширениями: pdo_mysql, mbstring, intl, curl, json, zip
- Composer
- Nginx + PHP-FPM (например php7.4-fpm)
- MySQL/MariaDB (и/или то, что у вас в конфиге)
- Redis (если используется очередями)

## Быстрая установка

```bash
# 1) Клонируем репозиторий
git clone <REPO_URL> prostoj && cd prostoj

# 2) Инициализируем приложение (yii2-advanced init)
php ./yii init

# 3) Ставим зависимости
composer install
```

## Управление Supervisor и Cron

### Supervisor

Фоновые процессы (воркеры очередей, WebSocket, node-сервисы) управляются **Supervisor**.  
Наши `.conf` файлы генерируются командой:

```bash
./yii supervisortask/sync       # сгенерировать/обновить конфиги из console/supervisor.php
sudo supervisorctl reread       # перечитать конфиги
sudo supervisorctl update       # применить изменения (запустить/перезапустить новые/изменённые)
sudo supervisorctl status | grep -i prostoj       # отфильтровать по группе prostoj

# Перезапустить всё в группе prostoj
sudo supervisorctl restart prostoj:*

# Перезапустить один процесс (с учётом группы)
sudo supervisorctl restart prostoj:prostoj.queue-top

# Остановить / запустить
sudo supervisorctl stop    prostoj:prostoj.queue-top
sudo supervisorctl start   prostoj:prostoj.queue-top

# Хвост лога (stdout); если нужен stderr и не редиректится — добавьте 'stderr'
sudo supervisorctl tail -f prostoj:prostoj.queue-top
```


### Cron

Периодические задачи управляются командами crontask/*. Они работают с user crontab и ведут собственный список задач.

```./yii crontask/index            # список доступных консольных команд для крона (описание)
./yii crontask/ls               # активные задания; флаги: a|al — показать все
./yii crontask/start            # записать задания в crontab пользователя
./yii crontask/stop             # удалить задания из crontab
./yii crontask/restart          # stop + start```
```

## Очереди (Queues)

> Все очереди реализованы на `yii2-queue` (Redis). Базовые команды для любой очереди:
>
> ```bash
> ./yii <queue>/info
> ./yii <queue>/listen --verbose=1 --color=0
> ./yii <queue>/run                     # синхронно обработать все задания
> ./yii <queue>/clear                   # очистить очередь
> ./yii <queue>/remove <id>             # удалить задание по id
> ./yii <queue>/exec <id>               # выполнить конкретное задание
> ```

| Очередь (route)        | Назначение (кратко)                                   | Пример запуска (dev)                                           |
|------------------------|--------------------------------------------------------|-----------------------------------------------------------------|
| `queue-stats`          | Подсчёты/агрегации статистики                         | `./yii queue-stats/listen --verbose=1 --color=0`                |
| `queue-telegram`       | Уведомления/интеграции Telegram                        | `./yii queue-telegram/listen --verbose=1 --color=0`             |
| `queue-report`         | Обработка репортов/отчётов с серверов                  | `./yii queue-report/listen --verbose=1 --color=0`               |
| `queue-kills`          | Обработка событий “kills” / боевых логов               | `./yii queue-kills/listen --verbose=1 --color=0`                |
| `queue-team`           | Операции над командами/сквадами                        | `./yii queue-team/listen --verbose=1 --color=0`                 |
| `queue-raid`           | События рейдов/осад                                    | `./yii queue-raid/listen --verbose=1 --color=0`                 |
| `queue-params`         | Синхронизация/пересчёт параметров                      | `./yii queue-params/listen --verbose=1 --color=0`               |
| `queue-top`            | Построение топов/рейтингов                             | `./yii queue-top/listen --verbose=1 --color=0`                  |
| `queue-online`         | Учёт/агрегация онлайна игроков                         | `./yii queue-online/listen --verbose=1 --color=0`               |
| `queue-process`        | Общий обработчик фоновых задач                         | `./yii queue-process/listen --verbose=1 --color=0`              |
| `queue-support`        | Сообщения/события саппорт-чата                         | `./yii queue-support/listen --verbose=1 --color=0`              |

> **Под Supervisor**: эти же команды запускаются как длительные демоны через `listen`. Конфиги генерируются командой:
>
> ```bash
> ./yii supervisortask/sync
> sudo supervisorctl reread && sudo supervisorctl update
> sudo supervisorctl status | grep -i queue
> ```
