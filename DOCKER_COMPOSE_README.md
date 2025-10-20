# 🚀 LiSCase - Docker Compose Production Deployment

## ✨ Полная автоматизация развертывания

Этот Docker Compose автоматически выполняет ВСЕ шаги, которые раньше делались вручную:

✅ Git clone (вы уже сделали)  
✅ **Composer install** - автоматически  
✅ **Yii init (Production)** - автоматически  
✅ **Миграции БД** - автоматически  
✅ **Nginx конфигурация** - автоматически из .env  
✅ **Supervisor для очередей** - автоматически  
✅ **SSL сертификаты** - автоматически через Let's Encrypt  

## ⚡ Запуск одной командой

### Linux/Mac
```bash
chmod +x start.sh
./start.sh
```

### Windows (двойной клик)
```
START.bat
```

### Windows (PowerShell)
```powershell
.\start.ps1
```

### Или через docker-compose
```bash
docker-compose -f docker-compose.prod.yml up -d
```

## 📋 Что происходит автоматически

### 1. Composer Install
При первом запуске контейнера `app`:
```bash
composer install --no-dev --optimize-autoloader
```

### 2. Yii Init
Автоматическая инициализация в режиме Production:
```bash
php init --env=Production --overwrite=All
```

### 3. Миграции
Автоматический запуск миграций:
```bash
php yii migrate --interactive=0
```

### 4. Nginx конфигурация
Генерация из шаблонов с вашими доменами из .env:
- Frontend: `${FRONTEND_DOMAIN}`
- Backend: `${BACKEND_DOMAIN}`
- API: `${API_DOMAIN}`
- English: `${FRONTEND_EN_DOMAIN}`
- WebSocket: `${WS_DOMAIN}`

### 5. Supervisor Tasks
Автоматический запуск:
- WebSocket server
- 3 queue workers
- Cron jobs (hourly, daily)

### 6. SSL Сертификаты
Автоматическое получение от Let's Encrypt:
- Для всех доменов
- Автообновление каждые 12 часов
- Поддержка staging для тестирования

## 🔧 Настройка перед запуском

### 1. Создайте .env файл
```bash
cp k8s.env.example .env
```

### 2. Отредактируйте .env
```bash
# Ваши домены
FRONTEND_DOMAIN=prostoj.store
BACKEND_DOMAIN=e.prostoj.store
API_DOMAIN=api.prostoj.store
FRONTEND_EN_DOMAIN=en.prostoj.store
WS_DOMAIN=ws.prostoj.store

# Базовые пароли
MYSQL_ROOT_PASSWORD=ваш-безопасный-пароль
MYSQL_PASSWORD=пароль-пользователя-бд
REDIS_PASSWORD=пароль-redis

# Email для Let's Encrypt
ADMIN_EMAIL=admin@prostoj.store
```

### 3. Настройте DNS
Убедитесь, что все домены указывают на ваш сервер:
```
prostoj.store        A    ВАШ_IP
e.prostoj.store      A    ВАШ_IP
api.prostoj.store    A    ВАШ_IP
en.prostoj.store     A    ВАШ_IP
ws.prostoj.store     A    ВАШ_IP
```

## 🎯 Запуск

```bash
./start.sh
```

Скрипт автоматически:
1. ✅ Проверит .env файл
2. ✅ Остановит старые контейнеры
3. ✅ Соберет Docker образы
4. ✅ Запустит MySQL и Redis
5. ✅ Дождется готовности БД
6. ✅ Запустит приложение с автоинициализацией
7. ✅ Выполнит composer install
8. ✅ Выполнит yii init
9. ✅ Запустит миграции
10. ✅ Настроит Nginx с вашими доменами
11. ✅ Получит SSL сертификаты
12. ✅ Запустит все сервисы

## 📦 Сервисы

После запуска работают:

| Сервис | Описание | Порт |
|--------|----------|------|
| **mysql** | MySQL 8.0 | 3306 |
| **redis** | Redis 7 | 6379 |
| **app** | PHP-FPM приложение | 9000 |
| **nginx** | Nginx web server | 80, 443 |
| **websocket** | WebSocket server | 4888 |
| **supervisor** | Очереди и workers | - |
| **discord-bot** | Discord бот | - |
| **music-bot** | Music бот | - |
| **radio1** | Radio server 1 | 3007 |
| **radio2** | Radio server 2 | 3008 |
| **certbot** | SSL автообновление | - |

## 🔐 SSL Сертификаты

### Автоматическое получение
```bash
./docker/certbot/init-letsencrypt.sh
```

### Staging (для тестирования)
```bash
LETSENCRYPT_STAGING=1 ./docker/certbot/init-letsencrypt.sh
```

### Ручное обновление
```bash
docker-compose -f docker-compose.prod.yml exec certbot certbot renew
```

## 📊 Управление

### Просмотр логов
```bash
# Все логи
docker-compose -f docker-compose.prod.yml logs -f

# Конкретный сервис
docker-compose -f docker-compose.prod.yml logs -f app
docker-compose -f docker-compose.prod.yml logs -f nginx
docker-compose -f docker-compose.prod.yml logs -f supervisor
```

### Статус
```bash
docker-compose -f docker-compose.prod.yml ps
```

### Перезапуск
```bash
# Все сервисы
docker-compose -f docker-compose.prod.yml restart

# Конкретный сервис
docker-compose -f docker-compose.prod.yml restart app
```

### Shell в контейнере
```bash
docker-compose -f docker-compose.prod.yml exec app bash
```

### Выполнение команд
```bash
# Миграции
docker-compose -f docker-compose.prod.yml exec app php yii migrate

# Очистка кэша
docker-compose -f docker-compose.prod.yml exec app php yii cache/flush-all

# Supervisor status
docker-compose -f docker-compose.prod.yml exec supervisor supervisorctl status
```

### Остановка
```bash
docker-compose -f docker-compose.prod.yml down
```

### Полная очистка (с удалением данных)
```bash
docker-compose -f docker-compose.prod.yml down -v
```

## 🔄 Обновление приложения

```bash
# 1. Pull новый код
git pull

# 2. Пересборка и перезапуск
docker-compose -f docker-compose.prod.yml up -d --build

# 3. Миграции (если нужно)
docker-compose -f docker-compose.prod.yml exec app php yii migrate
```

## 🐛 Troubleshooting

### Проблема: Composer не устанавливается
```bash
# Проверьте логи
docker-compose -f docker-compose.prod.yml logs app

# Зайдите в контейнер и установите вручную
docker-compose -f docker-compose.prod.yml exec app bash
composer install
```

### Проблема: Миграции не применяются
```bash
# Проверьте подключение к БД
docker-compose -f docker-compose.prod.yml exec app php yii migrate/up

# Проверьте логи MySQL
docker-compose -f docker-compose.prod.yml logs mysql
```

### Проблема: SSL сертификаты не получены
```bash
# Проверьте DNS
nslookup prostoj.store

# Проверьте доступность порта 80
curl http://prostoj.store/.well-known/acme-challenge/test

# Используйте staging для тестирования
LETSENCRYPT_STAGING=1 ./docker/certbot/init-letsencrypt.sh
```

### Проблема: Nginx не запускается
```bash
# Проверьте конфигурацию
docker-compose -f docker-compose.prod.yml exec nginx nginx -t

# Проверьте логи
docker-compose -f docker-compose.prod.yml logs nginx
```

## 📁 Структура файлов

```
liscase/
├── .env                              # Ваша конфигурация
├── docker-compose.prod.yml           # Docker Compose конфигурация
├── start.sh                          # Скрипт запуска (Linux/Mac)
├── start.ps1                         # Скрипт запуска (Windows)
├── START.bat                         # Двойной клик (Windows)
├── docker/
│   ├── app/
│   │   ├── Dockerfile                # PHP приложение
│   │   └── init-app.sh               # Автоинициализация
│   ├── nginx/
│   │   ├── templates/                # Nginx шаблоны с переменными
│   │   │   ├── default.conf.template
│   │   │   └── websocket.conf.template
│   │   └── generate-nginx.sh         # Генератор конфигов
│   ├── supervisor/
│   │   └── supervisord.conf          # Supervisor конфиг
│   ├── mysql/
│   │   └── custom.cnf                # MySQL оптимизация
│   └── certbot/
│       ├── init-letsencrypt.sh       # Получение SSL
│       └── renew.sh                  # Автообновление SSL
└── ...
```

## ⚙️ Переменные окружения

Все настройки в `.env`:

### Обязательные
- `FRONTEND_DOMAIN` - основной домен
- `BACKEND_DOMAIN` - админка
- `API_DOMAIN` - API
- `MYSQL_ROOT_PASSWORD` - пароль MySQL
- `ADMIN_EMAIL` - email для Let's Encrypt

### Опциональные
- `FRONTEND_EN_DOMAIN` - английская версия
- `WS_DOMAIN` - WebSocket домен
- `REDIS_PASSWORD` - пароль Redis
- `LETSENCRYPT_STAGING` - тестовый режим SSL

## 🎉 Готово!

Теперь весь процесс развертывания автоматизирован:
```bash
./start.sh
```

И все работает! 🚀




