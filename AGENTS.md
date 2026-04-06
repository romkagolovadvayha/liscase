# AGENTS.md — ориентация (liscase, бэкенд)

Кратко для ИИ при правках API и моделей рядом с **prostoj-frontend**.

## Стек

- **PHP**, фреймворк **Yii2**.
- Публичный API: каталог **`api/`** (контроллеры `api/controllers/v1/`).
- Общий код моделей и логики: **`common/`** (`common/models/`, в т.ч. `clan/`, `statistics/`).
- Консоль и миграции: **`console/`** (`console/migrations/`).

## Деплой на VPS по SSH

- Workflow: **`.github/workflows/deploy-ssh.yml`** — при push в `develop` / `main` / `master` / `prostoj` или вручную (**Actions → Deploy (SSH — VPS) → Run workflow**). На сервере ветка приводится к **`origin` через `git reset --hard`** (локальные изменения отслеживаемых файлов сбрасываются — не правьте их вручную в каталоге деплоя).
- На сервере в **`DEPLOY_PATH`**: перед **`composer` / `yii`** задайте **`export YII_ENV=prod`** (и при желании **`YII_DEBUG=0`**), иначе корневой `yii` раньше считал окружение dev и тянул Gii, которого нет при **`--no-dev`**. Workflow это выставляет сам.
- GitHub **Environment**: **`prostoj`** (то же имя, что у фронта; см. `environment:` в `.github/workflows/deploy-ssh.yml`). В этом environment задайте отдельный **`DEPLOY_PATH`** — **абсолютный путь к корню репозитория liscase** на сервере (где `composer.json` и `yii`). Плюс `SSH_HOST`, `SSH_USER`, SSH-ключ; опционально `DEPLOY_ENV`.
- **Supervisor (опционально):** переменная **`DEPLOY_SUPERVISOR_CONF`** (secret или var) — полный текст одного файла для **`/etc/supervisor/conf.d/`** (несколько секций `[program:…]` в одном файле допустимо). Имя файла — **`DEPLOY_SUPERVISOR_CONF_FILENAME`** (иначе **`liscase.conf`**). После деплоя выполняются **`supervisorctl reread`** и **`supervisorctl update`**. Пользователю SSH нужен **sudo без пароля** на `mv`/`chmod` в `conf.d` и на `supervisorctl` — см. комментарий в начале **`.github/workflows/deploy-ssh.yml`**.
- **Crontab (опционально):** **`DEPLOY_CRONTAB`** — полный текст crontab: при деплое он **целиком подставляется** командой **`crontab deploy.crontab`** (все прежние задачи этого пользователя затираются — перечислите в переменной всё нужное). В **`deploy-ssh.yml`** из текста убирается `\r` (CRLF из секрета/Windows), иначе аргумент команды `yii` получает хвост `\r` и падает с `Unknown command`. Флаг **`DEPLOY_CRONTAB_ROOT`** = `1` / `true` / `yes` — установка через **`sudo crontab`** (расписание root; нужен NOPASSWD на **`crontab`**). Без флага — crontab пользователя **`SSH_USER`** (sudo не нужен). После установки выполняется **`systemctl kill -s HUP cron`** или **`crond`**, чтобы демон сразу перечитал задания (для этого в sudoers обычно нужен **`systemctl`** — см. пример в **`deploy-ssh.yml`**).
- Kubernetes-деплой остаётся в **`.github/workflows/deploy.yml`**.

## Типичные точки входа

| Задача | Где смотреть |
|--------|----------------|
| Кланы REST | `api/controllers/v1/ClansController.php` |
| Пользователь / профиль API | `api/controllers/v1/UserController.php` |
| Модели клана | `common/models/clan/` |
| Статистика | `common/models/statistics/Statistics.php`, `common/models/clan/ClanStatistics.php` |
| Ingest с игровых серверов (плагины) | POST `v1/plugin-ingest/update-users/{tag}`, `.../raid/{tag}`, `.../signs` → `api/controllers/StatsController.php` |
| Тексты wipe/welcome/help для плагинов | GET `v1/rust-plugin-chat/wipe|welcome|help/{serverTag}` → `RustPluginChatController` + `common/components/rust/RustPluginChatJsonBuilder.php` |
| Legacy магазин ProstojRUST | GET `v1/rust-legacy-store?secret=&method=…` → `RustLegacyStoreController` (делегирует `frontend/controllers/ApiController`) |

## Связь с фронтом

- Фронтенд: репозиторий **prostoj-frontend** (`src/lib/api/*.ts`, `src/types/`).
- Меняя поля ответа API, синхронизировать типы и парсинг на фронте.

## Правила Cursor (общие для фронта)

- Детальные UI- и процесс-правила лежат в **prostoj-frontend** (`.cursor/rules/general.mdc` и `AGENTS.md`).
