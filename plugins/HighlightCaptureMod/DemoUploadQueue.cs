using System;
using System.Collections.Generic;
using System.IO;
using System.IO.Compression;
using System.Net.Http;
using System.Text;
using System.Threading;
using UnityEngine;

namespace HighlightCaptureMod
{
    /// <summary>
    /// Очередь загрузки ZIP (демо + manifest) на API, как в RustDemoPro. Заголовки X-Capture-*.
    /// </summary>
    public static class DemoUploadQueue
    {
        struct Item
        {
            public string ZipPath;
            public HighlightPayload Payload;
        }

        static readonly List<Item> Queue = new List<Item>();
        static readonly object Lock = new object();
        static HttpClient _client;
        static bool _workerStarted;

        public static void Enqueue(string zipPath, HighlightPayload payload)
        {
            if (string.IsNullOrEmpty(zipPath) || payload == null) return;
            if (!File.Exists(zipPath)) return;
            lock (Lock)
            {
                Queue.Add(new Item { ZipPath = zipPath, Payload = payload });
                if (!_workerStarted)
                {
                    _workerStarted = true;
                    ThreadPool.QueueUserWorkItem(_ => Worker());
                }
            }
        }

        static void Worker()
        {
            while (true)
            {
                Item item;
                lock (Lock)
                {
                    if (Queue.Count == 0) { _workerStarted = false; return; }
                    item = Queue[0];
                    Queue.RemoveAt(0);
                }
                try
                {
                    SendZip(item.ZipPath, item.Payload);
                }
                catch (Exception ex)
                {
                    HighlightCaptureModLoader.LogWarning("Demo upload failed: " + ex.Message);
                }
                finally
                {
                    try { if (File.Exists(item.ZipPath)) File.Delete(item.ZipPath); } catch { }
                }
            }
        }

        static void SendZip(string zipPath, HighlightPayload p)
        {
            string url = ModConfig.UploadApiUrl;
            if (string.IsNullOrWhiteSpace(url)) return;
            if (_client == null) _client = new HttpClient { Timeout = TimeSpan.FromSeconds(60) };

            using (var content = new StreamContent(File.OpenRead(zipPath), 65536))
            {
                content.Headers.ContentType = new System.Net.Http.Headers.MediaTypeHeaderValue("application/zip");
                var request = new HttpRequestMessage(HttpMethod.Post, url) { Content = content };
                if (!string.IsNullOrWhiteSpace(ModConfig.ApiKey))
                    request.Headers.TryAddWithoutValidation("X-Api-Key", ModConfig.ApiKey);
                request.Headers.TryAddWithoutValidation("X-Capture-Before-Minutes", ((double)p.seconds_before / 60.0).ToString(System.Globalization.CultureInfo.InvariantCulture));
                request.Headers.TryAddWithoutValidation("X-Capture-After-Minutes", ((double)p.seconds_after / 60.0).ToString(System.Globalization.CultureInfo.InvariantCulture));
                if (!string.IsNullOrEmpty(p.window_start_utc)) request.Headers.TryAddWithoutValidation("X-Capture-Start-Utc", p.window_start_utc.Length > 64 ? p.window_start_utc.Substring(0, 64) : p.window_start_utc);
                if (!string.IsNullOrEmpty(p.window_end_utc)) request.Headers.TryAddWithoutValidation("X-Capture-End-Utc", p.window_end_utc.Length > 64 ? p.window_end_utc.Substring(0, 64) : p.window_end_utc);

                var response = _client.SendAsync(request).GetAwaiter().GetResult();
                if (ModConfig.LogToConsole)
                    HighlightCaptureModLoader.Log($"Demo upload response: {(int)response.StatusCode} for {p.killer_name} -> {p.victim_name}");
                if (response.StatusCode < System.Net.HttpStatusCode.OK || response.StatusCode >= (System.Net.HttpStatusCode)300)
                    HighlightCaptureModLoader.LogWarning($"Demo upload API error: {(int)response.StatusCode}");
            }
        }

        /// <summary>Собрать ZIP: demoPath + manifest.json (payload), вернуть путь к временному файлу.</summary>
        public static string BuildZip(string demoPath, HighlightPayload payload)
        {
            if (string.IsNullOrEmpty(demoPath) || !File.Exists(demoPath) || payload == null) return null;
            string tempZip = Path.Combine(Path.GetTempPath(), "HighlightCaptureMod_" + Guid.NewGuid().ToString("N") + ".zip");
            try
            {
                using (var fs = new FileStream(tempZip, FileMode.Create, FileAccess.Write, FileShare.None))
                using (var zip = new ZipArchive(fs, ZipArchiveMode.Create))
                {
                    var demoEntry = zip.CreateEntry(Path.GetFileName(demoPath), System.IO.Compression.CompressionLevel.Fastest);
                    using (var src = File.OpenRead(demoPath))
                    using (var dst = demoEntry.Open())
                        src.CopyTo(dst);
                    var manifestEntry = zip.CreateEntry("manifest.json", System.IO.Compression.CompressionLevel.Fastest);
                    string manifestJson = JsonUtility.ToJson(payload);
                    using (var w = new StreamWriter(manifestEntry.Open(), Encoding.UTF8))
                        w.Write(manifestJson);
                }
                return tempZip;
            }
            catch (Exception ex)
            {
                HighlightCaptureModLoader.LogWarning("BuildZip: " + ex.Message);
                try { if (File.Exists(tempZip)) File.Delete(tempZip); } catch { }
                return null;
            }
        }
    }
}
