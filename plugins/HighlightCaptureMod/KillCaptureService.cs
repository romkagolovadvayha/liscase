using System;
using UnityEngine;
using Network;

namespace HighlightCaptureMod
{
    /// <summary>
    /// Обработка смерти: только PvP (жертва и киллер — игроки). Окно 15 сек до / 15 сек после, отправка на API.
    /// </summary>
    public static class KillCaptureService
    {
        public static void OnEntityDeath(BaseCombatEntity entity, HitInfo info)
        {
            if (entity == null || !ModConfig.Enabled || string.IsNullOrWhiteSpace(ModConfig.ApiUrl))
                return;

            BasePlayer victim = entity as BasePlayer ?? (entity as BaseEntity)?.ToPlayer();
            if (victim == null)
                return;

            BasePlayer killer = null;
            if (info != null && info.Initiator != null)
            {
                var p = info.Initiator as BasePlayer;
                if (p != null) killer = p;
                else
                {
                    var npc = info.Initiator as BaseCombatEntity;
                    if (npc == null) { }
                    else killer = null; // NPC kill — можно не отправлять или отправлять без killer
                }
            }

            // Только PvP: оба — игроки
            if (killer == null || victim == null)
                return;
            if (killer.IsNpc || victim.IsNpc)
                return;

            DateTime killUtc = DateTime.UtcNow;
            int before = ModConfig.SecondsBeforeKill;
            int after = ModConfig.SecondsAfterKill;
            DateTime windowStart = killUtc.AddSeconds(-before);
            DateTime windowEnd = killUtc.AddSeconds(after);

            string weaponShort = null;
            string weaponPrefab = null;
            if (info?.Weapon != null)
            {
                weaponShort = info.Weapon.ShortPrefabName;
                weaponPrefab = info.Weapon.PrefabName;
            }
            else if (info?.WeaponPrefab != null)
            {
                weaponShort = info.WeaponPrefab.ShortPrefabName;
                weaponPrefab = info.WeaponPrefab.PrefabName;
            }

            float distance = 0f;
            try
            {
                if (killer != null && victim != null)
                    distance = Vector3.Distance(killer.transform.position, entity.transform.position);
            }
            catch { }

            var payload = new HighlightPayload
            {
                killer_steam_id = killer.UserIDString,
                killer_name = killer.displayName ?? "",
                victim_steam_id = victim.UserIDString,
                victim_name = victim.displayName ?? "",
                kill_timestamp_utc = killUtc.ToString("o"),
                window_start_utc = windowStart.ToString("o"),
                window_end_utc = windowEnd.ToString("o"),
                seconds_before = before,
                seconds_after = after,
                server_id = ModConfig.ServerId ?? "",
                weapon_short_name = weaponShort ?? "",
                weapon_prefab = weaponPrefab ?? "",
                distance = distance
            };

            ApiSender.Enqueue(payload);
            if (ModConfig.UploadDemo)
                KillDemoScheduler.OnKill(killer, payload);
            if (ModConfig.LogToConsole)
                HighlightCaptureModLoader.Log($"PvP kill: {payload.killer_name} -> {payload.victim_name} ({payload.weapon_short_name ?? "?"})");
        }
    }

    [Serializable]
    public class HighlightPayload
    {
        public string killer_steam_id;
        public string killer_name;
        public string victim_steam_id;
        public string victim_name;
        public string kill_timestamp_utc;
        public string window_start_utc;
        public string window_end_utc;
        public int seconds_before;
        public int seconds_after;
        public string server_id;
        public string weapon_short_name;
        public string weapon_prefab;
        public float distance;
    }
}
