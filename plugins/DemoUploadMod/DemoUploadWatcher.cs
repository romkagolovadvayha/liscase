using System;
using System.Collections;
using System.IO;
using UnityEngine;
using DemoUploadMod.HarmonyPatches;

namespace DemoUploadMod
{
    /// <summary>
    /// Fallback: если Harmony-патч не применился (не указан тип/метод в клиенте),
    /// раз в N секунд проверяем папку демок и загружаем последний новый .dem.
    /// Включи в лоадере, если не используешь патч.
    /// </summary>
    public class DemoUploadWatcher : MonoBehaviour
    {
        public static string LastUploadedPath;
        public static DateTime LastUploadedTime = DateTime.MinValue;
        private const float CheckInterval = 8f;
        private float _nextCheck;

        private void Update()
        {
            if (!Config.Enabled)
                return;

            _nextCheck -= Time.deltaTime;
            if (_nextCheck > 0f)
                return;

            _nextCheck = CheckInterval;
            StartCoroutine(CheckAndUploadLatest());
        }

        private static IEnumerator CheckAndUploadLatest()
        {
            string demosFolder = GetDemosFolder();
            if (string.IsNullOrEmpty(demosFolder) || !Directory.Exists(demosFolder))
                yield break;

            FileInfo latest = null;
            try
            {
                var dir = new DirectoryInfo(demosFolder);
                foreach (var f in dir.GetFiles("*.dem"))
                {
                    if (latest == null || f.LastWriteTimeUtc > latest.LastWriteTimeUtc)
                        latest = f;
                }
            }
            catch
            {
                yield break;
            }

            if (latest == null)
                yield break;

            // Не загружать один и тот же файл повторно (или если только что загружен)
            if (string.Equals(latest.FullName, LastUploadedPath, StringComparison.OrdinalIgnoreCase))
                yield break;
            if ((DateTime.UtcNow - latest.LastWriteTimeUtc).TotalSeconds > 30)
                yield break;

            LastUploadedPath = latest.FullName;
            LastUploadedTime = DateTime.UtcNow;

            var go = new GameObject("DemoUploadMod_Upload");
            DontDestroyOnLoad(go);
            go.AddComponent<DemoUploadBehaviour>().StartUpload(latest.FullName);
        }

        private static string GetDemosFolder()
        {
            string dataPath = Application.dataPath;
            if (!string.IsNullOrEmpty(dataPath))
            {
                string candidate = Path.Combine(Path.GetDirectoryName(dataPath), "demos");
                if (Directory.Exists(candidate))
                    return candidate;
            }
            string steam = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
            steam = Path.Combine(steam, "..", "LocalLow", "Facepunch Studios", "Rust", "demos");
            return Path.GetFullPath(steam);
        }
    }
}
