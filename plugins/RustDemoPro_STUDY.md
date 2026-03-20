# Разбор RustDemoPro.dll (декомпилированный код)

Декомпиляция выполнена утилитой **InspectDll** (ICSharpCode.Decompiler). Исходный C# лежит в папке **`RustDemoPro_Decompiled/RustDemoPro_Decompiled.cs`** (~10 400 строк).

---

## 1. Как мод подключается

- **Лоадер:** `RustDemoPro.HarmonyMod.Loader.RustDemoProLoader`
  - `OnLoaded(OnHarmonyModLoadedArgs args)` — инициализация
  - `OnUnloaded(OnHarmonyModUnloadedArgs args)` — выгрузка

- **Главный компонент:** синглтон `C` (`SingletonComponent<C>.Instance`), в нём вся логика записей, событий и загрузки.

---

## 2. Harmony-патчи (игра → мод)

Патчи вешаются вручную через рефлексию (без атрибутов `[HarmonyPatch]`), по имени метода.

### Смерть сущности (килл / смерть игрока)

- **Класс `T` (Gameplay.EntityDeath)**  
  - Цель: `BaseCombatEntity.Die(HitInfo)` (или `Die()` без аргументов, если не найден вариант с HitInfo).  
  - Postfix вызывает: `SingletonComponent<C>.Instance.a(__instance, info)`.

- **Класс `U` (Gameplay.EntityTakeDamage)**  
  - Цель: `BaseCombatEntity.Hurt(HitInfo)`.  
  - Postfix вызывает: `SingletonComponent<C>.Instance.A(__instance, info)`.

То есть каждое убийство/смерть и каждый урон проходят в мод через эти два метода.

---

## 3. Обработка килла/смерти (класс `N`, методы `A` и `a`)

- Из `HitInfo` и `BaseCombatEntity` достаются:
  - жертва: `BasePlayer` (ToPlayer()),
  - атакующий: `HitInfo.InitiatorPlayer`,
  - оружие, дистанция, урон, кость попадания и т.д.

- По `userID` жертвы и атакующего из словаря `m_A` берётся сессия игрока (тип `B`). Если сессии нет (игрок не «под записью») — вызов игнорируется.

- Создаётся событие типа **`J`** с полями:
  - `serverSeconds`, `serverTimeLocal`, `type` ("Hit" или "Death"),
  - `attackerUserId`, `attackerName`, `targetUserId`, `targetName`,
  - `weaponPrefab`, `weaponShortName`, `ammoPrefab`, `hitArea`, `distance`,
  - `oldHp`, `predictedNewHp`, `damageTotal`, и т.д.

- Событие добавляется в сессии и жертвы, и киллера: `A(value2, j2)` и `A(value, j2)`.

То есть мод не «режет» демо сам — он **фиксирует время и участников** каждого килла/смерти в своих структурах.

---

## 4. Окно захвата «до / после» (минуты)

- При создании репорта (или аналогичного события) формируется объект контекста с полями:
  - `captureWindowStartUtc`, `captureWindowEndUtc` — границы окна в UTC;
  - **`captureWindowBeforeMinutes`** — сколько минут **до** события (из конфига `m.m_A.TotalMinutes`, по умолчанию в тексте фигурирует 30);
  - **`captureWindowAfterMinutes`** — сколько минут **после** (из конфига `m.m_a.TotalMinutes`, по умолчанию 15);
  - плюс локальные строки времени и т.д.

- Код (около строк 911–917):

```csharp
captureWindowStartUtc = dateTime.ToString("o"),
captureWindowEndUtc = dateTime2.ToString("o"),
captureWindowBeforeMinutes = (int)Math.Round(m.m_A.TotalMinutes),
captureWindowAfterMinutes = (int)Math.Round(m.m_a.TotalMinutes),
```

То есть окно задаётся **в минутах** (до/после), а не в секундах, и берётся из настроек (`m.m_A`, `m.m_a` — скорее всего `TimeSpan`).

---

## 5. Чанки демо и событие ChunkFinalized

- У компонента (тип `N`) есть событие **`ChunkFinalized`** (аргумент типа `a` — метаданные чанка).
- Кто-то подписывается на него (например, строка 708: `this.m_A.ChunkFinalized += A;`) и дальше обрабатывает готовые чанки (очередь на загрузку и т.д.).
- То есть на сервере идёт **непрерывная или чанковая запись** демо; когда чанк готов, мод получает уведомление и может привязать его к окну по времени (start/end) и отправить на свой бэкенд.

---

## 6. Загрузка на сервер (API)

- URL по умолчанию: **`https://api.rustdemopro.com/uploads`** (строка 9492, поле `c2.Upload.Url`).
- В запрос добавляются заголовки (строки 5947–5961), в том числе:
  - **`X-Capture-Before-Minutes`** — `captureWindowBeforeMinutes`;
  - **`X-Capture-After-Minutes`** — `captureWindowAfterMinutes`;
  - **`X-Capture-Start-Utc`**, **`X-Capture-End-Utc`** — границы окна в UTC.
- Тело запроса — **ZIP-файл** (`FileStream` → `StreamContent`, `Content-Type: application/zip`). То есть мод отправляет **архив** (чанки демо и/или метаданные), а не сырой .dem.
- Есть повторные попытки и логи вида `[RustDemoPro] Upload response: ...`.

Итого: «вырезка» 15/30 минут до и после — это не обрезка одного .dem-файла в самом моде, а:
- фиксация времени события (килл/репорт),
- расчёт окна в минутах (before/after),
- отправка этого окна в заголовках и архива с данными на **свой бэкенд** (api.rustdemopro.com), где, вероятно, уже по этим метаданным выбирают или режут нужный кусок.

---

## 7. Что можно взять в свой проект

1. **Патчить те же методы:**  
   `BaseCombatEntity.Die(HitInfo)` и `BaseCombatEntity.Hurt(HitInfo)` — как в классах `T` и `U`: искать метод по имени и `ParameterTypes`, вешать Postfix через `HarmonyMethod`.

2. **Хранить события киллов с серверным временем:**  
   Структура вроде `J` (serverSeconds, attacker/target, weapon, damage…) и привязка к сессии игрока (словарь по userID) — без изменений движка даёт «кто, кого, когда».

3. **Окно в минутах:**  
   Задавать `captureWindowBeforeMinutes` / `captureWindowAfterMinutes` и считать `captureWindowStartUtc` / `captureWindowEndUtc` от времени события (как в коде около 911–917).

4. **Формат загрузки:**  
   Отправлять на свой API ZIP с чанками/демо и заголовками `X-Capture-*-*` — тогда на своей стороне можно реализовать выборку или нарезку по времени так же, как у RustDemoPro.

5. **Чанки:**  
   Нужно понять, откуда в игре берутся «чанки» (полный серверный демо-стрим, отдельная запись на сервере и т.д.) и как подписаться на их финализацию. В декомпилированном коде видно только использование события `ChunkFinalized`, а не то, как именно пишется демо — это уже сторона движка Rust/сервера.

---

## 8. Как снова получить C# из DLL

Из папки `plugins`:

```bash
cd InspectDll\bin\Debug\net48
InspectDll.exe "..\..\..\..\RustDemoPro.dll" "..\..\..\..\RustDemoPro_Decompiled"
```

Или из корня репо (подставь свой путь к DLL):

```bash
dotnet run --project plugins/InspectDll -- "plugins/RustDemoPro.dll" "plugins/RustDemoPro_Decompiled"
```

Полный декомпилированный файл: **`plugins/RustDemoPro_Decompiled/RustDemoPro_Decompiled.cs`**.
