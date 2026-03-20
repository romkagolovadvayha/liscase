using System;
using System.IO;
using System.Reflection;
using HarmonyLib;
using UnityEngine;

namespace DemoUploadMod.HarmonyPatches
{
    /// <summary>
    /// Патч на метод, который вызывается при остановке записи демо в клиенте Rust.
    /// Точное имя типа и метода нужно подставить после просмотра клиента в dnSpy/ILSpy
    /// (поиск по "demos", ".dem", "StopRecording", "IsRecording").
    /// Если не указать TargetTypeName / TargetMethodName, патч не применится — тогда включи fallback через DemoUploadWatcher.
    /// </summary>
    [HarmonyPatch]
    public static class DemoRecordingStop_Patch
    {
        // Укажи сюда тип и метод из клиента Rust после декомпиляции, например:
        // private const string TargetTypeName = "Net.Client";
        // private const string TargetMethodName = "StopRecording";
        private const string TargetTypeName = "";
        private const string TargetMethodName = "StopRecording";

        [HarmonyTargetMethod]
        static MethodBase FindStopRecordingMethod()
        {
            if (string.IsNullOrWhiteSpace(TargetTypeName) || string.IsNullOrWhiteSpace(TargetMethodName))
            {
                DemoUploadModLoader.LogWarning("TargetTypeName или TargetMethodName не заданы. Заполни их после декомпиляции.");
                return null;
            }

            foreach (var asm in AppDomain.CurrentDomain.GetAssemblies())
            {
                if (asm.GetName().Name != "Assembly-CSharp")
                    continue;
                var type = asm.GetType(TargetTypeName);
                if (type == null)
                    continue;
                var method = type.GetMethod(TargetMethodName, BindingFlags.Public | BindingFlags.NonPublic | BindingFlags.Instance);
                if (method != null)
                {
                    DemoUploadModLoader.Log($"Патч применён к {TargetTypeName}.{TargetMethodName}");
                    return method;
                }
            }

            DemoUploadModLoader.LogWarning($"Метод {TargetTypeName}.{TargetMethodName} не найден.");
            return null;
        }

        [HarmonyPostfix]
        static void AfterStopRecording()
        {
            if (!Config.Enabled)
                return;

            try
            {
                string demosFolder = GetDemosFolder();
                if (string.IsNullOrEmpty(demosFolder) || !Directory.Exists(demosFolder))
                    return;

                var dir = new DirectoryInfo(demosFolder);
                FileInfo latest = null;
                foreach (var f in dir.GetFiles("*.dem"))
                {
                    if (latest == null || f.LastWriteTimeUtc > latest.LastWriteTimeUtc)
                        latest = f;
                }

                if (latest != null)
                {
                    var go = new GameObject("DemoUploadMod_Upload");
                    UnityEngine.Object.DontDestroyOnLoad(go);
                    go.AddComponent<DemoUploadBehaviour>().StartUpload(latest.FullName);
                }
            }
            catch (Exception ex)
            {
                DemoUploadModLoader.LogError(ex.ToString());
            }
        }

        static string GetDemosFolder()
        {
            // Стандартная папка демок Rust (рядом с exe)
            string dataPath = Application.dataPath;
            if (!string.IsNullOrEmpty(dataPath))
            {
                string candidate = Path.Combine(Path.GetDirectoryName(dataPath), "demos");
                if (Directory.Exists(candidate))
                    return candidate;
            }
            // Fallback: типичный путь Steam
            string steam = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
            steam = Path.Combine(steam, "..", "LocalLow", "Facepunch Studios", "Rust", "demos");
            return Path.GetFullPath(steam);
        }
    }

    public class DemoUploadBehaviour : MonoBehaviour
    {
        public void StartUpload(string filePath)
        {
            StartCoroutine(DemoUploader.UploadCoroutine(filePath, () => Destroy(gameObject)));
        }
    }
}
