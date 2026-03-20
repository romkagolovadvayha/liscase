using System;
using System.IO;
using UnityEngine;

namespace HighlightCaptureMod
{
    [Serializable]
    public class ModConfigData
    {
        public bool enabled = true;
        public string api_url = "https://yoursite.com/api/rust/highlights";
        /// <summary>URL для загрузки ZIP с демкой (если пусто — используется api_url).</summary>
        public string upload_api_url = "";
        public string api_key = "";
        public string server_id = "main";
        public int seconds_before_kill = 15;
        public int seconds_after_kill = 15;
        /// <summary>Записывать демку киллера и отправлять ZIP на upload_api_url (как RustDemoPro).</summary>
        public bool upload_demo = false;
        public bool log_to_console = false;
    }

    /// <summary>Конфиг: путь через Application.dataPath (корень инстанса сервера), чтобы работало при запуске из подпапки (survivalhost.org и т.д.).</summary>
    public static class ModConfig
    {
        const string RELATIVE_PATH = "HarmonyMods_Data/HighlightCaptureMod/HighlightCaptureMod.json";
        static string _configPath;

        static ModConfigData _data;

        /// <summary>Абсолютный путь к конфигу: рядом с RustDedicated_Data инстанса (работает при любом CWD и мульти-инстансе).</summary>
        static string GetConfigPath()
        {
            if (_configPath != null) return _configPath;
            try
            {
                string dataPath = Application.dataPath;
                if (string.IsNullOrEmpty(dataPath)) { _configPath = RELATIVE_PATH; return _configPath; }
                string root = Path.GetDirectoryName(dataPath);
                if (string.IsNullOrEmpty(root)) { _configPath = RELATIVE_PATH; return _configPath; }
                _configPath = Path.Combine(root, "HarmonyMods_Data", "HighlightCaptureMod", "HighlightCaptureMod.json");
                return _configPath;
            }
            catch { _configPath = RELATIVE_PATH; return _configPath; }
        }

        public static bool Enabled => _data?.enabled ?? true;
        public static string ApiUrl => _data?.api_url ?? "";
        /// <summary>URL для загрузки ZIP (если пусто — api_url).</summary>
        public static string UploadApiUrl => !string.IsNullOrWhiteSpace(_data?.upload_api_url) ? _data.upload_api_url.Trim() : (ApiUrl ?? "");
        public static string ApiKey => _data?.api_key ?? "";
        public static string ServerId => _data?.server_id ?? "";
        public static int SecondsBeforeKill => _data != null && _data.seconds_before_kill > 0 ? _data.seconds_before_kill : 15;
        public static int SecondsAfterKill => _data != null && _data.seconds_after_kill > 0 ? _data.seconds_after_kill : 15;
        public static bool UploadDemo => _data?.upload_demo ?? false;
        public static bool LogToConsole => _data?.log_to_console ?? false;
        public static string ConfigPath => GetConfigPath();

        public static void Load()
        {
            string path = GetConfigPath();
            try
            {
                if (File.Exists(path))
                {
                    string configStr = File.ReadAllText(path);
                    _data = JsonUtility.FromJson<ModConfigData>(configStr);
                    if (_data != null)
                    {
                        Debug.Log("[HighlightCaptureMod] Config loaded from " + path);
                        return;
                    }
                }
            }
            catch (Exception ex)
            {
                Debug.LogWarning("[HighlightCaptureMod] Config read error: " + ex.Message);
            }

            Debug.LogError("[HighlightCaptureMod]: The configuration seems to be missing or malformed. Defaults will be loaded.");
            _data = new ModConfigData();

            if (File.Exists(path))
                return;

            SaveConfiguration();
        }

        static void SaveConfiguration()
        {
            string path = GetConfigPath();
            try
            {
                var configFileInfo = new FileInfo(path);
                if (configFileInfo.Directory != null && !configFileInfo.Directory.Exists)
                    configFileInfo.Directory.Create();
                string serializedConfiguration = JsonUtility.ToJson(_data ?? new ModConfigData(), true);
                File.WriteAllText(path, serializedConfiguration);
                Debug.Log("[HighlightCaptureMod] Default config saved to " + path);
            }
            catch (Exception ex)
            {
                Debug.LogError("[HighlightCaptureMod]: Failed to write configuration file: " + ex.Message);
                Debug.LogException(ex);
            }
        }
    }
}
