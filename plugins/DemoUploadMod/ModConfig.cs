using System.IO;
using UnityEngine;

namespace DemoUploadMod
{
    /// <summary>
    /// Конфиг мода с сервера (файл DemoUploadMod.json рядом с RustDedicated_Data или в корне сервера).
    /// </summary>
    public static class ModConfig
    {
        public static string UploadUrl { get; private set; } = "";
        public static string ApiKey { get; private set; } = "";
        public static bool Enabled { get; private set; } = true;
        public static bool UseFolderWatcherFallback { get; private set; } = false;

        public static void Load()
        {
            string path = GetConfigPath();
            if (string.IsNullOrEmpty(path) || !File.Exists(path))
            {
                UploadUrl = "https://yoursite.com/api/rust/highlights/upload";
                ApiKey = "";
                Enabled = true;
                UseFolderWatcherFallback = false;
                return;
            }

            try
            {
                string json = File.ReadAllText(path);
                var o = JsonUtility.FromJson<ConfigJson>(json);
                if (o != null)
                {
                    UploadUrl = o.upload_url ?? "";
                    ApiKey = o.api_key ?? "";
                    Enabled = o.enabled;
                    UseFolderWatcherFallback = o.use_folder_watcher_fallback;
                }
            }
            catch
            {
                // оставляем значения по умолчанию
            }
        }

        private static string GetConfigPath()
        {
            try
            {
                // RustDedicated_Data — рядом с исполняемым файлом
                string dataPath = Application.dataPath;
                if (!string.IsNullOrEmpty(dataPath))
                {
                    string dir = Path.GetDirectoryName(dataPath);
                    string candidate = Path.Combine(dir, "DemoUploadMod.json");
                    if (File.Exists(candidate)) return candidate;
                    candidate = Path.Combine(dataPath, "..", "DemoUploadMod.json");
                    if (File.Exists(Path.GetFullPath(candidate))) return Path.GetFullPath(candidate);
                }
            }
            catch { }
            return "";
        }

        [System.Serializable]
        private class ConfigJson
        {
            public string upload_url;
            public string api_key;
            public bool enabled = true;
            public bool use_folder_watcher_fallback = false;
        }
    }
}
