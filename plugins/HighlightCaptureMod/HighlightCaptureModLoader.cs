using System;
using HarmonyLib;
using UnityEngine;

namespace HighlightCaptureMod
{
    /// <summary>Лоадер для Harmony Mods. Реализует IHarmonyModHooks, чтобы лаунчер Rust точно вызывал OnLoaded/OnUnloaded.</summary>
    public class HighlightCaptureModLoader : IHarmonyModHooks
    {
        public static void Log(string msg)
        {
            Debug.Log("[HighlightCaptureMod] " + msg);
        }

        public static void LogWarning(string msg)
        {
            Debug.LogWarning("[HighlightCaptureMod] " + msg);
        }

        public void OnLoaded(OnHarmonyModLoadedArgs args)
        {
            DoOnLoaded();
        }

        public void OnUnloaded(OnHarmonyModUnloadedArgs args)
        {
            DoOnUnloaded();
        }

        void DoOnLoaded()
        {
            Log("OnLoaded called.");
            ModConfig.Load();
            string configPath = ModConfig.ConfigPath;
            if (!string.IsNullOrEmpty(configPath))
                Log("Config: " + configPath);
            if (!ModConfig.Enabled || string.IsNullOrWhiteSpace(ModConfig.ApiUrl))
            {
                Log("Disabled (enabled=false or api_url empty). Edit config in HarmonyMods_Data/HighlightCaptureMod/");
                return;
            }

            try
            {
                Lib.HarmonyInstance = new Harmony("com.prostoj.highlightcapture");
                Lib.HarmonyInstance.PatchAll(typeof(HighlightCaptureModLoader).Assembly);
                if (ModConfig.UploadDemo)
                {
                    var go = new GameObject("HighlightCaptureMod_KillDemoScheduler");
                    UnityEngine.Object.DontDestroyOnLoad(go);
                    go.AddComponent<KillDemoScheduler>();
                    Log("Demo upload enabled: recording killer and sending ZIP.");
                }
                Log($"Loaded. Window: {ModConfig.SecondsBeforeKill}s before, {ModConfig.SecondsAfterKill}s after kill.");
            }
            catch (Exception ex)
            {
                LogWarning("Load failed: " + ex.Message);
            }
        }

        void DoOnUnloaded()
        {
            try
            {
                var h = Lib.HarmonyInstance;
                if (h != null) h.UnpatchAll(h.Id);
                Log("Unloaded.");
            }
            catch (Exception ex)
            {
                LogWarning("Unload error: " + ex.Message);
            }
        }
    }

    public static class Lib
    {
        public static Harmony HarmonyInstance;
    }
}
