# API v1 Controllers

Здесь находятся версионированные API контроллеры для нового React frontend.

## Структура

- `AuthController.php` - Авторизация и аутентификация
- `UserController.php` - Управление пользователями
- `ServersController.php` - Информация о серверах
- `StatsController.php` - Статистика
- `TasksController.php` - Задания
- `PaymentController.php` - Платежи

## Конвенции

1. Все контроллеры наследуются от `yii\rest\ActiveController` или `yii\web\Controller`
2. Все ответы в формате JSON
3. Единый формат ответов (см. `API_MIGRATION_GUIDE.md`)
4. CORS включен для всех контроллеров
5. Версионирование через URL (`/api/v1/...`)

## Тестирование

Все endpoints тестируются через:
- Unit тесты
- Integration тесты
- Postman/Swagger



















