using System;
using System.Reflection;
using HarmonyLib;
using UnityEngine;
using Network;

namespace HighlightCaptureMod.HarmonyPatches
{
    /// <summary>
    /// Патч на BaseCombatEntity.Die(HitInfo) — смерть игрока (килл). По образцу RustDemoPro.
    /// </summary>
    [HarmonyPatch]
    public static class EntityDeathPatch
    {
        static MethodBase TargetMethod()
        {
            Type type = typeof(BaseCombatEntity);
            foreach (MethodInfo m in type.GetMethods(BindingFlags.Instance | BindingFlags.Public | BindingFlags.NonPublic))
            {
                if (!string.Equals(m.Name, "Die", StringComparison.Ordinal)) continue;
                ParameterInfo[] ps = m.GetParameters();
                if (ps.Length == 1 && ps[0].ParameterType.Name == "HitInfo")
                {
                    HighlightCaptureModLoader.Log("Patched BaseCombatEntity.Die(HitInfo).");
                    return m;
                }
            }
            foreach (MethodInfo m in type.GetMethods(BindingFlags.Instance | BindingFlags.Public | BindingFlags.NonPublic))
            {
                if (string.Equals(m.Name, "Die", StringComparison.Ordinal) && m.GetParameters().Length == 0)
                {
                    HighlightCaptureModLoader.Log("Patched BaseCombatEntity.Die() (no HitInfo).");
                    return m;
                }
            }
            HighlightCaptureModLoader.LogWarning("BaseCombatEntity.Die not found.");
            return null;
        }

        static void Postfix(BaseCombatEntity __instance, HitInfo info)
        {
            try
            {
                KillCaptureService.OnEntityDeath(__instance, info);
            }
            catch (Exception ex)
            {
                HighlightCaptureModLoader.LogWarning("Postfix error: " + ex.Message);
            }
        }
    }
}
