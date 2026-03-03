# Руководство по миграции API endpoints: ProstojRUST → GameStoresRUST

## Общие различия в архитектуре API

### ProstojRUST.cs
- **Base URL**: `https://prostoj.store/api/`
- **Формат запросов**: GET с параметрами в query string
- **Структура**: `BaseRequest + &method=...&param=value`
- **Пример**: `https://prostoj.store/api/?secret=XXX&server=YYY&method=item&item=true&steam_id=ZZZ&id=123`

### GameStoresRUST.cs
- **Base URL**: `https://apip.gamestores.app/v1/` (или `https://api.gamestores.app/v1/`)
- **Формат запросов**: POST с параметрами в body (Dictionary<string, string>)
- **Структура**: `BestApiLink + method + ?store_id=XXX&server_id=YYY` + body params
- **Пример**: `https://apip.gamestores.app/v1/baskets.item?store_id=XXX&server_id=YYY` + POST body

## Таблица соответствия методов

| ProstojRUST | GameStoresRUST | Описание |
|------------|----------------|----------|
| `&method=item&item=true&steam_id={steamId}&id={id}` | `baskets.item` + body: `{"basketId": id}` | Получение информации о предмете в корзине |
| `&method=gived&gived=true&id={id}` | `baskets.makeIssued` + body: `{"steamId": steamId, "basketId": id}` | Отметить предмет как выданный |
| `&method=basket.commands.instant` | `baskets.instantCommands` (без параметров) | Получить список автоматических команд |
| `&method=take&item=true&id={id}` | `baskets.item` + body: `{"basketId": id}` | Получить предмет (тот же что и первый) |
| `&method=info&info=true` | `store.pluginInfo` (без параметров) | Получить информацию о магазине |
| `&method=topData&data={json}` (POST) | Не реализовано в GameStoresRUST | Отправка статистики топа игроков |

## Детальное описание изменений

### 1. Получение предмета из корзины

**ProstojRUST:**
```csharp
Request($"&method=item&item=true&steam_id={player.UserIDString}&id={id}", (i, s) => {
    // обработка
}, player);
```

**GameStoresRUST:**
```csharp
Request("baskets.item", new Dictionary<string, string>
{
    {"basketId", basketId.ToString()}
}, (code, response) => {
    // обработка
}, player);
```

### 2. Отметить предмет как выданный

**ProstojRUST:**
```csharp
Request($"&method=gived&gived=true&id={id}", (code, newResponse) => {
    // обработка
}, player);
```

**GameStoresRUST:**
```csharp
Request("baskets.makeIssued", new Dictionary<string, string>
{
    {"steamId", player.UserIDString},
    {"basketId", basketID.ToString()}
}, (code, newResponse) => {
    // обработка
}, player);
```

### 3. Получение автоматических команд

**ProstojRUST:**
```csharp
Request($"&method=basket.commands.instant", (code, response) => {
    // обработка
}, null);
```

**GameStoresRUST:**
```csharp
Request("baskets.instantCommands", null, (code, response) => {
    // обработка
});
```

### 4. Получение информации о магазине

**ProstojRUST:**
```csharp
Request($"&method=info&info=true", (code, response) => {
    // обработка
});
```

**GameStoresRUST:**
```csharp
Request("store.pluginInfo", null, HandleFetchShopUrlResponse);
```

### 5. Получение корзины игрока

**ProstojRUST:**
Не реализовано напрямую (используется через UI)

**GameStoresRUST:**
```csharp
Request("baskets.bySteamId", new Dictionary<string, string>
{
    {"steamId", playerUserID.ToString()}
}, (code, response) => {
    // обработка
}, player);
```

## Дополнительные методы в GameStoresRUST

GameStoresRUST имеет дополнительные методы, которых нет в ProstojRUST:

1. **`players.item.balance`** - Получить баланс игрока
2. **`players.item.balance.change`** - Изменить баланс игрока
3. **`integrations/payments/custom`** (через PayApiLink) - Создать платеж

## Изменения в структуре ответов

### ProstojRUST формат ответа:
```json
{
    "result": "success",
    "data": { ... }
}
```

### GameStoresRUST формат ответа:
```json
{
    "result": "success",
    "data": { ... }
}
```

**Примечание**: Структура ответов идентична, но пути к данным могут отличаться.

## Параметры авторизации

### ProstojRUST:
- Параметры в query string: `?secret=XXX&server=YYY`
- Добавляются автоматически в `BaseRequestParams`

### GameStoresRUST:
- Параметры в query string: `?store_id=XXX&server_id=YYY`
- Добавляются автоматически в `Request()` метод
- Дополнительные заголовки в `reqHeaders`:
  - `X-Plugin-Version`
  - `storeId`
  - `secretKey`
  - `serverId`

## Рекомендации по миграции

1. **Замените все вызовы `Request()`** с формата `&method=...` на формат `method` с Dictionary параметрами
2. **Измените Base URL** с `prostoj.store/api/` на `apip.gamestores.app/v1/`
3. **Обновите параметры авторизации** с `secret`/`server` на `store_id`/`server_id`
4. **Добавьте заголовки** в `reqHeaders` для GameStoresRUST
5. **Измените метод запроса** с GET на POST для большинства методов
6. **Обновите обработку ответов** если структура данных изменилась

## Пример полной миграции метода

### До (ProstojRUST):
```csharp
Request($"&method=item&item=true&steam_id={player.UserIDString}&id={id}", (i, s) =>
{
    switch (i)
    {
        case 200:
            Dictionary<string, object> response = JsonConvert.DeserializeObject<Dictionary<string, object>>(s, new KeyValuesConverter());
            if (!response.ContainsKey("data"))
                return;
            
            Request($"&method=gived&gived=true&id={id}", (code, newResponse) =>
            {
                if (code != 200 || JsonConvert.DeserializeObject<JObject>(newResponse)["result"].ToString() != "success")
                    return;
                
                ProcessTake(player, response["data"] as Dictionary<string, object>);
            }, player);
            break;
    }
}, player);
```

### После (GameStoresRUST):
```csharp
Request("baskets.item", new Dictionary<string, string>
{
    {"basketId", basketId.ToString()}
}, (code, response) =>
{
    switch (code)
    {
        case 200:
            var data = JsonConvert.DeserializeObject<Dictionary<string, object>>(response, new KeyValuesConverter());
            if (data == null || !data.ContainsKey("data"))
                return;
            
            Request("baskets.makeIssued", new Dictionary<string, string>
            {
                {"steamId", player.UserIDString},
                {"basketId", basketId.ToString()}
            }, (code2, newResponse) =>
            {
                if (code2 != 200 || JsonConvert.DeserializeObject<JObject>(newResponse)["result"].ToString() != "success")
                    return;
                
                ProcessTake(player, data["data"] as Dictionary<string, object>);
            }, player);
            break;
    }
}, player);
```


