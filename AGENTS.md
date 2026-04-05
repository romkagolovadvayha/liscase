# AGENTS.md — ориентация (liscase, бэкенд)

Кратко для ИИ при правках API и моделей рядом с **prostoj-frontend**.

## Стек

- **PHP**, фреймворк **Yii2**.
- Публичный API: каталог **`api/`** (контроллеры `api/controllers/v1/`).
- Общий код моделей и логики: **`common/`** (`common/models/`, в т.ч. `clan/`, `statistics/`).
- Консоль и миграции: **`console/`** (`console/migrations/`).

## Деплой на VPS по SSH

- Workflow: **`.github/workflows/deploy-ssh.yml`** — при push в `develop` / `main` / `master` / `prostoj` или вручную (**Actions → Deploy (SSH — VPS) → Run workflow**).
- На сервере в **`DEPLOY_PATH`**: перед **`composer` / `yii`** задайте **`export YII_ENV=prod`** (и при желании **`YII_DEBUG=0`**), иначе корневой `yii` раньше считал окружение dev и тянул Gii, которого нет при **`--no-dev`**. Workflow это выставляет сам.
- GitHub **Environment**: **`prostoj`** (то же имя, что у фронта; см. `environment:` в `.github/workflows/deploy-ssh.yml`). В этом environment задайте отдельный **`DEPLOY_PATH`** — **абсолютный путь к корню репозитория liscase** на сервере (где `composer.json` и `yii`). Плюс `SSH_HOST`, `SSH_USER`, SSH-ключ; опционально `DEPLOY_ENV`.
- Kubernetes-деплой остаётся в **`.github/workflows/deploy.yml`**.

## Типичные точки входа

| Задача | Где смотреть |
|--------|----------------|
| Кланы REST | `api/controllers/v1/ClansController.php` |
| Пользователь / профиль API | `api/controllers/v1/UserController.php` |
| Модели клана | `common/models/clan/` |
| Статистика | `common/models/statistics/Statistics.php`, `common/models/clan/ClanStatistics.php` |

## Связь с фронтом

- Фронтенд: репозиторий **prostoj-frontend** (`src/lib/api/*.ts`, `src/types/`).
- Меняя поля ответа API, синхронизировать типы и парсинг на фронте.

## Правила Cursor (общие для фронта)

- Детальные UI- и процесс-правила лежат в **prostoj-frontend** (`.cursor/rules/general.mdc` и `AGENTS.md`).
