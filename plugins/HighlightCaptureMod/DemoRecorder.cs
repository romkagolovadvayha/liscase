using System;
using System.IO;
using System.Reflection;
using UnityEngine;
using Network;

namespace HighlightCaptureMod
{
    /// <summary>
    /// Запуск/остановка серверной записи демо для игрока и получение пути к .dem (как в RustDemoPro).
    /// </summary>
    public static class DemoRecorder
    {
        static MethodInfo _stopRecordingOnConnection;

        public static string GetDemosRoot()
        {
            try
            {
                string dataPath = Application.dataPath;
                if (string.IsNullOrEmpty(dataPath)) return null;
                string root = Path.GetDirectoryName(dataPath);
                if (string.IsNullOrEmpty(root)) return null;
                string demos = Path.Combine(root, "demos");
                return Directory.Exists(demos) ? demos : root;
            }
            catch { return null; }
        }

        public static bool StartRecording(BasePlayer player)
        {
            if (player == null || player.Connection == null) return false;
            try
            {
                if (IsRecording(player)) return true;
                player.StartDemoRecording();
                return IsRecording(player);
            }
            catch (Exception ex)
            {
                HighlightCaptureModLoader.LogWarning("StartRecording: " + ex.Message);
                return false;
            }
        }

        public static bool StopRecording(BasePlayer player)
        {
            if (player == null) return false;
            try
            {
                if (player.Connection != null && IsRecording(player))
                {
                    player.StopDemoRecording();
                    return true;
                }
                if (player.Connection != null)
                {
                    if (_stopRecordingOnConnection == null)
                        _stopRecordingOnConnection = player.Connection.GetType().GetMethod("StopRecording", BindingFlags.Instance | BindingFlags.Public | BindingFlags.NonPublic);
                    if (_stopRecordingOnConnection != null)
                    {
                        _stopRecordingOnConnection.Invoke(player.Connection, null);
                        return true;
                    }
                }
            }
            catch (Exception ex)
            {
                HighlightCaptureModLoader.LogWarning("StopRecording: " + ex.Message);
            }
            return false;
        }

        public static bool IsRecording(BasePlayer player)
        {
            try
            {
                return player?.Connection != null && player.Connection.IsRecording;
            }
            catch { return false; }
        }

        /// <summary>Путь к текущему/последнему записанному .dem (RecordFilename на Connection или последний файл в demos).</summary>
        public static string GetRecordedDemoPath(BasePlayer player)
        {
            if (player?.Connection == null) return null;
            try
            {
                var conn = player.Connection;
                var prop = conn.GetType().GetProperty("RecordFilename", BindingFlags.Instance | BindingFlags.Public | BindingFlags.NonPublic);
                if (prop != null)
                {
                    string path = prop.GetValue(conn, null) as string;
                    if (!string.IsNullOrEmpty(path))
                    {
                        string root = GetDemosRoot();
                        if (!string.IsNullOrEmpty(root) && !Path.IsPathRooted(path))
                            path = Path.Combine(root, path.TrimStart(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar));
                        if (File.Exists(path)) return path;
                    }
                }
            }
            catch { }
            return FindLatestDemoInDemos();
        }

        public static string FindLatestDemoInDemos()
        {
            string root = GetDemosRoot();
            if (string.IsNullOrEmpty(root) || !Directory.Exists(root)) return null;
            try
            {
                string[] files = Directory.GetFiles(root, "*.dem", SearchOption.AllDirectories);
                if (files == null || files.Length == 0) return null;
                string latest = null;
                DateTime latestTime = DateTime.MinValue;
                foreach (string f in files)
                {
                    try
                    {
                        var t = File.GetLastWriteTimeUtc(f);
                        if (t > latestTime) { latestTime = t; latest = f; }
                    }
                    catch { }
                }
                return latest;
            }
            catch { }
            return null;
        }
    }
}
