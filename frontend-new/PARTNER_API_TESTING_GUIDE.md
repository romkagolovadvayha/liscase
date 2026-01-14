# Руководство по тестированию API партнерской программы

## Быстрая проверка через браузер (DevTools)

### 1. Откройте DevTools (F12)
- Перейдите на вкладку Network
- Включите фильтр XHR/Fetch

### 2. Тест вкладки "Условия программы"
1. Откройте `/profile/referral`
2. В Network должен появиться запрос: `GET /v1/user/partner/conditions`
3. В Network должен появиться запрос: `GET /v1/user/partner/invite`
4. Проверьте:
   - Статус: 200
   - Ответ содержит `success: true`
   - Данные корректны

### 3. Тест вкладки "Как приглашать?"
1. Переключитесь на вкладку "Как приглашать?"
2. В Network должны появиться запросы:
   - `GET /v1/user/partner/invite`
   - `GET /v1/user/partner/promocode`
3. Проверьте:
   - Статус: 200
   - Ответ содержит `success: true`
   - Партнерская ссылка отображается
   - Промокод загружается (если есть)

### 4. Тест вкладки "Мои рефералы"
1. Переключитесь на вкладку "Мои рефералы"
2. В Network должен появиться запрос: `GET /v1/user/partner/referrals`
3. Проверьте:
   - Статус: 200
   - Ответ содержит `success: true`
   - Список рефералов отображается
   - Структура данных корректна

## Проверка через cURL

### Получить токен (для всех тестов)
```bash
# Сначала авторизуйтесь через браузер и скопируйте access_token из localStorage
TOKEN="your_access_token_here"
API_BASE="http://api.test.prostoj.store"
```

### 1. Тест GET /v1/user/partner/conditions
```bash
curl -X GET "${API_BASE}/v1/user/partner/conditions" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json"
```

**Ожидаемый ответ:**
```json
{
  "success": true,
  "data": {
    "description": "Условия партнерской программы"
  }
}
```

### 2. Тест GET /v1/user/partner/invite
```bash
curl -X GET "${API_BASE}/v1/user/partner/invite" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json"
```

**Ожидаемый ответ:**
```json
{
  "success": true,
  "data": {
    "ref_code": "12345",
    "referral_link": "http://example.com/p/12345",
    "partnerLink": "http://example.com/p/12345",
    "referral_percent": 5.0,
    "referral_clicks": 10,
    "registered_count": 5,
    "played_count": 3,
    "referral_balance": 1000.50
  }
}
```

### 3. Тест GET /v1/user/partner/referrals
```bash
curl -X GET "${API_BASE}/v1/user/partner/referrals" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json"
```

**Ожидаемый ответ:**
```json
{
  "success": true,
  "data": {
    "referrals": [
      {
        "id": 1,
        "userId": 1,
        "username": "test_user",
        "steam_id": "76561198000000000",
        "avatar": "https://example.com/avatar.jpg",
        "created_at": "2024-01-01 00:00:00",
        "hasBonus": false,
        "hasSkinSent": false,
        "hasHourInServer": true,
        "canGetReward": true
      }
    ],
    "total": 1
  }
}
```

### 4. Тест без авторизации (должен вернуть 401)
```bash
curl -X GET "${API_BASE}/v1/user/partner/conditions" \
  -H "Content-Type: application/json"
```

**Ожидаемый ответ:** Статус 401

## Чеклист проверки

### Функциональность
- [ ] Вкладка "Условия программы" загружает данные из `/user/partner/conditions` и `/user/partner/invite`
- [ ] Вкладка "Как приглашать?" загружает данные из `/user/partner/invite` и `/user/partner/promocode`
- [ ] Вкладка "Мои рефералы" загружает данные из `/user/partner/referrals`
- [ ] При переключении вкладок вызываются правильные API методы
- [ ] Данные корректно отображаются на каждой вкладке

### Обработка ошибок
- [ ] При ошибке 401 показывается сообщение об ошибке
- [ ] При ошибке 500 показывается сообщение об ошибке
- [ ] При сетевой ошибке показывается сообщение об ошибке
- [ ] Не происходит краша приложения при ошибках

### Состояния загрузки
- [ ] Во время загрузки показывается skeleton loader
- [ ] После загрузки данные отображаются корректно
- [ ] Состояние загрузки корректно обрабатывается

### Структура данных
- [ ] Все обязательные поля присутствуют в ответах
- [ ] Типы данных соответствуют ожидаемым
- [ ] Числовые значения корректны (>= 0)
- [ ] Строки не null/undefined

## Примечания

1. Все запросы требуют JWT авторизации
2. Токен должен быть валидным и не истекшим
3. Для тестирования без авторизации используйте запросы без заголовка Authorization
4. Проверяйте Network tab в DevTools для отслеживания запросов




