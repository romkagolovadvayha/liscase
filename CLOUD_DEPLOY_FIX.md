# Cloud Deploy Fix - 502 Bad Gateway

## 🚨 Проблема

После деплоя возникает ошибка:
- ✅ Клонирование успешно
- ✅ Порты найдены (3025, 6379, 3307, 8080)
- ✅ Nginx настроен
- ❌ **502 Bad Gateway** на https://romkagolovadvayha-liscase-d44a.twc1.net/
- ❌ **phpMyAdmin (8080) недоступен**

## 🔍 Причина

Облачная платформа автоматически создает Nginx, который пытается проксировать на порты контейнеров. Но:

1. Nginx ожидает что приложение слушает на порту **80** (стандартный)
2. В `docker-compose.yml` настроен порт **3025:80** для локальной разработки
3. Облачный Nginx не может проксировать на 3025 внутри контейнера

## ✅ Решение

### Вариант 1: Использовать переменную APP_PORT

Обновите `.env` файл на облачном сервере:

```bash
# На облачном сервере создайте .env
cat > .env <<EOF
APP_PORT=80
ADMIN_STEAM_ID=ВАШ_STEAM_ID
APACHE_LOG_DIR=/var/log/apache2
DB_HOST=mysql
DB_NAME=liscase
DB_USER=root
DB_PASSWORD=ВАШ_ПАРОЛЬ
REDIS_HOST=redis
EOF
```

Затем передеплойте:
```bash
docker-compose down
docker-compose up -d
```

### Вариант 2: Создать docker-compose.cloud.yml

Создайте отдельный файл для облака:

```yaml
# docker-compose.cloud.yml
services:
  php:
    ports:
      - '80:80'      # Стандартный порт для облака
    environment:
      - APP_ENV=production
      
  mysql:
    ports:
      - '3306:3306'  # Стандартный порт MySQL
      
  phpmyadmin:
    ports:
      - '8080:80'    # phpMyAdmin на 8080
```

Деплой:
```bash
docker-compose -f docker-compose.yml -f docker-compose.cloud.yml up -d
```

### Вариант 3: Обновить docker-compose.yml напрямую на сервере

На сервере отредактируйте `docker-compose.yml`:

```bash
# Найдите строку:
ports:
  - '3025:80'

# Замените на:
ports:
  - '80:80'
```

Затем:
```bash
docker-compose down
docker-compose up -d
```

## 🌐 phpMyAdmin доступ

### Если 8080 порт недоступен извне

Облачная платформа может блокировать порт 8080. Варианты:

**1. Проброс через SSH tunnel**:
```bash
ssh -L 8080:localhost:8080 user@server
# Затем откройте: http://localhost:8080
```

**2. Добавить в Nginx облачной платформы**:

Если у вас есть доступ к конфигурации облачного Nginx, добавьте:

```nginx
server {
    listen 80;
    server_name pma.вашдомен.com;
    
    location / {
        proxy_pass http://localhost:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

**3. Использовать NodePort в Kubernetes** (если это K8s):

```yaml
apiVersion: v1
kind: Service
metadata:
  name: phpmyadmin
spec:
  type: NodePort
  ports:
  - port: 80
    targetPort: 80
    nodePort: 30080  # Внешний порт
```

## 🔧 Проверка после исправления

### 1. Проверьте порты внутри контейнера

```bash
docker-compose exec php netstat -tulpn | grep LISTEN
```

Должно быть:
```
tcp  0  0 0.0.0.0:80  0.0.0.0:*  LISTEN  apache2
```

### 2. Проверьте health endpoint

```bash
curl http://localhost/health.php
# или
curl http://94.198.216.133/health.php
```

Ответ:
```json
{
  "status": "OK",
  "timestamp": 1729467890,
  "datetime": "2025-10-21 02:51:30",
  "php_version": "7.4.33",
  "server": "Apache/2.4.54 (Debian)"
}
```

### 3. Проверьте логи Apache

```bash
docker-compose logs php | grep -i error
docker-compose exec php tail -f /var/log/apache2/error.log
```

### 4. Проверьте доступность через curl

```bash
# Изнутри сервера
curl -I http://localhost:80

# Извне
curl -I http://94.198.216.133:80
curl -I https://romkagolovadvayha-liscase-d44a.twc1.net/
```

## 📋 Быстрый чеклист

- [ ] Обновить порт с 3025 на 80 в docker-compose.yml (или через .env)
- [ ] Создать .env файл с APP_PORT=80 на облачном сервере
- [ ] Перезапустить контейнеры: `docker-compose down && docker-compose up -d`
- [ ] Проверить health endpoint: `curl http://localhost/health.php`
- [ ] Проверить логи: `docker-compose logs php`
- [ ] Проверить доступность извне

## 🎯 Ожидаемый результат

После исправления:
- ✅ https://romkagolovadvayha-liscase-d44a.twc1.net/ - HTTP 200
- ✅ http://94.198.216.133/health.php - JSON ответ
- ✅ Nginx может проксировать на Apache внутри контейнера

## 📞 Если проблема остается

Предоставьте:
1. `docker-compose logs php` - последние 100 строк
2. `docker-compose ps` - статус контейнеров
3. `curl -v http://localhost:80` - вывод изнутри сервера
4. Конфигурацию облачного Nginx (если есть доступ)

