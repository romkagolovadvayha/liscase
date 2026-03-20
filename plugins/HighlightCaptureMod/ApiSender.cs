using System;
using System.Collections.Generic;
using System.Net.Http;
using UnityEngine;
using System.Text;
using System.Threading;

namespace HighlightCaptureMod
{
    /// <summary>
    /// Очередь и отправка метаданных хайлайта на API (POST JSON). Выполняется в фоне.
    /// </summary>
    public static class ApiSender
    {
        static readonly List<HighlightPayload> Queue = new List<HighlightPayload>();
        static readonly object Lock = new object();
        static HttpClient _client;
        static bool _workerStarted;

        public static void Enqueue(HighlightPayload payload)
        {
            if (payload == null) return;
            lock (Lock)
            {
                Queue.Add(payload);
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
                HighlightPayload item;
                lock (Lock)
                {
                    if (Queue.Count == 0)
                    {
                        _workerStarted = false;
                        return;
                    }
                    item = Queue[0];
                    Queue.RemoveAt(0);
                }

                try
                {
                    if (ModConfig.LogToConsole)
                        HighlightCaptureModLoader.Log($"Sending highlight: {item.killer_name} -> {item.victim_name}");
                    SendOne(item);
                }
                catch (Exception ex)
                {
                    HighlightCaptureModLoader.LogWarning("Send failed: " + ex.Message);
                }
            }
        }

        static void SendOne(HighlightPayload payload)
        {
            if (string.IsNullOrWhiteSpace(ModConfig.ApiUrl)) return;

            if (_client == null)
                _client = new HttpClient { Timeout = TimeSpan.FromSeconds(15) };

            string json = JsonUtility.ToJson(payload);
            var content = new StringContent(json, Encoding.UTF8, "application/json");

            var request = new HttpRequestMessage(HttpMethod.Post, ModConfig.ApiUrl)
            {
                Content = content
            };
            if (!string.IsNullOrWhiteSpace(ModConfig.ApiKey))
                request.Headers.TryAddWithoutValidation("X-Api-Key", ModConfig.ApiKey);

            var response = _client.SendAsync(request).GetAwaiter().GetResult();
            if (ModConfig.LogToConsole)
                HighlightCaptureModLoader.Log($"API response: {(int)response.StatusCode} for {payload.killer_name} -> {payload.victim_name}");

            if (response.StatusCode < System.Net.HttpStatusCode.OK || response.StatusCode >= (System.Net.HttpStatusCode)300)
                HighlightCaptureModLoader.LogWarning($"API error: {(int)response.StatusCode} {response.ReasonPhrase}");
        }
    }
}
