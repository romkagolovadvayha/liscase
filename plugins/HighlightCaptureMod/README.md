# HighlightCaptureMod — серверный Harmony-мод (по образцу RustDemoPro)

При каждом PvP-килле:
- **Всегда** отправляет на API **метаданные** (JSON): кто убил, кого, время, окно «N сек до / N сек после».
- **Опционально** (`upload_demo: true`): записывает демо киллера на сервере, через N секунд после килла останавливает запись, упаковывает .dem + manifest.json в ZIP и отправляет на `upload_api_url` (как RustDemoPro). Заголовки: `X-Capture-Before-Minutes`, `X-Capture-After-Minutes`, `X-Capture-Start-Utc`, `X-Capture-End-Utc`.

---

## Структура

```
HighlightCaptureMod/
├── HighlightCaptureMod.csproj
├── Directory.Build.props       ← путь к серверу (D:\Server\rustds)
├── HighlightCaptureModLoader.cs  ← OnLoaded / OnUnloaded
├── ModConfig.cs                ← HarmonyMods_Data/HighlightCaptureMod/ (создаёт каталог и дефолтный .json)
├── KillCaptureService.cs       ← обработка смерти, формирование окна
├── ApiSender.cs                ← очередь + POST JSON на API
├── HarmonyPatches/
│   └── EntityDeathPatch.cs     ← патч BaseCombatEntity.Die(HitInfo)
├── HighlightCaptureMod.json.example
└── README.md
```

---

## Сборка

1. В `Directory.Build.props` укажи путь к серверу (папка с `RustDedicated_Data`).
2. Собери проект (нужны ссылки на `RustDedicated_Data\Managed\*.dll`).
3. Скопируй `HighlightCaptureMod.dll` в папку **HarmonyMods** на сервере.
4. При первом запуске мод сам создаёт **HarmonyMods_Data/HighlightCaptureMod/** (рядом с папкой HarmonyMods) и кладёт туда дефолтный **HighlightCaptureMod.json**. Редактируй этот файл для настройки.

---

## Конфиг (HarmonyMods_Data/HighlightCaptureMod/HighlightCaptureMod.json)

| Поле | Описание |
|------|----------|
| enabled | Вкл/выкл мод. |
| api_url | URL вашего API (POST, тело — JSON). |
| api_key | Опционально, заголовок `X-Api-Key`. |
| server_id | Идентификатор сервера для вашего бэкенда. |
| seconds_before_kill | Секунд **до** килла в окне (по умолчанию 15). |
| seconds_after_kill | Секунд **после** килла в окне (по умолчанию 15). |
| upload_demo | Записывать демо киллера и отправлять ZIP на API (как RustDemoPro). |
| upload_api_url | URL для загрузки ZIP (если пусто — используется api_url). |
| log_to_console | Писать в консоль ответ API. |

---

## Тело POST на API

Каждый запрос — один килл, JSON вида:

```json
{
  "killer_steam_id": "76561198123456789",
  "killer_name": "KillerNick",
  "victim_steam_id": "76561198987654321",
  "victim_name": "VictimNick",
  "kill_timestamp_utc": "2025-03-12T10:30:00.0000000Z",
  "window_start_utc": "2025-03-12T10:29:45.0000000Z",
  "window_end_utc": "2025-03-12T10:30:15.0000000Z",
  "seconds_before": 15,
  "seconds_after": 15,
  "server_id": "main",
  "weapon_short_name": "rifle.ak",
  "weapon_prefab": "assets/...",
  "distance": 42.5
}
```

По `window_start_utc` и `window_end_utc` ваш бэкенд знает нужный отрезок времени; при наличии демо (с клиента или full server demo) можно привязывать или резать по этому окну.

---

## Как устроен мод (как в RustDemoPro)

- **Патч:** ищется метод `BaseCombatEntity.Die(HitInfo)` через рефлексию, вешается Postfix.
- **Обработка:** в Postfix вызывается `KillCaptureService.OnEntityDeath`: проверяется PvP, считаются окно и метаданные.
- **Метаданные:** всегда отправляются на `api_url` (POST JSON).
- **Демо (если upload_demo):** при килле вызывается `StartDemoRecording()` для киллера; через `seconds_after_kill` секунд — `StopRecording()`, путь к .dem берётся из `Connection.RecordFilename` или поиск последнего .dem в папке `demos`. ZIP (демо + manifest.json) отправляется на `upload_api_url` с заголовками X-Capture-*.
