# Quick Fix: 502 Bad Gateway

## 🚨 Проблема

- ❌ 502 Bad Gateway на https://romkagolovadvayha-liscase-d44a.twc1.net/
- ❌ phpMyAdmin (8080) недоступен

## ⚡ Быстрое решение (на облачном сервере)

### Вариант 1: Упрощенная конфигурация

```bash
# 1. Используйте простой docker-compose
mv docker-compose.yml docker-compose.full.yml
mv docker-compose.simple.yml docker-compose.yml

# 2. Перезапустите
docker-compose down
docker-compose up -d

# 3. Проверьте
curl http://localhost/health.php
```

### Вариант 2: Отключить длинную инициализацию

Если проблема в долгой инициализации (chown, composer и т.д.):

```bash
# Отредактируйте docker-compose.yml
# Замените command на простой:
sed -i 's/command:.*/command: apache2-foreground/' docker-compose.yml

# Перезапустите
docker-compose down && docker-compose up -d
```

### Вариант 3: Проверить логи

```bash
# Проверьте, запустился ли контейнер
docker-compose ps

# Логи PHP
docker-compose logs php --tail 100

# Логи всех сервисов
docker-compose logs --tail 50
```

## 🔍 Диагностика

### Проверка 1: Контейнеры запущены?

```bash
docker-compose ps
```

Ожидаемый результат:
```
NAME            STATUS
liscase-app     Up X minutes
liscase-mysql   Up X minutes (healthy)
liscase-redis   Up X minutes
```

### Проверка 2: Apache работает?

```bash
# Проверка изнутри контейнера
docker-compose exec php curl -I http://localhost/health.php

# Должно вернуть: HTTP/1.1 200 OK
```

### Проверка 3: Порт 80 слушается?

```bash
docker-compose exec php netstat -tulpn | grep :80
```

Должно показать Apache на порту 80.

### Проверка 4: Доступ извне

```bash
# С сервера
curl -I http://localhost/health.php

# Извне
curl -I http://94.198.216.133/health.php
```

## 🛠️ Возможные причины 502

| Причина | Проверка | Решение |
|---------|----------|---------|
| **Apache не запустился** | `docker-compose ps` | Проверить логи: `docker-compose logs php` |
| **Долгая инициализация** | Контейнер в статусе "starting" | Использовать `docker-compose.simple.yml` |
| **Ошибка в command скрипте** | Логи показывают ошибку | Упростить command до `apache2-foreground` |
| **Nginx не может подключиться** | `curl http://localhost:80` работает, но извне нет | Проблема в облачном Nginx |
| **Неправильный порт** | Порт != 80 | Установить `APP_PORT=80` в .env |

## ✅ Рабочая минимальная конфигурация

Если ничего не помогает, используйте минимальный `docker-compose.yml`:

```yaml
services:
  php:
    image: yiisoftware/yii2-php:7.4-apache
    ports:
      - '80:80'
    volumes:
      - ./:/app
    environment:
      - APACHE_DOCUMENT_ROOT=/app/frontend/web
    command: apache2-foreground
```

Запустите:
```bash
docker-compose down
docker-compose up -d
```

## 📞 Следующие шаги

1. **Проверьте что контейнер запущен**: `docker-compose ps`
2. **Проверьте логи**: `docker-compose logs php`
3. **Проверьте health**: `curl http://localhost/health.php`
4. **Если 502 остается**: Проблема в облачном Nginx, а не в Docker
5. **Отправьте логи**: `docker-compose logs` для дальнейшей диагностики

## 🎯 Для облачной платформы TWC

Похоже вы используете Timeweb Cloud. Их автоматический Nginx должен проксировать на порт 80 контейнера. Убедитесь:

- ✅ Контейнер `php` слушает на порту 80
- ✅ В настройках проекта указан правильный порт (80)
- ✅ Контейнер полностью запустился (не в процессе инициализации)

Попробуйте минимальную конфигурацию и сообщите результат! 🚀

