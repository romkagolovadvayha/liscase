using System;
using HarmonyLib;
using UnityEngine;

namespace DemoUploadMod
{
    /// <summary>
    /// Лоадер для сервера Rust (HarmonyMods). Реализует IHarmonyModHooks, если эта ссылка есть в сборках сервера.
    /// Конфиг читается из DemoUploadMod.json в папке сервера.
    /// </summary>
    public class DemoUploadModLoader
    {
        public static Harmony HarmonyInstance;
        public static void Log(string message) => Debug.Log($"[DemoUploadMod] {message}");
        public static void LogWarning(string message) => Debug.LogWarning($"[DemoUploadMod] {message}");
        public static void LogError(string message) => Debug.LogError($"[DemoUploadMod] {message}");

        public void OnLoaded(object args)
        {
            ModConfig.Load();
            if (!ModConfig.Enabled || string.IsNullOrWhiteSpace(ModConfig.UploadUrl))
            {
                Log("Отключён (enabled=false или upload_url пустой).");
                return;
            }

            try
            {
                HarmonyInstance = new Harmony("com.prostoj.demoupload");
                HarmonyInstance.PatchAll(typeof(DemoUploadModLoader).Assembly);
                Log("v1.0.0 загружен.");

                if (ModConfig.UseFolderWatcherFallback)
                {
                    var go = new GameObject("DemoUploadMod_Watcher");
                    UnityEngine.Object.DontDestroyOnLoad(go);
                    go.AddComponent<DemoUploadWatcher>();
                    Log("Включён fallback (проверка папки демок).");
                }
            }
            catch (Exception ex)
            {
                LogError(ex.ToString());
            }
        }

        public void OnUnloaded(object args)
        {
            try
            {
                HarmonyInstance?.UnpatchSelf();
            }
            catch { }
        }
    }
}
