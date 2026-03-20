# Как написать клиентский мод для отправки демок на сайт (Rust)

Клиент Rust — Unity-игра. Мод делается через **BepInEx** + **Harmony** (или только HarmonyX). Мод перехватывает момент сохранения демо и загружает файл на ваш API.

---

## 1. Что поставить

- **BepInEx 5** для Unity-игр: https://docs.bepinex.dev/articles/user_guide/installation/index.html  
  Скачать BepInEx UnityIL2CPP (если Rust на IL2CPP) или BepInEx Unity Mono.
- Уточнить, Mono или IL2CPP у Rust (часто Rust — Mono). Тогда ставишь BepInEx 5 для Unity (x86/x64).

После установки в папке игры появятся `BepInEx/`, `doorstop_config.ini`, в `BepInEx/plugins/` кладёшь свою .dll.

---

## 2. Найти, где игра сохраняет демо

Клиент Rust не в открытом доступе. Нужно посмотреть сборки игры в декомпиляторе:

1. Открыть в **dnSpy** или **ILSpy** папку с игрой, найти `Rust_Data/Managed/Assembly-CSharp.dll` (или основной exe).
2. В поиске по тексту искать:
   - `demos`
   - `.dem`
   - `StartRecording` / `StopRecording`
   - `IsRecording`
3. Найти класс/метод, который вызывается при остановке записи и пишет файл на диск (часто что-то вроде `Connection`, `DemoRecorder`, `Client`). Запомнить полное имя типа и метода, например: `Net.Client.StopDemoRecording()`.

Этот метод мы будем патчить Harmony’ем: после его выполнения у нас уже есть сохранённый .dem файл — его путь можно взять из полей класса или параметров (или из стандартной папки игры).

---

## 3. Структура проекта мода (C#)

Пример структуры решения в Visual Studio:

```
RustHighlightUploader/
├── RustHighlightUploader.csproj
└── RustHighlightUploader/
    ├── Plugin.cs          // точка входа BepInEx
    └── DemoUploadPatch.cs // Harmony-патч + загрузка файла
```

**Ссылки (NuGet или из BepInEx):**

- `BepInEx.Core`
- `HarmonyX` (или `0Harmony`)

Либо положить в папку с проектом DLL из установленного BepInEx: `BepInEx/core/BepInEx.dll`, `BepInEx/core/0Harmony.dll` и добавить Reference в .csproj.

---

## 4. Пример кода

### Plugin.cs (BepInEx)

```csharp
using BepInEx;
using BepInEx.Logging;
using HarmonyLib;

namespace RustHighlightUploader
{
    [BepInPlugin(PluginInfo.PLUGIN_GUID, PluginInfo.PLUGIN_NAME, PluginInfo.PLUGIN_VERSION)]
    public class Plugin : BaseUnityPlugin
    {
        internal static new ManualLogSource Logger;

        private void Awake()
        {
            Logger = base.Logger;
            Harmony.CreateAndPatchAll(typeof(DemoUploadPatch));
            Logger.LogInfo("RustHighlightUploader loaded");
        }
    }
}
```

### DemoUploadPatch.cs (Harmony + загрузка)

Здесь нужно подставить **реальные** тип и метод из шага 2. Пример условный: `SomeNamespace.DemoRecorder.StopRecording`.

```csharp
using System;
using System.Collections;
using System.IO;
using System.Reflection;
using HarmonyLib;
using UnityEngine;
using UnityEngine.Networking;

namespace RustHighlightUploader
{
    [HarmonyPatch]
    public static class DemoUploadPatch
    {
        // Настройки — потом вынести в ConfigFile BepInEx
        private const string UploadUrl = "https://yoursite.com/api/rust/highlights/upload";
        private static string _lastDemoPath;

        // Вариант 1: патчим метод, который вызывается при остановке записи.
        // ЗАМЕНИТЬ на реальный класс из декомпилятора, например:
        // [HarmonyPatch(typeof(Net.Client), "StopRecording")]
        [HarmonyTargetMethod]
        static MethodBase FindStopRecordingMethod()
        {
            // Ищем по имени в Assembly-CSharp
            foreach (var asm in AppDomain.CurrentDomain.GetAssemblies())
            {
                if (asm.GetName().Name != "Assembly-CSharp") continue;
                var type = asm.GetType("Net.Client"); // или как у тебя называется
                if (type == null) continue;
                var method = type.GetMethod("StopRecording", BindingFlags.Public | BindingFlags.Instance);
                if (method != null) return method;
            }
            return null;
        }

        [HarmonyPostfix]
        static void AfterStopRecording(object __instance)
        {
            try
            {
                // Путь к последней демке: либо из поля __instance, либо стандартная папка Rust
                string demosFolder = Path.Combine(
                    Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                    "..", "LocalLow", "Facepunch Studios", "Rust", "demos"
                );
                // Или папка рядом с exe:
                // string demosFolder = Path.Combine(Application.dataPath, "..", "demos");

                if (!Directory.Exists(demosFolder)) return;

                var dir = new DirectoryInfo(demosFolder);
                FileInfo latest = null;
                foreach (var f in dir.GetFiles("*.dem"))
                {
                    if (latest == null || f.LastWriteTimeUtc > latest.LastWriteTimeUtc)
                        latest = f;
                }

                if (latest != null)
                {
                    _lastDemoPath = latest.FullName;
                    // Запускаем загрузку в корутине (Unity API)
                    var go = new GameObject("HighlightUploader");
                    UnityEngine.Object.DontDestroyOnLoad(go);
                    go.AddComponent<UploadBehaviour>().StartUpload(latest.FullName);
                }
            }
            catch (Exception e)
            {
                Plugin.Logger?.LogError($"DemoUpload error: {e}");
            }
        }
    }

    public class UploadBehaviour : MonoBehaviour
    {
        public void StartUpload(string filePath)
        {
            StartCoroutine(UploadCoroutine(filePath));
        }

        private static IEnumerator UploadCoroutine(string filePath)
        {
            if (!File.Exists(filePath))
            {
                Destroy(gameObject);
                yield break;
            }

            byte[] bytes = File.ReadAllBytes(filePath);
            string fileName = Path.GetFileName(filePath);

            // multipart/form-data: файл + метаданные
            var form = new WWWForm();
            form.AddBinaryData("demo", bytes, fileName, "application/octet-stream");
            form.AddField("steam_id", Steamworks.SteamUser.GetSteamID().ToString());
            form.AddField("timestamp_utc", DateTime.UtcNow.ToString("o"));

            using (var req = UnityWebRequest.Post(UploadUrl, form))
            {
                req.timeout = 30;
                yield return req.SendWebRequest();

                if (req.result != UnityWebRequest.Result.Success)
                    Plugin.Logger?.LogWarning($"Upload failed: {req.responseCode} {req.error}");
                else
                    Plugin.Logger?.LogInfo($"Upload OK: {fileName}");
            }

            Destroy(gameObject);
        }
    }
}
```

Важно: в реальном клиенте Rust тип может называться иначе (не `Net.Client`), и метод — не `StopRecording`. Это нужно уточнить в декомпиляторе и подставить в `GetType("...")` и `GetMethod("...")`.

---

## 5. Сборка и установка

- Собрать проект в Release, положить одну .dll в `BepInEx/plugins/`.
- Запустить игру, проверить лог в `BepInEx/LogOutput.log`.

Если патч не применился — в логе Harmony будет видно; тогда правишь имя типа/метода по декомпилятору.

---

## 6. API на сайте

Endpoint принимает POST `multipart/form-data`:

- `demo` — файл .dem
- `steam_id` — SteamID игрока
- `timestamp_utc` — время в ISO 8601

Ответ 200/201 — успех. По `steam_id` + `timestamp_utc` можно связать запись с хайлайтом у себя в БД.

---

## 7. Важно

- **EAC / античит:** если на сервере включён античит, клиентские моды могут быть запрещены. Обычно такие моды используют на своих серверах без EAC или с белым списком.
- **ToS Facepunch:** изменение клиента может нарушать правила игры; использование только на своих серверах и на свой риск.

После того как найдёшь в декомпиляторе точный класс и метод остановки записи и путь к файлу — подставь их в патч и при необходимости доработай способ получения пути к последнему .dem (из полей инстанса или конфига игры).
