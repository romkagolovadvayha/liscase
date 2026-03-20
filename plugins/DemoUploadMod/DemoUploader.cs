using System;
using System.Collections;
using System.IO;
using UnityEngine;
using UnityEngine.Networking;

namespace DemoUploadMod
{
    public static class DemoUploader
    {
        public static IEnumerator UploadCoroutine(string filePath, Action onDone)
        {
            if (string.IsNullOrEmpty(filePath) || !File.Exists(filePath))
            {
                onDone?.Invoke();
                yield break;
            }

            if (!Config.Enabled || string.IsNullOrWhiteSpace(Config.UploadUrl))
            {
                onDone?.Invoke();
                yield break;
            }

            byte[] bytes = File.ReadAllBytes(filePath);
            string fileName = Path.GetFileName(filePath);

            var form = new WWWForm();
            form.AddBinaryData("demo", bytes, fileName, "application/octet-stream");
            form.AddField("timestamp_utc", DateTime.UtcNow.ToString("o"));

            // Steam ID — если в сборке есть Steamworks
            try
            {
                var steamId = GetSteamId();
                if (!string.IsNullOrEmpty(steamId))
                    form.AddField("steam_id", steamId);
            }
            catch { /* Steamworks может отсутствовать */ }

            using (var req = UnityWebRequest.Post(Config.UploadUrl, form))
            {
                req.timeout = 60;
                if (!string.IsNullOrWhiteSpace(Config.ApiKey))
                    req.SetRequestHeader("X-Api-Key", Config.ApiKey);

                yield return req.SendWebRequest();

                if (req.result != UnityWebRequest.Result.Success)
                    DemoUploadModLoader.LogWarning($"Ошибка загрузки: {req.responseCode} {req.error}");
                else
                    DemoUploadModLoader.Log($"Загружено: {fileName}");
            }

            onDone?.Invoke();
        }

        private static string GetSteamId()
        {
            try
            {
                // Steamworks.NET / Facepunch.Steamworks
                var t = Type.GetType("Steamworks.SteamUser, Assembly-CSharp");
                t = t ?? Type.GetType("Steamworks.SteamUser, Facepunch.Steamworks");
                if (t != null)
                {
                    var m = t.GetMethod("GetSteamID", System.Reflection.BindingFlags.Public | System.Reflection.BindingFlags.Static);
                    if (m != null)
                    {
                        var id = m.Invoke(null, null);
                        return id?.ToString() ?? "";
                    }
                }
            }
            catch { }
            return "";
        }
    }
}
