using System;
using System.Collections.Generic;
using UnityEngine;
using Network;

namespace HighlightCaptureMod
{
    /// <summary>
    /// По PvP-киллу запускает запись демо киллера, через N сек останавливает, собирает ZIP и ставит в очередь на загрузку.
    /// </summary>
    public class KillDemoScheduler : MonoBehaviour
    {
        struct Pending
        {
            public ulong KillerUserId;
            public HighlightPayload Payload;
            public float RunAtTime;
        }

        static List<Pending> _pending = new List<Pending>();
        static readonly object _lock = new object();

        public static void OnKill(BasePlayer killer, HighlightPayload payload)
        {
            if (!ModConfig.UploadDemo || string.IsNullOrWhiteSpace(ModConfig.UploadApiUrl)) return;
            if (killer == null || payload == null) return;
            if (!DemoRecorder.StartRecording(killer))
            {
                HighlightCaptureModLoader.LogWarning("KillDemo: failed to start recording for " + killer.displayName);
                return;
            }
            float runAt = Time.time + ModConfig.SecondsAfterKill;
            lock (_lock)
            {
                _pending.Add(new Pending { KillerUserId = killer.userID, Payload = payload, RunAtTime = runAt });
            }
            if (ModConfig.LogToConsole)
                HighlightCaptureModLoader.Log($"KillDemo: recording started for {killer.displayName}, upload in {ModConfig.SecondsAfterKill}s");
        }

        void Update()
        {
            float now = Time.time;
            List<Pending> toRun = null;
            lock (_lock)
            {
                for (int i = _pending.Count - 1; i >= 0; i--)
                {
                    if (_pending[i].RunAtTime <= now)
                    {
                        if (toRun == null) toRun = new List<Pending>();
                        toRun.Add(_pending[i]);
                        _pending.RemoveAt(i);
                    }
                }
            }
            if (toRun == null) return;
            foreach (var p in toRun)
                ProcessOne(p);
        }

        static void ProcessOne(Pending p)
        {
            BasePlayer killer = BasePlayer.FindByID(p.KillerUserId);
            if (killer != null)
                DemoRecorder.StopRecording(killer);
            string demoPath = null;
            if (killer != null)
                demoPath = DemoRecorder.GetRecordedDemoPath(killer);
            if (string.IsNullOrEmpty(demoPath))
                demoPath = DemoRecorder.FindLatestDemoInDemos();
            if (string.IsNullOrEmpty(demoPath) || !System.IO.File.Exists(demoPath))
            {
                HighlightCaptureModLoader.LogWarning("KillDemo: no demo file found after stop");
                return;
            }
            string zipPath = DemoUploadQueue.BuildZip(demoPath, p.Payload);
            if (!string.IsNullOrEmpty(zipPath))
                DemoUploadQueue.Enqueue(zipPath, p.Payload);
        }

        void OnDestroy()
        {
            lock (_lock) _pending.Clear();
        }
    }
}
