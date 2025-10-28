# 🔧 Конфигурация через переменные окружения (.env)

## 📝 Обзор

Все настройки (домены, порты, пароли) теперь берутся из переменных окружения. Это позволяет легко менять конфигурацию без изменения кода.

## 🚀 Быстрый старт

### 1. Создайте файл конфигурации

```bash
# Linux/Mac
cp k8s.env.example k8s.env

# Windows
Copy-Item k8s.env.example k8s.env
```

### 2. Отредактируйте k8s.env

```bash
# Ваши домены
FRONTEND_DOMAIN=prostoj.store
BACKEND_DOMAIN=e.prostoj.store
API_DOMAIN=api.prostoj.store
WS_DOMAIN=ws.prostoj.store

# Ваши пароли
MYSQL_ROOT_PASSWORD=your-secure-password
REDIS_PASSWORD=your-redis-password
```

### 3. Загрузите в Kubernetes

```bash
# Linux/Mac
./load-env-to-k8s.sh

# Windows
.\load-env-to-k8s.ps1
```

### 4. Разверните приложение

```bash
./ONE_CLICK_DEPLOY.sh
```

## 📋 Доступные переменные

### Домены

| Переменная | По умолчанию | Описание |
|------------|--------------|----------|
| `FRONTEND_DOMAIN` | prostoj.store | Основной домен фронтенда |
| `BACKEND_DOMAIN` | e.prostoj.store | Домен админки |
| `API_DOMAIN` | api.prostoj.store | API домен |
| `FRONTEND_EN_DOMAIN` | en.prostoj.store | Английская версия |
| `WS_DOMAIN` | ws.prostoj.store | WebSocket домен |

### База данных

| Переменная | По умолчанию | Описание |
|------------|--------------|----------|
| `DB_HOST` | mysql-service | Хост MySQL |
| `DB_PORT` | 3306 | Порт MySQL |
| `DB_NAME` | prostoj4 | Имя базы данных |
| `DB_USERNAME` | root | Пользователь БД |
| `DB_PASSWORD` | - | Пароль БД (обязательно!) |
| `MYSQL_ROOT_PASSWORD` | - | Root пароль MySQL |

### WebSocket

| Переменная | По умолчанию | Описание |
|------------|--------------|----------|
| `WS_BACKEND_HOST` | 127.0.0.1 | Хост WS бэкенда |
| `WS_BACKEND_PORT` | 4888 | Порт WS бэкенда |
| `WS_READ_TIMEOUT` | 2147483647s | Таймаут чтения |
| `WS_SEND_TIMEOUT` | 2147483647s | Таймаут отправки |

### Радио

| Переменная | По умолчанию | Описание |
|------------|--------------|----------|
| `RADIO1_HOST` | 127.0.0.1 | Хост радио 1 |
| `RADIO1_PORT` | 3007 | Порт радио 1 |
| `RADIO2_HOST` | 127.0.0.1 | Хост радио 2 |
| `RADIO2_PORT` | 3008 | Порт радио 2 |

### PHP настройки

| Переменная | По умолчанию | Описание |
|------------|--------------|----------|
| `PHP_FPM_HOST` | 127.0.0.1 | PHP-FPM хост |
| `PHP_FPM_PORT` | 9000 | PHP-FPM порт |
| `PHP_TIMEOUT` | 300 | Таймаут выполнения |
| `UPLOAD_MAX_SIZE` | 128M | Макс размер загрузки |
| `STATIC_CACHE_TIME` | 30d | Кэш статики |

### Внешние сервисы

| Переменная | Описание |
|------------|----------|
| `TELEGRAM_BOT_TOKEN` | Токен Telegram бота |
| `STEAM_API_KEY` | API ключ Steam |
| `S3_ACCESS_KEY` | S3 access key |
| `S3_SECRET_KEY` | S3 secret key |
| `COOKIE_VALIDATION_KEY` | Ключ валидации cookies |

## 🔄 Как это работает

### 1. Nginx конфигурация

**Шаблон** (`docker/nginx/default.template.conf`):
```nginx
server_name ${FRONTEND_DOMAIN};
```

**После обработки** (`entrypoint.sh`):
```nginx
server_name prostoj.store;
```

### 2. Kubernetes ConfigMap

**ConfigMap** загружается из `k8s.env`:
```yaml
apiVersion: v1
kind: ConfigMap
data:
  FRONTEND_DOMAIN: "prostoj.store"
  BACKEND_DOMAIN: "e.prostoj.store"
```

### 3. Entrypoint скрипт

При старте контейнера:
1. Читает переменные окружения
2. Подставляет в шаблоны Nginx
3. Генерирует финальные конфиги
4. Запускает сервисы

## 💡 Примеры использования

### Изменить домены

1. Отредактируйте `k8s.env`:
```bash
FRONTEND_DOMAIN=mysite.com
BACKEND_DOMAIN=admin.mysite.com
```

2. Загрузите в K8s:
```bash
./load-env-to-k8s.sh
```

3. Перезапустите pod'ы:
```bash
kubectl rollout restart deployment/liscase-app -n liscase
```

### Изменить порты

1. В `k8s.env`:
```bash
PHP_FPM_PORT=9001
WS_BACKEND_PORT=5000
```

2. Загрузите и перезапустите:
```bash
./load-env-to-k8s.sh
kubectl rollout restart deployment -n liscase
```

### Использовать другой Docker Registry

1. В `k8s.env`:
```bash
DOCKER_REGISTRY=ghcr.io/myorg
```

2. В скриптах развертывания:
```bash
export DOCKER_REGISTRY=ghcr.io/myorg
./ONE_CLICK_DEPLOY.sh
```

## 🔐 Безопасность

### Никогда не коммитьте в git

Добавьте в `.gitignore`:
```
k8s.env
kubernetes/secrets-production.yaml
*.secret
```

### Используйте секреты для паролей

Пароли и ключи должны быть в `kubernetes/secrets.yaml`, а не в ConfigMap:

```yaml
# В secrets.yaml (зашифровано)
apiVersion: v1
kind: Secret
stringData:
  DB_PASSWORD: "secure-password"
  
# В configmap.yaml (открыто)
data:
  DB_HOST: "mysql-service"
```

## 📊 Приоритеты переменных

1. **Kubernetes ConfigMap/Secrets** (высший приоритет)
2. **Переменные окружения контейнера**
3. **Значения по умолчанию в шаблонах**

## 🧪 Тестирование локально

### Через Docker Compose

Создайте `.env` файл:
```bash
FRONTEND_DOMAIN=localhost
BACKEND_DOMAIN=localhost:8080
```

Запустите:
```bash
docker-compose -f docker-compose.k8s-test.yml up
```

### Проверка переменных

```bash
# В запущенном контейнере
kubectl exec -it <pod-name> -n liscase -- env | grep DOMAIN

# Проверка Nginx конфигурации
kubectl exec -it <pod-name> -n liscase -- cat /etc/nginx/sites-available/default
```

## 🔄 Обновление конфигурации

### Полное обновление

```bash
# 1. Редактируйте k8s.env
vim k8s.env

# 2. Загрузите в K8s
./load-env-to-k8s.sh

# 3. Перезапустите pod'ы
kubectl rollout restart deployment/liscase-app -n liscase

# 4. Проверьте статус
kubectl rollout status deployment/liscase-app -n liscase
```

### Быстрое обновление одной переменной

```bash
kubectl patch configmap liscase-config -n liscase \
  --type merge \
  -p '{"data":{"FRONTEND_DOMAIN":"newdomain.com"}}'

kubectl rollout restart deployment/liscase-app -n liscase
```

## 🎯 Рекомендации

### Development

```bash
APP_ENV=development
APP_DEBUG=true
RUN_MIGRATIONS=true
```

### Staging

```bash
APP_ENV=staging
APP_DEBUG=false
RUN_MIGRATIONS=true
```

### Production

```bash
APP_ENV=production
APP_DEBUG=false
RUN_MIGRATIONS=false
STATIC_CACHE_TIME=30d
```

## ✅ Checklist перед развертыванием

- [ ] Скопирован `k8s.env.example` в `k8s.env`
- [ ] Обновлены все домены
- [ ] Установлены безопасные пароли
- [ ] Настроены SSL сертификаты
- [ ] Проверены ресурсы (CPU/Memory)
- [ ] Настроен Docker Registry
- [ ] Загружены переменные: `./load-env-to-k8s.sh`

## 🎉 Готово!

Теперь вся конфигурация управляется через переменные окружения!


