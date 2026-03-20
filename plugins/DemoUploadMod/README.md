# DemoUploadMod — Harmony-мод для сервера Rust

Мод в стиле [Rust-ServerMetrics](https://github.com/features-not-bugs/Rust-ServerMetrics): после сохранения демо отправляет файл `.dem` на твой сайт (POST multipart/form-data).

**Сборка под сервер:** путь к серверу задаётся в `Directory.Build.props` (по умолчанию `D:\Server`). На сервере имеют смысл **full server demos** (если включён `demo.full_server_demo`); демки с клиентов игроков пишутся у них на ПК и на сервер не попадают.

---

## Структура (как у Rust-ServerMetrics)

```
DemoUploadMod/
├── DemoUploadMod.csproj
├── Directory.Build.props    ← путь к серверу (D:\Server или D:\Server\rustds)
├── DemoUploadModLoader.cs   ← OnLoaded / OnUnloaded для HarmonyMods
├── ModConfig.cs            ← чтение DemoUploadMod.json
├── Config.cs
├── DemoUploader.cs         ← загрузка файла (UnityWebRequest)
├── DemoUploadWatcher.cs    ← fallback: опрос папки демок
├── HarmonyPatches/
│   └── DemoRecordingStop_Patch.cs
├── DemoUploadMod.json.example
└── README.md
```

---

## Сборка

1. Укажи путь к серверу в `Directory.Build.props`:
   ```xml
   <RustServerPath>D:\Server</RustServerPath>
   ```
   Если исполняемый файл и `RustDedicated_Data` лежат в подпапке (например `rustds`), укажи `D:\Server\rustds`.

2. Собери проект в Release (нужны ссылки на `RustDedicated_Data\Managed\*.dll`).

3. Скопируй `DemoUploadMod.dll` в папку **Harmony Mods** на сервере (обычно рядом с `RustDedicated.exe` или в указанной в документации папке).

4. Положи конфиг `DemoUploadMod.json` в корень сервера (рядом с `RustDedicated_Data`). Пример — `DemoUploadMod.json.example`.

---

## Настройка (файл DemoUploadMod.json)

```json
{
  "upload_url": "https://yoursite.com/api/rust/highlights/upload",
  "api_key": "",
  "enabled": true,
  "use_folder_watcher_fallback": false
}
```

- **upload_url** — URL API (POST, multipart: поле `demo`, `timestamp_utc`, по возможности `steam_id`).
- **api_key** — опционально, заголовок `X-Api-Key`.
- **enabled** — вкл/выкл отправку.
- **use_folder_watcher_fallback** — если `true`, раз в 8 сек проверяется папка демок и загружается последний новый `.dem` (когда не задан метод для Harmony-патча).

---

## Harmony-патч (момент сохранения демо)

Чтобы срабатывание было в момент сохранения демо, нужно пропатчить метод сервера/клиента, который вызывается при остановке записи.

1. Открой в **dnSpy** или **ILSpy** сборку сервера:  
   `RustDedicated_Data/Managed/Assembly-CSharp.dll`
2. Поиск по строкам: `demos`, `.dem`, `StopRecording`, `IsRecording`.
3. Найди класс/метод, вызываемый при сохранении демо (на сервере — для full server demo).
4. В коде открой `HarmonyPatches/DemoRecordingStop_Patch.cs` и задай константы:
   ```csharp
   private const string TargetTypeName = "Имя.Пространства.Имен.Класса";
   private const string TargetMethodName = "StopRecording"; // или как у тебя
   ```
5. Пересобери мод.

Если тип/метод не заданы или не найдены, патч не применится — тогда можно включить **use_folder_watcher_fallback** в конфиге, и загрузка пойдёт по новым файлам в папке демок на сервере.

---

## API на сайте

Endpoint должен принимать **POST** с телом **multipart/form-data**:

- **demo** — файл `.dem`
- **timestamp_utc** — строка времени (ISO 8601)
- **steam_id** — если мод смог получить Steam ID (опционально)

Ответ: 200/201 при успехе.

---

## Важно

- На **сервере** доступны только демки, которые пишет сам сервер (например, full server demos). Демки с клиентов игроков на сервер не попадают.
- Папка демок на сервере обычно рядом с `RustDedicated_Data` или в корне сервера — при необходимости поправь путь в `GetDemosFolder()` в патче и в `DemoUploadWatcher`.
