# Сравнение API Endpoints: ProstojRUST vs GameStoresRUST

## Основные различия

### 1. URL и формат запросов

| Аспект | ProstojRUST | GameStoresRUST |
|--------|-------------|----------------|
| Base URL | `https://prostoj.store/api/` | `https://apip.gamestores.app/v1/` |
| Метод HTTP | GET | POST |
| Параметры | Query string (`&method=...&param=value`) | Body (Dictionary) + Query (`?store_id=...&server_id=...`) |
| Авторизация | `?secret=XXX&server=YYY` | `?store_id=XXX&server_id=YYY` + Headers |

### 2. Структура вызова Request()

**ProstojRUST:**
```csharp
Request($"&method=item&item=true&steam_id={steamId}&id={id}", callback, player);
```

**GameStoresRUST:**
```csharp
Request("baskets.item", new Dictionary<string, string> {
    {"basketId", id.ToString()}
}, callback, player);
```

## Таблица соответствия методов

### Получение предмета из корзины

| ProstojRUST | GameStoresRUST |
|------------|----------------|
| `&method=item&item=true&steam_id={steamId}&id={id}` | `baskets.item`<br>Body: `{"basketId": id}` |

**Пример миграции:**

```csharp
// ProstojRUST
Request($"&method=item&item=true&steam_id={player.UserIDString}&id={id}", (i, s) => {
    // обработка
}, player);

// GameStoresRUST
Request("baskets.item", new Dictionary<string, string> {
    {"basketId", id.ToString()}
}, (code, response) => {
    // обработка
}, player);
```

### Отметить предмет как выданный

| ProstojRUST | GameStoresRUST |
|------------|----------------|
| `&method=gived&gived=true&id={id}` | `baskets.makeIssued`<br>Body: `{"steamId": steamId, "basketId": id}` |

**Пример миграции:**

```csharp
// ProstojRUST
Request($"&method=gived&gived=true&id={id}", (code, newResponse) => {
    // обработка
}, player);

// GameStoresRUST
Request("baskets.makeIssued", new Dictionary<string, string> {
    {"steamId", player.UserIDString},
    {"basketId", id.ToString()}
}, (code, newResponse) => {
    // обработка
}, player);
```

### Получение автоматических команд

| ProstojRUST | GameStoresRUST |
|------------|----------------|
| `&method=basket.commands.instant` | `baskets.instantCommands`<br>Body: `null` |

**Пример миграции:**

```csharp
// ProstojRUST
Request($"&method=basket.commands.instant", (code, response) => {
    // обработка
}, null);

// GameStoresRUST
Request("baskets.instantCommands", null, (code, response) => {
    // обработка
});
```

### Получение информации о магазине

| ProstojRUST | GameStoresRUST |
|------------|----------------|
| `&method=info&info=true` | `store.pluginInfo`<br>Body: `null` |

**Пример миграции:**

```csharp
// ProstojRUST
Request($"&method=info&info=true", (code, response) => {
    // обработка
});

// GameStoresRUST
Request("store.pluginInfo", null, (code, response) => {
    // обработка
});
```

### Отправка статистики (POST)

| ProstojRUST | GameStoresRUST |
|------------|----------------|
| `&method=topData&data={json}` (POST) | ❌ Не реализовано |

**Примечание:** В GameStoresRUST нет функционала отправки статистики топа игроков.

## Полный пример миграции метода store.take

### ProstojRUST версия:

```csharp
[ConsoleCommand("store.take")]
private void CmdStoreTake(ConsoleSystem.Arg args)
{
    // ... проверки ...
    
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
}
```

### GameStoresRUST версия:

```csharp
[ConsoleCommand("store.take")]
private void CmdStoreTake(ConsoleSystem.Arg args)
{
    // ... проверки ...
    
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
}
```

## Ключевые изменения для миграции

### 1. Изменение формата вызова Request()

**Было:**
```csharp
Request($"&method=methodName&param1={value1}&param2={value2}", callback, player);
```

**Стало:**
```csharp
Request("methodName", new Dictionary<string, string> {
    {"param1", value1},
    {"param2", value2}
}, callback, player);
```

### 2. Изменение имен методов

| Старое имя (ProstojRUST) | Новое имя (GameStoresRUST) |
|-------------------------|---------------------------|
| `item` | `baskets.item` |
| `gived` | `baskets.makeIssued` |
| `basket.commands.instant` | `baskets.instantCommands` |
| `info` | `store.pluginInfo` |
| `take` | `baskets.item` (тот же что и item) |

### 3. Изменение параметров

| ProstojRUST параметр | GameStoresRUST параметр |
|---------------------|------------------------|
| `id` | `basketId` |
| `steam_id` | `steamId` (в body, не в URL) |
| `item=true` | Удалить (не нужен) |
| `gived=true` | Удалить (не нужен) |

### 4. Изменение структуры ответа

Структура JSON ответа остается той же:
```json
{
    "result": "success",
    "data": { ... }
}
```

Но пути к данным могут отличаться. Проверьте структуру `data` в каждом конкретном случае.

## Дополнительные возможности GameStoresRUST

GameStoresRUST имеет дополнительные методы:

1. **`baskets.bySteamId`** - Получить корзину игрока по Steam ID
2. **`players.item.balance`** - Получить баланс игрока
3. **`players.item.balance.change`** - Изменить баланс игрока
4. **`integrations/payments/custom`** - Создать платеж (через PayApiLink)

## Чек-лист миграции

- [ ] Заменить все `Request($"&method=...")` на `Request("method", dict)`
- [ ] Изменить Base URL с `prostoj.store/api/` на `apip.gamestores.app/v1/`
- [ ] Обновить имена методов согласно таблице соответствия
- [ ] Изменить параметры с query string на Dictionary в body
- [ ] Обновить имена параметров (`id` → `basketId`, `steam_id` → `steamId`)
- [ ] Удалить флаги типа `item=true`, `gived=true`
- [ ] Проверить структуру ответов и обновить обработку данных
- [ ] Добавить заголовки авторизации (если нужно)
- [ ] Протестировать все методы после миграции


