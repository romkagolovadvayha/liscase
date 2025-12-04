using System;
using System.Collections.Generic;
using System.Linq;
using System.Text.RegularExpressions;
using Newtonsoft.Json;
using Oxide.Core;
using Oxide.Core.Libraries;
using Oxide.Core.Libraries.Covalence;
using Rust;
using UnityEngine;
using ProtoBuf;
using Server = ConVar.Server;

namespace Oxide.Plugins
{
    [Info("Raid Alerts", "Prostoj", "1.0.0")]
    [Description("Raid Alert.")]
    public class RaidAlerts : CovalencePlugin
    {
        #region Config

        static Configuration config;
        public class Configuration
        {
            [JsonProperty(PropertyName = "Server Tag")] public string server_tag;

            public static Configuration DefaultConfig()
            {
                return new Configuration
                {
                    server_tag = "nolimit" 
                };
            }
        }

        protected override void LoadConfig()
        {
            base.LoadConfig();
            try
            {
                config = Config.ReadObject<Configuration>();
                if (config == null) LoadDefaultConfig();
                SaveConfig();
            }
            catch (Exception e)
            {
                PrintWarning("Создание нового файла конфигурации.");
                LoadDefaultConfig();
            }
        }

        protected override void LoadDefaultConfig() => config = Configuration.DefaultConfig();
        protected override void SaveConfig() => Config.WriteObject(config);

        private class Raid
        {
            public string steam_id = "";
            public string entityLocation = "";
            public string type = "";
            public List<string> explosiveUsed = new List<string>();
            public List<string> owners = new List<string>();
            public string created_at = "";
        };
        private List<Raid> raids = new List<Raid>();
        private readonly List<string> _alertedUsers = new List<string>();
        private readonly List<string> _alertedLocation = new List<string>();
        private readonly Dictionary<string, List<string>> _explosiveUsedUsers = new Dictionary<string, List<string>>();

        void OnServerInitialized(bool initial)
        {
            Puts("Raid Alerts: OnServerInitialized.");

            timer.Every(1 * 60, () =>
            {
                SaveAll();
            });
        }
        void Unload()
        {
            raids.Clear();
            SaveAll();
        }
        #endregion

        #region Save
        void SaveAll()
        {
            if (raids.Count == 0) {
                return;
            }

            string requestBody = JsonConvert.SerializeObject(
                new { 
                    raids = raids,
                }).Replace("\n", "").Replace("  ", "");

            Dictionary<string, string> header = new Dictionary<string, string>();
            header.Add("Content-Type", "application/json");
            webrequest.Enqueue($"https://prostoj.store/api-stats/raid?serverTag={config.server_tag}", requestBody, (code, response) => {}, this, RequestMethod.POST, header, timeout: 1F);
            raids.Clear();
            _explosiveUsedUsers.Clear();
        }

        [Command("raid.save")]
        private void SaveRaidCMD(IPlayer iPlayer, string command, string[] args)
        {
            Puts("Expert Statistics: SaveRaidCMD.");
            if (!iPlayer.IsServer) return;
            SaveAll();
        }
        #endregion

        #region Hooks
        void OnEntityDeath(BuildingPrivlidge entity, HitInfo info)
        {
            if (entity == null || info == null) return;
            BasePlayer attacker = info?.InitiatorPlayer;
            if (attacker == null || !attacker.userID.IsSteamId()) return;
                string entityLocation = GetGrid(entity.transform.position);
                string attackerUserId = info.InitiatorPlayer.UserIDString;
            
            Raid model = new Raid();
                model.steam_id = attackerUserId;
                model.entityLocation = entityLocation;
                model.type = "cupboard";

                if (_explosiveUsedUsers.ContainsKey(attackerUserId)) {
                    model.explosiveUsed = _explosiveUsedUsers[attackerUserId];
                }

                BuildingPrivlidge buildingPrivlidge = entity.GetBuildingPrivilege();
                if (buildingPrivlidge != null)
                {
                    HashSet<ulong> authorizedPlayers = buildingPrivlidge.authorizedPlayers;
                    foreach (ulong authorizedPlayer in authorizedPlayers) {
                        if (attackerUserId == authorizedPlayer.ToString()) {
                            return;
                        }
                        model.owners.Add(authorizedPlayer.ToString());
                    }
                }

                model.created_at = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
                raids.Add(model);

                timer.Once(120f, () => _alertedUsers.Remove(attackerUserId));
                timer.Once(120f, () => _alertedLocation.Remove(entityLocation));

                _alertedUsers.Add(attackerUserId);
                _alertedLocation.Add(entityLocation);
        }
        private void OnEntityDeath(BaseEntity entity, HitInfo info)
        {
            if (entity == null)
                return;
            BasePlayer attacker = info?.InitiatorPlayer;
            if (attacker == null || !attacker.userID.IsSteamId()) return;

            string type = null;    
            // Проверяем тип сущности
            switch (entity.ShortPrefabName)
            {
                case "foundation":
                case "door":
                case "wall":
                case "wall.frame":
                case "roof":
                case "door.double.hinged.wood":
                case "door.double.hinged.metal":
                case "door.double.hinged.toptier":
                case "door.hinged.wood":
                case "door.hinged.metal":
                case "door.hinged.toptier":
                case "wall.external.high.stone":
                case "wall.external.high.wood":
                case "gates.external.high.stone":
                case "gates.external.high.wood":
                case "wall.frame.garagedoor":
                    type = entity.ShortPrefabName;
                    break;
                default:
                    // Если это не стена, дверь или фундамент, ничего не делаем
                    break;
            }

            if (!string.IsNullOrEmpty(type)) {
                if (!info.damageTypes.Has(DamageType.Explosion) || info.InitiatorPlayer == null) {
                    return;
                }
                string entityLocation = GetGrid(entity.transform.position);
                string attackerUserId = info.InitiatorPlayer.UserIDString;

                if (_alertedLocation.Contains(entityLocation)) {
                    return;
                }
                if (_alertedUsers.Contains(attackerUserId)) {
                    return;
                }

                Raid model = new Raid();
                model.steam_id = attackerUserId;
                model.entityLocation = entityLocation;
                model.type = type;

                if (_explosiveUsedUsers.ContainsKey(attackerUserId)) {
                    model.explosiveUsed = _explosiveUsedUsers[attackerUserId];
                }

                BuildingPrivlidge buildingPrivlidge = entity.GetBuildingPrivilege();
                if (buildingPrivlidge != null)
                {
                    HashSet<ulong> authorizedPlayers = buildingPrivlidge.authorizedPlayers;
                    foreach (ulong authorizedPlayer in authorizedPlayers) {
                        if (attackerUserId == authorizedPlayer.ToString()) {
                            return;
                        }
                        model.owners.Add(authorizedPlayer.ToString());
                    }
                }

                model.created_at = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
                raids.Add(model);

                timer.Once(120f, () => _alertedUsers.Remove(attackerUserId));
                timer.Once(120f, () => _alertedLocation.Remove(entityLocation));

                _alertedUsers.Add(attackerUserId);
                _alertedLocation.Add(entityLocation);
            }
        }

        // private void OnEntityTakeDamage(BaseCombatEntity entity, HitInfo info)
        // {
        //     if (!info.damageTypes.Has(DamageType.Explosion) || info.InitiatorPlayer == null) {
        //         return;
        //     }
        //     string entityLocation = GetGrid(entity.transform.position);
        //     string attackerUserId = info.InitiatorPlayer.UserIDString;

        //     if (_alertedLocation.Contains(entityLocation)) {
        //         return;
        //     }
        //     if (_alertedUsers.Contains(attackerUserId)) {
        //         return;
        //     }

        //     Raid model = new Raid();
        //     model.steam_id = attackerUserId;
        //     model.entityLocation = entityLocation;

        //     if (_explosiveUsedUsers.ContainsKey(attackerUserId)) {
        //         model.explosiveUsed = _explosiveUsedUsers[attackerUserId];
        //     }

        //     BuildingPrivlidge buildingPrivlidge = entity.GetBuildingPrivilege();
        //     if (buildingPrivlidge != null)
        //     {
        //         HashSet<ulong> authorizedPlayers = buildingPrivlidge.authorizedPlayers;
        //         foreach (ulong authorizedPlayer in authorizedPlayers) {
        //             if (attackerUserId == authorizedPlayer.ToString()) {
        //                 return;
        //             }
        //             model.owners.Add(authorizedPlayer.ToString());
        //         }
        //     }

        //     model.created_at = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
        //     raids.Add(model);

        //     timer.Once(120f, () => _alertedUsers.Remove(attackerUserId));
        //     timer.Once(120f, () => _alertedLocation.Remove(entityLocation));

        //     _alertedUsers.Add(attackerUserId);
        //     _alertedLocation.Add(entityLocation);

        //     return;
        // }

        void OnRocketLaunched(BasePlayer player, BaseEntity entity)
        {
            if (player == null || entity == null)
                return;
            ExplosionProgressAdd(player, entity);
        }
        void OnWeaponFired(BaseProjectile projectile, BasePlayer player, ItemModProjectile mod, ProtoBuf.ProjectileShoot projectiles)
        {
            if (projectile == null || player == null)
                return;

            string shortname = projectile.primaryMagazine?.ammoType?.shortname;
            string WeaponName = string.IsNullOrWhiteSpace(shortname) == true ? string.Empty : shortname;
            if (!string.IsNullOrEmpty(WeaponName))
            {
                switch (WeaponName)
                {
                    // разрывной патрон
                    case "ammo.rifle.explosive":
                        if (!_explosiveUsedUsers.ContainsKey(player.UserIDString)) {
                            _explosiveUsedUsers.Add(player.UserIDString, new List<string>());
                        }
                        string entityShortName = "ammo_explosive";
                        if (entityShortName != null && !_explosiveUsedUsers[player.UserIDString].Contains(entityShortName)) {
                            _explosiveUsedUsers[player.UserIDString].Add(entityShortName);
                        }
                        break;
                }
            }
        }
        void OnExplosiveThrown(BasePlayer player, BaseEntity entity, ThrownWeapon item)
        {
            if (player == null || item == null)
                return;

            switch (player.GetActiveItem()?.info.shortname)
            {
                case "explosive.timed":
                    if (player.GetActiveItem()?.flags.ToString() != "IsOn") {
                        ExplosionProgressAdd(player, entity);
                    }
                    break;
                case "explosive.satchel":
                    ExplosionProgressAdd(player, entity);
                    break;
            }
        }
        private void ExplosionProgressAdd(BasePlayer player, BaseEntity entity, string shortname = "")
        {
            if (!_explosiveUsedUsers.ContainsKey(player.UserIDString)) {
                _explosiveUsedUsers.Add(player.UserIDString, new List<string>());
            }
            if (entity != null)
            {
                string entityShortName = entity.ShortPrefabName;
                if (entityShortName != null && !_explosiveUsedUsers[player.UserIDString].Contains(entityShortName)) {
                    _explosiveUsedUsers[player.UserIDString].Add(entityShortName);
                }
            }
        }
        #endregion

        #region Utils

        private static string GetGrid(Vector3 pos)
        {
            // Размер одного блока на карте
            const float block = 146f;
            
            // Размер карты
            float size = Server.worldsize;
            
            // Смещение для перевода координат в систему с началом в углу карты
            float offset = size / 2f;
            
            // Перевод координат в систему с началом в углу карты
            float xpos = pos.x + offset;
            float zpos = pos.z + offset;
            
            // Максимальное количество блоков по одной оси
            int maxgrid = (int)(size / block);
            
            // Расчет координат в сетке
            int xcoord = Mathf.Clamp((int)Mathf.Floor(xpos / block), 0, maxgrid - 1);
            int zcoord = Mathf.Clamp(maxgrid - (int)Mathf.Floor(zpos / block), 1, maxgrid);
            
            // Преобразование x координаты в букву (A-Z, затем AA, AB и т.д.)
            string letter;
            if (xcoord < 26)
            {
                letter = ((char)('A' + xcoord)).ToString();
            }
            else
            {
                // Для координат больше 26 используем две буквы (AA, AB, ...)
                int firstLetter = xcoord / 26 - 1;
                int secondLetter = xcoord % 26;
                letter = ((char)('A' + firstLetter)).ToString() + ((char)('A' + secondLetter)).ToString();
            }
            
            return $"{letter}{zcoord}";
        }

        #endregion
    }
}