using System;
using System.Collections.Generic;
using System.Linq;
using Oxide.Core;
using Oxide.Core.Plugins;
using Oxide.Game.Rust;
using Oxide.Game.Rust.Cui;
using Oxide.Core.Libraries;
using UnityEngine;
using Newtonsoft.Json.Linq;
using Newtonsoft.Json;
using System.Collections;
using System.Diagnostics;
using System.Net;
using UnityEngine.Networking;



namespace Oxide.Plugins
{
    [Info("ClanManager", "CA$HR(discord: CASHR#6906)", "1.1.4")]
    public class ClanManager : RustPlugin
    {
        #region Var

        private readonly Dictionary<ulong, EntityCache> _playerEntities = new Dictionary<ulong, EntityCache>();
        private HashSet<ulong> _playersWithoutClan = new HashSet<ulong>();

        private class EntityCache
        {
            public readonly HashSet<BuildingPrivlidge> cupboards = new HashSet<BuildingPrivlidge>();
            public readonly HashSet<CodeLock> codeLocks = new HashSet<CodeLock>();
            public readonly HashSet<AutoTurret> turrets = new HashSet<AutoTurret>();
            public readonly HashSet<SamSite> samSites = new HashSet<SamSite>();
        }


        #endregion


        #region Config

        private Configuration _config;

        public class Configuration
        {
            [JsonProperty("updateIntervalSeconds")]
            public int UpdateIntervalSeconds = 30;

            [JsonProperty("apiBaseUrl")]
            public string ApiBaseUrl = "https://api.moscow77.store.store/v1";

            [JsonProperty("codeAuthMode")]
            public string CodeAuthMode = "guestPlayers"; // or whitelistPlayers
        }

        protected override void LoadDefaultConfig()
        {
            _config = new Configuration();
            SaveConfig();
        }

        protected override void LoadConfig()
        {
            base.LoadConfig();
            try
            {
                _config = Config.ReadObject<Configuration>();
            }
            catch
            {
                PrintError("Ошибка чтения конфига. Используется стандартный.");
                LoadDefaultConfig();
            }
        }

        protected override void SaveConfig() => Config.WriteObject(_config);

        #endregion

        #region Data

        public class ClanUser
        {
            [JsonProperty("steam_id")]
            public string steam_id;

            [JsonIgnore]
            public ulong SteamId
            {
                get
                {
                    if (ulong.TryParse(steam_id, out var id))
                        return id;
                    return 0;
                }
            }

            [JsonProperty("lock")]
            public bool Lock;
            [JsonProperty("turrets")]
            public bool turrets;
            [JsonProperty("defense")]
            public bool defense;
            [JsonProperty("cupboard_auth")]
            public bool cupboard_auth;
        }

        public class ClanData
        {
            [JsonProperty("tag")]
            public string tag;
            [JsonProperty("color_tag")]
            public string color_tag;
            [JsonProperty("update_at")]
            public DateTime update_at;
            [JsonProperty("users")]
            public List<ClanUser> users = new List<ClanUser>();
        }

        private List<ClanData> Clans = new List<ClanData>();

        #endregion

        #region API

        /// <summary>
        /// Для дополнительных плагинов (например ClanCupboardReporter): актуальный кэш кланов без HTTP.
        /// </summary>
        public string API_GetClansSnapshotJson()
        {
            try
            {
                return JsonConvert.SerializeObject(Clans ?? new List<ClanData>());
            }
            catch
            {
                return "[]";
            }
        }

        private void FetchClansWebRequest()
        {
            string serverIp = ConVar.Server.ip;
            int serverPort = ConVar.Server.port;
            string url = $"{_config.ApiBaseUrl}/clans/list?ip={Uri.EscapeDataString(serverIp)}&port={serverPort}";
            webrequest.Enqueue(url, null, (code, response) =>
            {
                if(code != 200)
                {
                    PrintError($"Failed to send request to server. Response: {response}");
                }
                else
                {
                    var clans = JsonConvert.DeserializeObject<List<ClanData>>(response);
                    if (clans != null)
                    {
                        ServerMgr.Instance.StartCoroutine(ApplyClanUpdates(clans));
                    }
                }
            }, this, RequestMethod.GET);
        }

        private IEnumerator ApplyClanUpdates(List<ClanData> newClans)
        {
            foreach (var clan in newClans)
            {
                var previousClan = Clans?.FirstOrDefault(c => c.tag == clan.tag);
                bool hasChanged = previousClan == null || previousClan.update_at != clan.update_at;

                if (hasChanged)
                {
                    List<ulong> usersToDeauthorize = new List<ulong>();

                    if (previousClan != null)
                    {
                        usersToDeauthorize = previousClan.users
                            .Where(u => !clan.users.Any(nu => nu.SteamId == u.SteamId))
                            .Select(u => u.SteamId)
                            .ToList();

                        int index = Clans.IndexOf(previousClan);
                        Clans[index] = clan;
                    }
                    else
                    {
                        Clans.Add(clan);
                    }

                    foreach (var steamId in clan.users)
                    {
                        SetCustomPrefix(steamId.SteamId, clan.tag, clan.color_tag);
                        _playersWithoutClan.Remove(steamId.SteamId);

                        if (_playerEntities.TryGetValue(steamId.SteamId, out var cache))
                        {
                            UpdateCupboardAuthorization(cache.cupboards, steamId.SteamId);
                            UpdateCodeLockAuthorization(cache.codeLocks, steamId.SteamId);
                            UpdateTurretAuthorization(cache.turrets, steamId.SteamId);

                            foreach (var userId in usersToDeauthorize)
                            {
                                RemoveFromCupboards(cache.cupboards, userId);
                                RemoveFromCodeLocks(cache.codeLocks, userId);
                                RemoveFromTurrets(cache.turrets, userId);
                            }
                        }

                        yield return new WaitForSeconds(0.001f);
                    }
                }

                foreach (var player in BasePlayer.activePlayerList)
                {
                    if (!IsInAnyClan(player.userID) && !_playersWithoutClan.Contains(player.userID))
                    {
                        _playersWithoutClan.Add(player.userID);
                        RemoveCustomPrefix(player.userID);
                    }
                }

                yield return new WaitForSeconds(0.001f);
            }

        }

        /// <summary>
        /// IQChat console command "set" uses BasePlayer.FindByID; offline players always fail with a misleading SteamID/syntax error.
        /// </summary>
        private void SetCustomPrefix(ulong steamId, string tag, string color)
        {
            if (steamId == 0UL)
                return;
            if (BasePlayer.FindByID(steamId) == null)
                return;

            tag = tag ?? string.Empty;
            if (string.IsNullOrWhiteSpace(color))
                color = "#FFFFFF";
            else if (!color.StartsWith("#", StringComparison.Ordinal))
                color = "#" + color;

            var custom = $"<color={color}>[{tag}]</color>";
            var chatCmd = $"set {steamId} custom {QuoteConsoleArg(custom)}";
            rust.RunServerCommand(chatCmd);
        }

        private void RemoveCustomPrefix(ulong steamId)
        {
            if (steamId == 0UL)
                return;
            if (BasePlayer.FindByID(steamId) == null)
                return;

            var chatCmd = $"set {steamId} custom \"\"";
            rust.RunServerCommand(chatCmd);
        }

        private static string QuoteConsoleArg(string value)
        {
            if (string.IsNullOrEmpty(value))
                return "\"\"";
            var escaped = value.Replace("\\", "\\\\").Replace("\"", "\\\"");
            return $"\"{escaped}\"";
        }

        private void SyncIqChatClanPrefixForPlayer(ulong steamId)
        {
            if (steamId == 0UL)
                return;
            if (BasePlayer.FindByID(steamId) == null)
                return;

            var clan = Clans?.FirstOrDefault(c => c.users.Any(u => u.SteamId == steamId));
            if (clan != null)
            {
                SetCustomPrefix(steamId, clan.tag, clan.color_tag);
                return;
            }

            if (Clans == null || Clans.Count == 0)
                return;

            RemoveCustomPrefix(steamId);
        }

        private bool IsInAnyClan(ulong steamId)
        {
            return Clans.Any(clan => clan.users.Any(user => user.SteamId == steamId));
        }

        #endregion

        #region Hooks

        private void Init()
        {
            LoadConfig();
            Unsubscribe(nameof(OnEntitySpawned));
        }

        private void OnServerInitialized()
        {
            StartUpdateLoop();
            Subscribe(nameof(OnEntitySpawned));
            foreach (var entity in BaseNetworkable.serverEntities)
            {
                var cupboard = entity as BuildingPrivlidge;
                if (cupboard != null)
                {
                    RegisterCupboard(cupboard);
                    continue;
                }

                var codeLock = entity as CodeLock;
                if (codeLock != null)
                {
                    RegisterCodeLock(codeLock);
                    continue;
                }

                var turret = entity as AutoTurret;
                if (turret != null)
                {
                    RegisterTurret(turret);
                    continue;
                }
            }
        }

        private void OnPlayerConnected(BasePlayer player)
        {
            if (player == null)
                return;
            var id = player.userID;
            if (id == 0UL)
                return;
            NextTick(() => SyncIqChatClanPrefixForPlayer(id));
        }

        private void OnEntitySpawned(BuildingPrivlidge cupboard) => RegisterCupboard(cupboard);
        private void OnEntitySpawned(CodeLock codeLock) => RegisterCodeLock(codeLock);
        private void OnEntitySpawned(AutoTurret turret) => RegisterTurret(turret);

        private void OnEntityKill(BuildingPrivlidge cupboard) => UnregisterCupboard(cupboard);
        private void OnEntityKill(CodeLock codeLock) => UnregisterCodeLock(codeLock);
        private void OnEntityKill(AutoTurret turret) => UnregisterTurret(turret);

        private object OnSamSiteTarget(SamSite samSite, BaseCombatEntity target)
        {

            if (samSite.staticRespawn)
                return null;

            var cupboard = samSite.GetBuildingPrivilege(samSite.WorldSpaceBounds());
            if ((object)cupboard == null)
                return null;

            var mountPoints = (target as BaseVehicle)?.mountPoints;
            if (mountPoints != null)
            {
                foreach (var mountPoint in mountPoints)
                {
                    var player = mountPoint.mountable.GetMounted();
                    if ((object)player != null && IsAuthed(cupboard, player.userID))
                        return true;
                }
            }

            foreach (var child in target.children)
            {
                var player = child as BasePlayer;
                if ((object)player != null)
                {
                    if (IsAuthed(cupboard, player.userID))
                        return true;
                }
            }

            return null;
        }

        private bool IsAuthed(BuildingPrivlidge cupboard, ulong userId)
        {
            if (userId == 0UL)
                return false;

            var clan = Clans.FirstOrDefault(c => c.users.Any(u => u.SteamId == userId));
            if (clan == null)
            {
                foreach (var authId in cupboard.authorizedPlayers)
                {
                    if (authId == userId)
                        return true;
                }
                return false;
            }
            bool auth = false;
            foreach(var entry in clan.users)
            {
                if (cupboard.authorizedPlayers.Any(u => u == entry.SteamId))
                    auth = true;
            }

            if(auth == false)
                return false;
            foreach (var entry in clan.users)
            {
                if (entry.SteamId == userId && entry.defense)
                    return true;
            }

            return false;
        }


        private void Unload()
        {
            Unsubscribe(nameof(OnEntitySpawned));
            StopUpdateLoop();
        }

        #endregion



        #region Methods

        private void RegisterCupboard(BuildingPrivlidge cupboard)
        {
            var cache = GetOrCreateCache(cupboard.OwnerID);
            cache.cupboards.Add(cupboard);
            UpdateCupboardAuthorization(cache.cupboards, cupboard.OwnerID);
        }

        private void RegisterCodeLock(CodeLock codeLock)
        {
            var cache = GetOrCreateCache(codeLock.OwnerID);
            cache.codeLocks.Add(codeLock);
            UpdateCodeLockAuthorization(cache.codeLocks, codeLock.OwnerID);
        }

        private void RegisterTurret(AutoTurret turret)
        {
            var cache = GetOrCreateCache(turret.OwnerID);
            cache.turrets.Add(turret);
            UpdateTurretAuthorization(cache.turrets, turret.OwnerID);
        }

        private void UnregisterCupboard(BuildingPrivlidge cupboard)
        {
            if (_playerEntities.TryGetValue(cupboard.OwnerID, out var cache))
                cache.cupboards.Remove(cupboard);
        }

        private void UnregisterCodeLock(CodeLock codeLock)
        {
            if (_playerEntities.TryGetValue(codeLock.OwnerID, out var cache))
                cache.codeLocks.Remove(codeLock);
        }

        private void UnregisterTurret(AutoTurret turret)
        {
            if (_playerEntities.TryGetValue(turret.OwnerID, out var cache))
                cache.turrets.Remove(turret);
        }

        private EntityCache GetOrCreateCache(ulong playerId)
        {
            if (!_playerEntities.TryGetValue(playerId, out var cache))
            {
                cache = new EntityCache();
                _playerEntities[playerId] = cache;
            }
            return cache;
        }



        #region Authorization Logic
        private bool IsClanMember(ulong clanOwnerId, ulong targetId)
        {
            var clan = Clans.FirstOrDefault(c => c.users.Any(u => u.SteamId == clanOwnerId));
            return clan != null && clan.users.Any(u => u.SteamId == targetId);
        }

        private void UpdateCupboardAuthorization(IEnumerable<BuildingPrivlidge> cupboards, ulong ownerId)
        {
            var clan = Clans.FirstOrDefault(c => c.users.Any(u => u.SteamId == ownerId));
            if (clan == null) return;

            var clanUsersById = clan.users.ToDictionary(u => u.SteamId, u => u);

            foreach (var cupboard in cupboards)
            {
                var authorizedPlayers = cupboard.authorizedPlayers;

                var authorizedIds = new HashSet<ulong>(authorizedPlayers);
                var clanAuthorizedIds = new HashSet<ulong>(
                    clan.users
                        .Where(u => u.SteamId != ownerId && u.cupboard_auth)
                        .Select(u => u.SteamId)
                );

                var toRemove = authorizedPlayers
                    .Where(id => id != ownerId &&
                                 clanUsersById.TryGetValue(id, out var user) && !user.cupboard_auth)
                    .ToList();

                var toAdd = clanAuthorizedIds
                    .Where(id => !authorizedIds.Contains(id))
                    .ToList();

                if (toRemove.Count == 0 && toAdd.Count == 0)
                    continue;

                foreach (var id in toRemove)
                    authorizedPlayers.Remove(id);

                foreach (var id in toAdd)
                    authorizedPlayers.Add(id);

                cupboard.SendNetworkUpdate();
            }
        }


        private void UpdateCodeLockAuthorization(IEnumerable<CodeLock> codeLocks, ulong ownerId)
        {
            var clan = Clans.FirstOrDefault(c => c.users.Any(u => u.SteamId == ownerId));
            if (clan == null) return;

            var clanUsersById = clan.users.ToDictionary(u => u.SteamId, u => u);
            var clanUserIdsWithLock = new HashSet<ulong>(
                clan.users.Where(u => u.Lock).Select(u => u.SteamId));

            foreach (var codeLock in codeLocks)
            {
                if (_config.CodeAuthMode == "whitelistPlayers")
                {
                    var authorizedPlayers = codeLock.whitelistPlayers;

                    var authorizedIds = new HashSet<ulong>(authorizedPlayers);

                    var clanAuthorizedIds = new HashSet<ulong>(
                        clan.users
                            .Where(u => u.SteamId != ownerId && u.Lock)
                            .Select(u => u.SteamId)
                    );

                    var toRemove = authorizedPlayers
                        .Where(ap => ap != ownerId &&
                                 clanUsersById.TryGetValue(ap, out var user) && !user.Lock)
                        .ToList();

                    var toAdd = clanAuthorizedIds
                        .Where(id => !authorizedIds.Contains(id))
                        .Select(id => id)
                        .ToList();

                    if (toRemove.Count == 0 && toAdd.Count == 0)
                        continue;

                    foreach (var id in toRemove)
                        authorizedPlayers.Remove(id);

                    foreach (var id in toAdd)
                        authorizedPlayers.Add(id);

                    codeLock.SendNetworkUpdate();
                }
                else
                {
                    var authorizedPlayers = codeLock.guestPlayers;
                    var authorizedIds = new HashSet<ulong>(authorizedPlayers);
                    var whiteList = new HashSet<ulong>(codeLock.whitelistPlayers);

                    var clanAuthorizedIds = new HashSet<ulong>(
                        clan.users
                            .Where(u => u.SteamId != ownerId && u.Lock)
                            .Select(u => u.SteamId)
                    );

                    var toRemove = authorizedPlayers
                        .Where(ap => ap != ownerId &&
                                 clanUsersById.TryGetValue(ap, out var user) && !user.Lock)
                        .ToList();

                    var toAdd = clanAuthorizedIds
                        .Where(id => !authorizedIds.Contains(id) && !whiteList.Contains(id))
                        .Select(id => id)
                        .ToList();

                    if (toRemove.Count == 0 && toAdd.Count == 0)
                        continue;

                    foreach (var id in toRemove)
                        authorizedPlayers.Remove(id);

                    foreach (var id in toAdd)
                        authorizedPlayers.Add(id);

                    codeLock.SendNetworkUpdate();
                }
            }
        }

        private void UpdateTurretAuthorization(IEnumerable<AutoTurret> AutoTurrets, ulong ownerId)
        {
            var clan = Clans.FirstOrDefault(c => c.users.Any(u => u.SteamId == ownerId));
            if (clan == null)
                return;

            var clanUsersById = clan.users.ToDictionary(u => u.SteamId, u => u);

            foreach (var turret in AutoTurrets)
            {
                if (turret == null || turret.IsDestroyed)
                {
                    continue;
                }

                var authorizedPlayers = turret.authorizedPlayers;

                var authorizedIds = new HashSet<ulong>(authorizedPlayers);

                var clanAuthorizedIds = new HashSet<ulong>(
                    clan.users
                        .Where(u => u.SteamId != ownerId && u.turrets)
                        .Select(u => u.SteamId)
                );

                var toRemove = authorizedPlayers
                    .Where(id => id != ownerId &&
                                 clanUsersById.TryGetValue(id, out var user) && !user.turrets)
                    .ToList();

                var toAdd = clanAuthorizedIds
                    .Where(id => !authorizedIds.Contains(id))
                    .ToList();

                if (toRemove.Count == 0 && toAdd.Count == 0)
                    continue;

                var isOnline = turret.IsOnline();

                if (isOnline)
                    turret.SetIsOnline(false);

                foreach (var id in toRemove)
                    authorizedPlayers.Remove(id);

                foreach (var id in toAdd)
                    authorizedPlayers.Add(id);

                if (isOnline)
                    turret.SetIsOnline(true);

                turret.UpdateMaxAuthCapacity();
                turret.SendNetworkUpdate();
            }
        }

        private void RemoveFromCupboards(IEnumerable<BuildingPrivlidge> cupboards, ulong userId)
        {
            foreach (var cupboard in cupboards)
            {
                if (!cupboard.authorizedPlayers.Contains(userId))
                    continue;
                cupboard.authorizedPlayers.Remove(userId);
                cupboard.SendNetworkUpdate();
            }
        }

        private void RemoveFromCodeLocks(IEnumerable<CodeLock> codeLocks, ulong userId)
        {
            foreach (var codeLock in codeLocks)
            {
                if (_config.CodeAuthMode == "whitelistPlayers")
                {
                    if (codeLock.whitelistPlayers.Contains(userId))
                    {

                        codeLock.whitelistPlayers.Remove(userId);
                        codeLock.guestPlayers.Remove(userId);
                        codeLock.SendNetworkUpdate();
                    }
                }
                else
                {
                    if (codeLock.guestPlayers.Contains(userId))
                    {
                        codeLock.whitelistPlayers.Remove(userId);
                        codeLock.guestPlayers.Remove(userId);
                        codeLock.SendNetworkUpdate();
                    }
                }
            }
        }

        private void RemoveFromTurrets(IEnumerable<AutoTurret> turrets, ulong userId)
        {
            foreach (var turret in turrets)
            {
                if (!turret.authorizedPlayers.Contains(userId))
                    continue;
                turret.authorizedPlayers.Remove(userId);
                turret.UpdateMaxAuthCapacity();
                turret.SendNetworkUpdate();
            }
        }

        private object CanUseLockedEntity(BasePlayer player, BaseLock baseLock)
        {
            var ownerEntity = baseLock.GetParentEntity();
            ulong ownerId = baseLock.OwnerID.IsSteamId() ? baseLock.OwnerID : ownerEntity?.OwnerID ?? 0;

            if (!ownerId.IsSteamId()) return null;

            var clan = Clans.FirstOrDefault(c => c.users.Any(u => u.SteamId == ownerId));
            if (clan == null) return null;

            if (IsClanMember(ownerId, player.userID)) return true;

            return null;
        }

        #endregion

        private void OnUpdateLoop()
        {
            FetchClansWebRequest();
        }

        private void StopUpdateLoop()
        {
            var mgr = ServerMgr.Instance;
            if (mgr == null) return;
            mgr.CancelInvoke(OnUpdateLoop);
        }

        private void StartUpdateLoop()
        {
            StopUpdateLoop();
            var mgr = ServerMgr.Instance;
            if (mgr == null) return;
            mgr.InvokeRepeating(OnUpdateLoop, 0f, _config.UpdateIntervalSeconds);
        }
        #endregion
    }
}
