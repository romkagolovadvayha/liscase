using System;
using System.Collections.Generic;
using System.Globalization;
using System.Linq;
using Newtonsoft.Json;
using Oxide.Core;
using Oxide.Core.Libraries;
using Oxide.Core.Plugins;
using Oxide.Game.Rust.Cui;
using UnityEngine;

namespace Oxide.Plugins
{
    [Info("ProstojCashRace", "Prostoj Team", "1.0.14")]
    [Description("Cash Race tournament module for ProstojMenu")]
    public class ProstojCashRace : RustPlugin
    {
        [PluginReference] private Plugin ProstojMenu;

        private const string UiRoot = "ProstojCashRace.Terminal";
        private const string PreviewSteamId = "76561198394504608";
        private const string DataFileName = "ProstojCashRace/BoundKeys";
        private Configuration config;
        private StoredData storedData;
        private StatusData status;
        private BaseEntity terminalEntity;
        private string terminalUuid;
        private string lastMonumentKey;
        private Timer pollTimer;
        private Timer terminalTimer;
        private bool statusRequestRunning;
        private bool firstActivePoll = true;
        private bool menuTabRegistered;
        private bool assetsRequested;
        private readonly Dictionary<ulong, StatusData> playerStatus = new Dictionary<ulong, StatusData>();
        private readonly Dictionary<ulong, float> playerStatusFetchedAt = new Dictionary<ulong, float>();
        private readonly HashSet<ulong> playerRequests = new HashSet<ulong>();

        private class Configuration
        {
            [JsonProperty("API URL (without trailing slash)")] public string ApiUrl = "https://api.prostoj.store/v1/cash-race";
            [JsonProperty("Server tag")] public string ServerTag = "classic14x2";
            [JsonProperty("Private preview Steam ID")] public string PrivateSteamId = PreviewSteamId;
            [JsonProperty("Status poll seconds")] public float PollSeconds = 30f;
            [JsonProperty("Key item shortname override (empty = API)")] public string KeyItemShortnameOverride = "door.key";
            [JsonProperty("Reset previously tracked keys once")] public bool ResetTrackedKeysOnce;
            [JsonProperty("Terminal cooldown override seconds (0 = API)")] public int TerminalCooldownOverrideSeconds = 1200;
            [JsonProperty("Website assets URL")] public string AssetsUrl = "https://prostoj.store/images/cash-race";
            [JsonProperty("Chat prefix")] public string ChatPrefix = "<color=#72b883>[ДЕНЕЖНАЯ ГОНКА]</color>";
            [JsonProperty("Debug logging")] public bool Debug;
        }

        private class StoredData
        {
            [JsonProperty("bound_keys")] public Dictionary<ulong, BoundKey> BoundKeys = new Dictionary<ulong, BoundKey>();
            [JsonProperty("pending_lost")] public List<PendingLost> PendingLost = new List<PendingLost>();
        }

        private class BoundKey
        {
            [JsonProperty("token")] public string Token;
            [JsonProperty("owner_id")] public ulong OwnerId;
            [JsonProperty("tournament_id")] public int TournamentId;
        }

        private class PendingLost
        {
            [JsonProperty("steam_id")] public string SteamId;
            [JsonProperty("tokens")] public List<string> Tokens = new List<string>();
        }

        private class ApiEnvelope<T>
        {
            [JsonProperty("success")] public bool Success;
            [JsonProperty("data")] public T Data;
            [JsonProperty("error")] public ApiError Error;
        }

        private class ApiError
        {
            [JsonProperty("message")] public string Message;
            [JsonProperty("code")] public string Code;
        }

        private class StatusData
        {
            [JsonProperty("available")] public bool Available;
            [JsonProperty("eligible")] public bool Eligible;
            [JsonProperty("id")] public int Id;
            [JsonProperty("title")] public string Title;
            [JsonProperty("description")] public string Description;
            [JsonProperty("phase")] public string Phase;
            [JsonProperty("server_now_unix")] public long ServerNowUnix;
            [JsonProperty("starts_at_unix")] public long StartsAtUnix;
            [JsonProperty("ends_at_unix")] public long EndsAtUnix;
            [JsonProperty("prize_pool_label")] public string PrizePoolLabel;
            [JsonProperty("preview_only")] public bool PreviewOnly = true;
            [JsonProperty("mechanics")] public MechanicsData Mechanics;
            [JsonProperty("terminal")] public TerminalData Terminal;
            [JsonProperty("player")] public PlayerData Player;
            [JsonProperty("leaderboard")] public List<LeaderboardData> Leaderboard = new List<LeaderboardData>();
            [JsonProperty("rewards")] public List<RewardData> Rewards = new List<RewardData>();
        }

        private class MechanicsData
        {
            [JsonProperty("drop_chance")] public float DropChance = 0.12f;
            [JsonProperty("drop_min")] public int DropMin = 1;
            [JsonProperty("drop_max")] public int DropMax = 2;
            [JsonProperty("key_shortname")] public string KeyShortname = "keycard_green";
            [JsonProperty("key_skin_id")] public ulong KeySkinId;
            [JsonProperty("terminal_active_seconds")] public int TerminalActiveSeconds = 900;
            [JsonProperty("terminal_cooldown_min_seconds")] public int TerminalCooldownMinSeconds = 1200;
            [JsonProperty("terminal_cooldown_max_seconds")] public int TerminalCooldownMaxSeconds = 2400;
            [JsonProperty("terminal_prefab")] public string TerminalPrefab;
            [JsonProperty("allowed_monuments")] public List<string> AllowedMonuments = new List<string>();
        }

        private class TerminalData
        {
            [JsonProperty("active")] public bool Active;
            [JsonProperty("session_uuid")] public string SessionUuid;
            [JsonProperty("monument_name")] public string MonumentName;
            [JsonProperty("expires_at")] public string ExpiresAt;
        }

        private class PlayerData
        {
            [JsonProperty("username")] public string Username;
            [JsonProperty("avatar")] public string Avatar;
            [JsonProperty("keys_found")] public int KeysFound;
            [JsonProperty("keys_lost")] public int KeysLost;
            [JsonProperty("keys_deposited")] public int KeysDeposited;
            [JsonProperty("position")] public int? Position;
        }

        private class LeaderboardData
        {
            [JsonProperty("position")] public int Position;
            [JsonProperty("steam_id")] public string SteamId;
            [JsonProperty("username")] public string Username;
            [JsonProperty("avatar")] public string Avatar;
            [JsonProperty("keys_deposited")] public int KeysDeposited;
        }

        private class RewardData
        {
            [JsonProperty("place")] public int Place;
            [JsonProperty("title")] public string Title;
            [JsonProperty("subtitle")] public string Subtitle;
        }

        protected override void LoadDefaultConfig()
        {
            config = new Configuration();
            SaveConfig();
        }

        protected override void LoadConfig()
        {
            base.LoadConfig();
            try { config = Config.ReadObject<Configuration>() ?? new Configuration(); }
            catch { PrintWarning("Config was invalid; default values were restored."); config = new Configuration(); }
            config.ApiUrl = (config.ApiUrl ?? string.Empty).TrimEnd('/');
            config.AssetsUrl = (config.AssetsUrl ?? string.Empty).TrimEnd('/');
            SaveConfig();
        }

        protected override void SaveConfig() => Config.WriteObject(config, true);

        private void Init()
        {
            storedData = Interface.Oxide.DataFileSystem.ReadObject<StoredData>(DataFileName) ?? new StoredData();
        }

        private void OnServerInitialized()
        {
            ResetTrackedKeysIfRequested();
            PollStatus();
            pollTimer = timer.Every(Mathf.Clamp(config.PollSeconds, 15f, 120f), PollStatus);
            timer.Every(60f, FlushPendingLost);
        }

        private void ResetTrackedKeysIfRequested()
        {
            if (!config.ResetTrackedKeysOnce) return;
            foreach (var ownerGroup in storedData.BoundKeys.Values
                .Where(key => key != null && !string.IsNullOrWhiteSpace(key.Token))
                .GroupBy(key => key.OwnerId))
            {
                QueueLost(ownerGroup.Key.ToString(), ownerGroup.Select(key => key.Token).Distinct().ToList());
            }
            storedData.BoundKeys.Clear();
            config.ResetTrackedKeysOnce = false;
            SaveData();
            SaveConfig();
        }

        private void Unload()
        {
            pollTimer?.Destroy();
            terminalTimer?.Destroy();
            foreach (var player in BasePlayer.activePlayerList) CuiHelper.DestroyUi(player, UiRoot);
            if (terminalEntity != null && !terminalEntity.IsDestroyed) terminalEntity.Kill();
            if (!string.IsNullOrEmpty(terminalUuid)) CloseTerminalApi(false);
            UnregisterMenuTab();
            SaveData();
        }

        private void OnPluginLoaded(Plugin plugin)
        {
            if (plugin != null && plugin.Name == "ProstojMenu")
            {
                ProstojMenu = plugin;
                RegisterMenuTab();
                if (status != null && status.Available) CacheImagesOnce();
            }
        }

        private void OnPluginUnloaded(Plugin plugin)
        {
            if (plugin != null && plugin.Name == "ProstojMenu")
            {
                ProstojMenu = null;
                menuTabRegistered = false;
            }
        }

        private void RegisterMenuTab()
        {
            if (menuTabRegistered || ProstojMenu == null || status == null || !status.Available) return;
            ProstojMenu.Call("API_RegisterTab", this, "tournament", "ТУРНИР", "KEY", 25);
            menuTabRegistered = true;
        }

        private void UnregisterMenuTab()
        {
            if (!menuTabRegistered) return;
            ProstojMenu?.Call("API_UnregisterTab", this, "tournament");
            menuTabRegistered = false;
        }

        private object ProstojMenu_CanView(BasePlayer player) => status != null && status.Available && IsPreviewPlayer(player);

        private object ProstojMenu_Render(BasePlayer player, string parent, int page)
        {
            if (player == null || status == null || !status.Available || !IsPreviewPlayer(player)) return false;
            StatusData snapshot;
            var hasPlayerSnapshot = playerStatus.TryGetValue(player.userID, out snapshot) && snapshot != null;
            var isFresh = hasPlayerSnapshot && playerStatusFetchedAt.ContainsKey(player.userID)
                && UnityEngine.Time.realtimeSinceStartup - playerStatusFetchedAt[player.userID] < 15f;
            if (!hasPlayerSnapshot) snapshot = status;
            DrawMenu(player, parent, snapshot);
            if (!isFresh) RequestPlayerStatus(player, true);
            return true;
        }

        private bool IsPreviewPlayer(BasePlayer player)
        {
            return player != null;
        }

        private bool IsActive => status != null && status.Available && string.Equals(status.Phase, "active", StringComparison.OrdinalIgnoreCase);

        private void PollStatus()
        {
            if (statusRequestRunning || string.IsNullOrWhiteSpace(config.ApiUrl) || string.IsNullOrWhiteSpace(config.ServerTag)) return;
            statusRequestRunning = true;
            var url = config.ApiUrl + "/plugin/status?compact=1&server_tag=" + Uri.EscapeDataString(config.ServerTag);
            webrequest.Enqueue(url, null, (code, response) =>
            {
                statusRequestRunning = false;
                ApiEnvelope<StatusData> envelope;
                if (!TryEnvelope(code, response, out envelope)) return;
                var wasActive = IsActive;
                status = envelope.Data;
                if (status != null && status.Mechanics == null) status.Mechanics = new MechanicsData();
                if (status != null && status.Available)
                {
                    RegisterMenuTab();
                    CacheImagesOnce();
                }
                else
                {
                    playerStatus.Clear();
                    playerStatusFetchedAt.Clear();
                    UnregisterMenuTab();
                }
                if (config.Debug && status != null)
                    Puts("Status: phase=" + status.Phase + ", terminalActive=" + status.Mechanics.TerminalActiveSeconds
                        + "s, cooldown=" + status.Mechanics.TerminalCooldownMinSeconds + "-" + status.Mechanics.TerminalCooldownMaxSeconds + "s");
                if (IsActive && (!wasActive || firstActivePoll))
                {
                    firstActivePoll = false;
                    ScheduleTerminal(10f);
                    FlushPendingLost();
                }
                else if (!IsActive)
                {
                    firstActivePoll = true;
                    StopTerminal(false);
                }
            }, this, RequestMethod.GET, Headers(), 12f);
        }

        private void CacheImagesOnce()
        {
            if (assetsRequested || ProstojMenu == null) return;
            assetsRequested = true;
            CacheImages();
        }

        private void RequestPlayerStatus(BasePlayer player, bool redraw)
        {
            if (player == null || !player.IsConnected || !IsPreviewPlayer(player) || !playerRequests.Add(player.userID)) return;
            var url = config.ApiUrl + "/plugin/status?server_tag=" + Uri.EscapeDataString(config.ServerTag)
                + "&steam_id=" + player.UserIDString + "&server_admin=" + (player.net.connection.authLevel > 0 ? "1" : "0");
            var userId = player.userID;
            webrequest.Enqueue(url, null, (code, response) =>
            {
                playerRequests.Remove(userId);
                ApiEnvelope<StatusData> envelope;
                if (!TryEnvelope(code, response, out envelope) || envelope.Data == null) return;
                playerStatus[userId] = envelope.Data;
                playerStatusFetchedAt[userId] = UnityEngine.Time.realtimeSinceStartup;
                if (redraw && player.IsConnected && IsMenuTabActive(player))
                    ProstojMenu?.Call("API_RefreshTab", player);
            }, this, RequestMethod.GET, Headers(), 12f);
        }

        private void OnEntityDeath(BaseCombatEntity entity, HitInfo info)
        {
            if (!IsActive || entity == null || info == null) return;
            var shortName = (entity.ShortPrefabName ?? string.Empty).ToLowerInvariant();
            if (!shortName.Contains("barrel") || shortName.Contains("water")) return;
            var player = info.InitiatorPlayer;
            if (player == null || !IsPreviewPlayer(player)) return;
            var mechanics = status.Mechanics ?? new MechanicsData();
            if (UnityEngine.Random.value > Mathf.Clamp01(mechanics.DropChance)) return;
            var min = Mathf.Max(1, mechanics.DropMin);
            var max = Mathf.Max(min, mechanics.DropMax);
            MintKeys(player, UnityEngine.Random.Range(min, max + 1), entity.transform.position);
        }

        private void MintKeys(BasePlayer player, int amount, Vector3 dropPosition)
        {
            var tokens = Enumerable.Range(0, Mathf.Clamp(amount, 1, 10)).Select(_ => Guid.NewGuid().ToString()).ToList();
            var body = JsonConvert.SerializeObject(new
            {
                server_tag = config.ServerTag,
                steam_id = player.UserIDString,
                server_admin = player.net.connection.authLevel > 0,
                tokens
            });
            Post("/plugin/mint", body, (code, response) =>
            {
                ApiEnvelope<Dictionary<string, object>> envelope;
                if (!TryEnvelope(code, response, out envelope))
                {
                    if (player.IsConnected) player.ChatMessage(config.ChatPrefix + " Не удалось зарегистрировать найденный ключ. Попробуйте следующую бочку.");
                    return;
                }
                var given = 0;
                foreach (var token in tokens)
                {
                    if (DropBoundKey(player, token, dropPosition)) given++;
                }
                SaveData();
                if (player.IsConnected && given > 0) RequestPlayerStatus(player, false);
            });
        }

        private bool DropBoundKey(BasePlayer player, string token, Vector3 dropPosition)
        {
            var mechanics = status?.Mechanics ?? new MechanicsData();
            var shortname = string.IsNullOrWhiteSpace(config.KeyItemShortnameOverride)
                ? (string.IsNullOrWhiteSpace(mechanics.KeyShortname) ? "door.key" : mechanics.KeyShortname)
                : config.KeyItemShortnameOverride.Trim();
            var skinId = string.IsNullOrWhiteSpace(config.KeyItemShortnameOverride) ? mechanics.KeySkinId : 0UL;
            var item = ItemManager.CreateByName(shortname, 1, skinId);
            if (item == null)
            {
                PrintError("Could not create key item '" + shortname + "'.");
                QueueLost(player.UserIDString, new List<string> { token });
                return false;
            }
            item.name = "Ключ денежной гонки";
            item.text = "Персональный ключ: принадлежит " + player.displayName;
            storedData.BoundKeys[item.uid.Value] = new BoundKey { Token = token, OwnerId = player.userID, TournamentId = status.Id };

            var offset = new Vector3(UnityEngine.Random.Range(-0.45f, 0.45f), 0.45f, UnityEngine.Random.Range(-0.45f, 0.45f));
            var dropped = item.Drop(dropPosition + offset, Vector3.up * 0.35f);
            if (dropped != null) return true;

            storedData.BoundKeys.Remove(item.uid.Value);
            item.Remove();
            QueueLost(player.UserIDString, new List<string> { token });
            if (player.IsConnected)
                player.ChatMessage(config.ChatPrefix + " Ключ зарегистрирован, но предмет не удалось создать. Попробуйте следующую бочку.");
            return false;
        }

        private object CanAcceptItem(ItemContainer container, Item item)
        {
            BoundKey bound;
            if (item == null || !storedData.BoundKeys.TryGetValue(item.uid.Value, out bound)) return null;
            var owner = container?.playerOwner;
            if (owner != null && owner.userID != bound.OwnerId) return ItemContainer.CanAcceptResult.CannotAccept;
            return null;
        }

        private object CanPickupItem(BasePlayer player, Item item)
        {
            BoundKey bound;
            if (player == null || item == null || !storedData.BoundKeys.TryGetValue(item.uid.Value, out bound)) return null;
            if (player.userID != bound.OwnerId)
            {
                player.ChatMessage(config.ChatPrefix + " Этот ключ привязан к другому игроку.");
                return false;
            }
            return null;
        }

        private object CanStackItem(Item item, Item targetItem)
        {
            if (item == null || targetItem == null) return null;
            return storedData.BoundKeys.ContainsKey(item.uid.Value) || storedData.BoundKeys.ContainsKey(targetItem.uid.Value) ? (object)false : null;
        }

        private void OnPlayerDeath(BasePlayer player, HitInfo info)
        {
            if (player == null) return;
            var entries = OwnedInventoryKeys(player);
            if (entries.Count == 0) return;
            foreach (var entry in entries)
            {
                storedData.BoundKeys.Remove(entry.Item1.uid.Value);
                entry.Item1.Remove();
            }
            var tokens = entries.Select(x => x.Item2.Token).ToList();
            QueueLost(player.UserIDString, tokens);
            SaveData();
            FlushPendingLost();
            player.ChatMessage(config.ChatPrefix + " После смерти потеряно ключей: " + tokens.Count + ". Они исчезли и не попадут в труп.");
        }

        private List<Tuple<Item, BoundKey>> OwnedInventoryKeys(BasePlayer player)
        {
            var result = new List<Tuple<Item, BoundKey>>();
            if (player?.inventory == null) return result;
            var containers = new[] { player.inventory.containerMain, player.inventory.containerBelt, player.inventory.containerWear };
            foreach (var item in containers.Where(container => container != null).SelectMany(container => container.itemList.ToArray()))
            {
                BoundKey bound;
                if (item != null && storedData.BoundKeys.TryGetValue(item.uid.Value, out bound) && bound.OwnerId == player.userID)
                    result.Add(Tuple.Create(item, bound));
            }
            return result;
        }

        private void QueueLost(string steamId, List<string> tokens)
        {
            if (tokens == null || tokens.Count == 0) return;
            storedData.PendingLost.Add(new PendingLost { SteamId = steamId, Tokens = tokens.Distinct().ToList() });
            SaveData();
        }

        private void FlushPendingLost()
        {
            if (storedData.PendingLost.Count == 0 || status == null) return;
            var pending = storedData.PendingLost[0];
            var body = JsonConvert.SerializeObject(new { server_tag = config.ServerTag, steam_id = pending.SteamId, server_admin = true, tokens = pending.Tokens });
            Post("/plugin/lost", body, (code, response) =>
            {
                ApiEnvelope<Dictionary<string, object>> envelope;
                if (!TryEnvelope(code, response, out envelope)) return;
                storedData.PendingLost.Remove(pending);
                SaveData();
                if (storedData.PendingLost.Count > 0) timer.Once(0.25f, FlushPendingLost);
            });
        }

        private void ScheduleTerminal(float? firstDelay = null)
        {
            terminalTimer?.Destroy();
            if (!IsActive || terminalEntity != null) return;
            if (status.PreviewOnly && !BasePlayer.activePlayerList.Any(IsPreviewPlayer))
            {
                terminalTimer = timer.Once(60f, () => ScheduleTerminal(5f));
                return;
            }
            var mechanics = status.Mechanics ?? new MechanicsData();
            var delay = firstDelay ?? (config.TerminalCooldownOverrideSeconds > 0
                ? Mathf.Max(60, config.TerminalCooldownOverrideSeconds)
                : UnityEngine.Random.Range(
                    Mathf.Max(60, mechanics.TerminalCooldownMinSeconds),
                    Mathf.Max(61, mechanics.TerminalCooldownMaxSeconds + 1)));
            terminalTimer = timer.Once(Mathf.Max(5f, delay), SpawnTerminal);
        }

        private void SpawnTerminal()
        {
            terminalTimer = null;
            if (!IsActive || terminalEntity != null) return;
            MonumentInfo monument;
            Vector3 position;
            if (!TryTerminalPosition(out monument, out position))
            {
                PrintWarning("No safe monument position found; retrying in 60 seconds.");
                ScheduleTerminal(60f);
                return;
            }
            var prefab = status.Mechanics?.TerminalPrefab;
            if (string.IsNullOrWhiteSpace(prefab)) prefab = "assets/prefabs/deployable/vendingmachine/vendingmachine.deployed.prefab";
            var direction = (TerrainMeta.Center - position); direction.y = 0f;
            var entity = GameManager.server.CreateEntity(prefab, position, direction.sqrMagnitude > 1f ? Quaternion.LookRotation(direction.normalized) : Quaternion.identity);
            if (entity == null)
            {
                ScheduleTerminal(60f);
                return;
            }
            entity.enableSaving = false;
            entity.OwnerID = 0;
            entity.Spawn();
            terminalEntity = entity;
            terminalUuid = Guid.NewGuid().ToString();
            var grid = MapHelper.PositionToString(position);
            var locationLabel = "Квадрат " + grid;
            var vending = entity as VendingMachine;
            if (vending != null)
            {
                SetTerminalName(vending, grid);
                NextTick(() =>
                {
                    if (vending != null && !vending.IsDestroyed) SetTerminalName(vending, grid);
                });
            }
            var duration = Mathf.Max(60, status.Mechanics?.TerminalActiveSeconds ?? 900);
            var body = JsonConvert.SerializeObject(new
            {
                server_tag = config.ServerTag,
                session_uuid = terminalUuid,
                monument_key = monument.name,
                monument_name = locationLabel,
                position = string.Format(CultureInfo.InvariantCulture, "{0:0.0},{1:0.0},{2:0.0}", position.x, position.y, position.z)
            });
            Post("/plugin/terminal/open", body, (code, response) =>
            {
                ApiEnvelope<Dictionary<string, object>> envelope;
                if (!TryEnvelope(code, response, out envelope))
                {
                    StopTerminal(true);
                    ScheduleTerminal(60f);
                    return;
                }
                var activeMinutes = Mathf.CeilToInt(duration / 60f);
                var announcement = config.ChatPrefix + " Терминал появился в квадрате <color=#f0d4a5>" + grid
                    + "</color>! Он будет активен " + activeMinutes + " "
                    + Word(activeMinutes, "минуту", "минуты", "минут") + ".";
                if (status.PreviewOnly)
                {
                    foreach (var previewPlayer in BasePlayer.activePlayerList.Where(IsPreviewPlayer)) previewPlayer.ChatMessage(announcement);
                }
                else PrintToChat(announcement);
                terminalTimer = timer.Once(duration, () => StopTerminal(false));
            });
        }

        private void SetTerminalName(VendingMachine vending, string grid)
        {
            vending.shopName = "Терминал денежной гонки • " + grid;
            vending.SendNetworkUpdateImmediate();
        }

        private bool TryTerminalPosition(out MonumentInfo chosen, out Vector3 position)
        {
            chosen = null;
            position = Vector3.zero;
            var allowed = status?.Mechanics?.AllowedMonuments ?? new List<string>();
            var candidates = TerrainMeta.Path.Monuments.Where(m => m != null && IsSafeMonument(m, allowed)).OrderBy(_ => UnityEngine.Random.value).ToList();
            if (candidates.Count > 1 && !string.IsNullOrEmpty(lastMonumentKey))
                candidates = candidates.Where(m => !string.Equals(m.name, lastMonumentKey, StringComparison.OrdinalIgnoreCase)).ToList();
            foreach (var monument in candidates)
            {
                for (var attempt = 0; attempt < 10; attempt++)
                {
                    var angle = UnityEngine.Random.Range(0f, 360f) * Mathf.Deg2Rad;
                    var radius = UnityEngine.Random.Range(18f, 42f);
                    var probe = monument.transform.position + new Vector3(Mathf.Cos(angle) * radius, 100f, Mathf.Sin(angle) * radius);
                    RaycastHit hit;
                    if (!Physics.Raycast(probe, Vector3.down, out hit, 220f, LayerMask.GetMask("Terrain", "World", "Construction"))) continue;
                    if (Vector3.Angle(hit.normal, Vector3.up) > 18f || WaterLevel.GetWaterDepth(hit.point, true, false, null) > 0.25f) continue;
                    if (Physics.CheckSphere(hit.point + Vector3.up, 1.2f, LayerMask.GetMask("Construction", "Deployed", "Vehicle_Large"))) continue;
                    chosen = monument;
                    position = hit.point + Vector3.up * 0.05f;
                    lastMonumentKey = monument.name;
                    return true;
                }
            }
            return false;
        }

        private bool IsSafeMonument(MonumentInfo monument, List<string> allowed)
        {
            var name = (monument.name ?? string.Empty).ToLowerInvariant();
            if (allowed.Count > 0 && !allowed.Any(value => name.Contains((value ?? string.Empty).ToLowerInvariant()))) return false;
            var blocked = new[] { "cave", "tunnel", "underwater", "oilrig", "oil_rig", "offshore", "fishing_village", "compound" };
            return !blocked.Any(name.Contains);
        }

        private string FriendlyMonumentName(MonumentInfo monument)
        {
            var key = (monument?.name ?? string.Empty).ToLowerInvariant();
            if (key.Contains("arctic_research_base")) return "Арктическая исследовательская база";
            if (key.Contains("desert_military_base")) return "Пустынная военная база";
            if (key.Contains("launch_site")) return "Космодром";
            if (key.Contains("military_tunnel")) return "Военные туннели";
            if (key.Contains("missile_silo")) return "Ракетная шахта";
            if (key.Contains("water_treatment_plant")) return "Водоочистная станция";
            if (key.Contains("powerplant")) return "Электростанция";
            if (key.Contains("trainyard")) return "Железнодорожное депо";
            if (key.Contains("airfield")) return "Аэродром";
            if (key.Contains("satellite_dish")) return "Спутниковые антенны";
            if (key.Contains("sewer_branch")) return "Канализационный отвод";
            if (key.Contains("sphere_tank") || key.Contains("dome")) return "Сфера";
            if (key.Contains("junkyard")) return "Свалка";
            if (key.Contains("harbor")) return "Порт";
            if (key.Contains("supermarket")) return "Супермаркет";
            if (key.Contains("gas_station")) return "Заправка";
            if (key.Contains("warehouse")) return "Заброшенный склад";
            if (key.Contains("lighthouse")) return "Маяк";
            if (key.Contains("mining_quarry")) return "Горнодобывающий карьер";
            if (key.Contains("ferry_terminal")) return "Паромный терминал";
            if (key.Contains("observatory")) return "Обсерватория";
            if (key.Contains("large_barn")) return "Большая конюшня";
            if (key.Contains("stables")) return "Конюшни";
            if (key.Contains("ranch")) return "Ранчо";
            if (key.Contains("power_sub")) return "Электрическая подстанция";
            if (key.Contains("water_well")) return "Колодец";
            if (key.Contains("radtown_small")) return "Заброшенный город";
            if (key.Contains("swamp")) return "Болото";
            var phrase = monument?.displayPhrase?.translated;
            if (!string.IsNullOrWhiteSpace(phrase)) return phrase;
            var raw = (monument?.name ?? "РТ").Split('/').Last().Replace('_', ' ').Replace('-', ' ');
            return CultureInfo.CurrentCulture.TextInfo.ToTitleCase(raw);
        }

        private void StopTerminal(bool destroyed)
        {
            terminalTimer?.Destroy();
            terminalTimer = null;
            if (terminalEntity != null && !terminalEntity.IsDestroyed) terminalEntity.Kill();
            terminalEntity = null;
            if (!string.IsNullOrEmpty(terminalUuid)) CloseTerminalApi(destroyed);
            terminalUuid = null;
            if (IsActive) ScheduleTerminal();
        }

        private void CloseTerminalApi(bool destroyed)
        {
            var uuid = terminalUuid;
            var body = JsonConvert.SerializeObject(new { server_tag = config.ServerTag, session_uuid = uuid, destroyed });
            Post("/plugin/terminal/close", body, (_, __) => { });
        }

        private object OnEntityTakeDamage(BaseCombatEntity entity, HitInfo info)
        {
            if (entity != null && terminalEntity != null && entity.net?.ID == terminalEntity.net?.ID)
            {
                info?.damageTypes?.ScaleAll(0f);
                return true;
            }
            return null;
        }

        private void OnLootEntity(BasePlayer player, BaseEntity entity)
        {
            if (player == null || entity == null || terminalEntity == null || entity.net?.ID != terminalEntity.net?.ID) return;
            NextTick(() =>
            {
                player.EndLooting();
                if (!IsPreviewPlayer(player)) return;
                DrawTerminal(player);
            });
        }

        private void OnPlayerConnected(BasePlayer player)
        {
            if (player == null || !IsPreviewPlayer(player)) return;
            RequestPlayerStatus(player, false);
            if (IsActive && terminalEntity == null) ScheduleTerminal(10f);
        }

        private void OnPlayerDisconnected(BasePlayer player, string reason)
        {
            if (player == null) return;
            playerStatus.Remove(player.userID);
            playerStatusFetchedAt.Remove(player.userID);
            playerRequests.Remove(player.userID);
            CuiHelper.DestroyUi(player, UiRoot);
            if (status != null && status.PreviewOnly && !BasePlayer.activePlayerList.Any(candidate => candidate != player && candidate.IsConnected && IsPreviewPlayer(candidate)))
                StopTerminal(false);
        }

        [ConsoleCommand("cashrace.ui")]
        private void CashRaceUi(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !IsPreviewPlayer(player) || arg.Args == null || arg.Args.Length == 0) return;
            switch ((arg.GetString(0) ?? string.Empty).ToLowerInvariant())
            {
                case "close": CuiHelper.DestroyUi(player, UiRoot); break;
                case "deposit": DepositAll(player); break;
                case "refresh": RequestPlayerStatus(player, true); break;
            }
        }

        private void DepositAll(BasePlayer player)
        {
            if (terminalEntity == null || Vector3.Distance(player.transform.position, terminalEntity.transform.position) > 5.5f)
            {
                player.ChatMessage(config.ChatPrefix + " Подойдите к активному терминалу.");
                CuiHelper.DestroyUi(player, UiRoot);
                return;
            }
            var entries = OwnedInventoryKeys(player);
            if (entries.Count == 0)
            {
                player.ChatMessage(config.ChatPrefix + " У вас нет персональных ключей.");
                return;
            }
            var depositUuid = Guid.NewGuid().ToString();
            var tokenUuids = entries.Select(x => x.Item2.Token).ToList();
            var body = JsonConvert.SerializeObject(new
            {
                server_tag = config.ServerTag,
                steam_id = player.UserIDString,
                server_admin = player.net.connection.authLevel > 0,
                deposit_uuid = depositUuid,
                terminal_uuid = terminalUuid,
                tokens = tokenUuids
            });
            Post("/plugin/deposit", body, (code, response) =>
            {
                ApiEnvelope<Dictionary<string, object>> envelope;
                if (!TryEnvelope(code, response, out envelope))
                {
                    if (player.IsConnected) player.ChatMessage(config.ChatPrefix + " Терминал не принял ключи. Они остались у вас — попробуйте ещё раз.");
                    return;
                }
                foreach (var entry in entries)
                {
                    storedData.BoundKeys.Remove(entry.Item1.uid.Value);
                    entry.Item1.Remove();
                }
                SaveData();
                if (player.IsConnected)
                {
                    CuiHelper.DestroyUi(player, UiRoot);
                    player.ChatMessage(config.ChatPrefix + " Зарегистрировано ключей: <color=#72b883>" + entries.Count + "</color>. Рейтинг обновлён.");
                    RequestPlayerStatus(player, false);
                }
            });
        }

        private void DrawTerminal(BasePlayer player)
        {
            CuiHelper.DestroyUi(player, UiRoot);
            var count = OwnedInventoryKeys(player).Count;
            var ui = new CuiElementContainer();
            ui.Add(new CuiPanel { Image = { Color = "0 0 0 0.76" }, RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1" }, CursorEnabled = true }, "Overlay", UiRoot);
            var panel = UiRoot + ".Panel";
            ui.Add(new CuiPanel { Image = { Color = "0.055 0.082 0.075 0.98" }, RectTransform = { AnchorMin = "0.34 0.28", AnchorMax = "0.66 0.72" } }, UiRoot, panel);
            AddLabel(ui, panel, "ДЕНЕЖНАЯ ГОНКА", "0.08 0.75", "0.92 0.92", 24, "0.9 0.95 0.93 1", TextAnchor.MiddleCenter, true);
            AddLabel(ui, panel, "АКТИВНЫЙ ТЕРМИНАЛ", "0.08 0.66", "0.92 0.77", 10, "0.45 0.78 0.62 1", TextAnchor.MiddleCenter, true);
            AddLabel(ui, panel, "ПЕРСОНАЛЬНЫХ КЛЮЧЕЙ", "0.08 0.50", "0.92 0.60", 10, "0.58 0.65 0.62 1", TextAnchor.MiddleCenter, true);
            AddLabel(ui, panel, count.ToString(CultureInfo.InvariantCulture), "0.08 0.34", "0.92 0.53", 44, "0.9 0.95 0.93 1", TextAnchor.MiddleCenter, true);
            AddButton(ui, panel, "0.08 0.12", "0.68 0.25", "cashrace.ui deposit", count > 0 ? "ЗАРЕГИСТРИРОВАТЬ ВСЕ" : "КЛЮЧЕЙ НЕТ", count > 0 ? "0.20 0.60 0.43 1" : "0.22 0.25 0.24 1");
            AddButton(ui, panel, "0.71 0.12", "0.92 0.25", "cashrace.ui close", "ЗАКРЫТЬ", "0.20 0.22 0.21 1");
            CuiHelper.AddUi(player, ui);
        }

        private void DrawMenu(BasePlayer player, string parent, StatusData snapshot)
        {
            var ui = new CuiElementContainer();
            var root = parent + ".CashRace";
            AddPanel(ui, parent, root, "0 0", "1 1", "0 0 0 0");
            if (snapshot == null || !snapshot.Available)
            {
                AddLabel(ui, root, "ДЕНЕЖНАЯ ГОНКА", "0.08 0.58", "0.92 0.72", 30, "0.92 0.91 0.88 1", TextAnchor.MiddleCenter, true);
                AddLabel(ui, root, "Проверяем расписание турнира…", "0.08 0.45", "0.92 0.58", 13, "0.62 0.61 0.59 1", TextAnchor.MiddleCenter, false);
                CuiHelper.AddUi(player, ui);
                return;
            }

            var theme = ProstojMenu?.Call("API_GetTheme") as Dictionary<string, string>;
            var success = ThemeToken(theme, "success", "0.43 0.77 0.57 1");
            var warning = ThemeToken(theme, "warning", "0.79 0.66 0.42 1");
            var danger = ThemeToken(theme, "danger", "0.77 0.40 0.36 1");
            var textMain = ThemeToken(theme, "text_main", "0.92 0.95 0.93 1");
            var textSecondary = ThemeToken(theme, "text_secondary", "0.60 0.63 0.61 1");
            var surface = ThemeToken(theme, "bg_secondary", "0.205 0.195 0.175 0.80");
            var raised = ThemeToken(theme, "bg_raised", "0.350 0.330 0.295 0.66");
            var gold = ThemeToken(theme, "gold", "0.88 0.67 0.39 1");
            var terminalActive = snapshot.Terminal != null && snapshot.Terminal.Active;
            var target = snapshot.Phase == "upcoming" ? snapshot.StartsAtUnix : snapshot.EndsAtUnix;
            var remaining = Math.Max(0, target - DateTimeOffset.UtcNow.ToUnixTimeSeconds());

            var header = root + ".Header";
            AddPanel(ui, root, header, "0 0.89", "1 1", surface);
            AddLabel(ui, header, Short(Safe(snapshot.Title), 34).ToUpperInvariant(), "0.025 0.45", "0.48 0.9", 18, textMain, TextAnchor.MiddleLeft, true);
            AddLabel(ui, header, "ПРИЗОВОЙ ФОНД  ·  " + Safe(snapshot.PrizePoolLabel), "0.025 0.10", "0.50 0.46", 9, textSecondary, TextAnchor.MiddleLeft, true);
            AddLabel(ui, header, snapshot.Phase == "upcoming" ? "ДО СТАРТА" : snapshot.Phase == "active" ? "ДО ФИНИША" : "ЗАВЕРШЁН", "0.57 0.18", "0.70 0.82", 8, textSecondary, TextAnchor.MiddleRight, true);
            AddLabel(ui, header, FormatDuration(remaining), "0.715 0.20", "0.83 0.86", 18, textMain, TextAnchor.MiddleCenter, true);
            AddPanel(ui, header, header + ".StatusDot", "0.85 0.42", "0.862 0.58", terminalActive ? success : warning);
            AddLabel(ui, header, terminalActive ? "ТЕРМИНАЛ ОТКРЫТ" : "ТЕРМИНАЛ СКРЫТ", "0.875 0.18", "0.985 0.82", 9,
                terminalActive ? success : textSecondary, TextAnchor.MiddleLeft, true);

            var guide = root + ".Guide";
            AddPanel(ui, root, guide, "0 0.255", "0.575 0.875", surface);
            AddLabel(ui, guide, "КАК УЧАСТВОВАТЬ", "0.04 0.90", "0.50 0.98", 12, textMain, TextAnchor.MiddleLeft, true);
            AddLabel(ui, guide, Wrap(Safe(snapshot.Description), 68, 2), "0.04 0.83", "0.96 0.90", 9, textSecondary, TextAnchor.UpperLeft, false);
            AddInstructionRow(ui, guide, guide + ".Step0", "0.04 0.69", "0.96 0.82", "nav-tournament.png",
                "ИЩИТЕ КЛЮЧИ", "Разбивайте бочки во время турнира.", warning, raised, textMain, textSecondary);
            AddInstructionRow(ui, guide, guide + ".Step1", "0.04 0.532", "0.96 0.662", "cashrace-keep-key.png",
                "НЕ ТЕРЯЙТЕ ИХ", "Ключи личные и исчезают после смерти.", warning, raised, textMain, textSecondary);
            AddInstructionRow(ui, guide, guide + ".Step2", "0.04 0.374", "0.96 0.504", "cashrace-wait-terminal.png",
                "ДОЖДИТЕСЬ ТЕРМИНАЛА", "Квадрат его появления придёт в чат.", terminalActive ? success : textSecondary, raised, textMain, textSecondary);
            AddInstructionRow(ui, guide, guide + ".Step3", "0.04 0.216", "0.96 0.346", "cashrace-deposit-key.png",
                "ЗАРЕГИСТРИРУЙТЕ КЛЮЧИ", "Побеждает игрок, сдавший больше всех.", success, raised, textMain, textSecondary);

            var ranking = root + ".Ranking";
            AddPanel(ui, root, ranking, "0.585 0.255", "1 0.875", surface);
            AddLabel(ui, ranking, "ТОП-5 ИГРОКОВ", "0.055 0.90", "0.58 0.98", 12, textMain, TextAnchor.MiddleLeft, true);
            AddLabel(ui, ranking, "КЛЮЧИ", "0.72 0.90", "0.94 0.98", 8, textSecondary, TextAnchor.MiddleRight, true);
            var leaders = snapshot.Leaderboard ?? new List<LeaderboardData>();
            var podiumColors = new[] { gold, textSecondary, warning };
            for (var i = 0; i < 5; i++)
            {
                var yMax = 0.82f - i * 0.158f;
                var yMin = yMax - 0.13f;
                var rowName = ranking + ".Row" + i;
                var podium = i < 3;
                AddPanel(ui, ranking, rowName, F(0.04f, yMin), F(0.96f, yMax), raised);
                if (podium) AddPanel(ui, rowName, rowName + ".Rail", "0 0", "0.012 1", podiumColors[i]);
                if (i >= leaders.Count)
                {
                    AddLabel(ui, rowName, "МЕСТО СВОБОДНО", "0.10 0.1", "0.90 0.9", 9, textSecondary, TextAnchor.MiddleCenter, false);
                    continue;
                }

                var row = leaders[i];
                AddLabel(ui, rowName, (i + 1).ToString(CultureInfo.InvariantCulture), "0.045 0.1", "0.145 0.9", 12,
                    podium ? podiumColors[i] : textSecondary, TextAnchor.MiddleCenter, true);
                AddSteamAvatar(ui, rowName, row.SteamId, "0.205 0.5", 16f, "1 1 1 1");
                AddLabel(ui, rowName, Short(Safe(row.Username), 17), "0.285 0.15", "0.74 0.85", 10, textMain, TextAnchor.MiddleLeft, true);
                AddLabel(ui, rowName, row.KeysDeposited.ToString(CultureInfo.InvariantCulture), "0.76 0.12", "0.93 0.88", 14, podium ? podiumColors[i] : textMain, TextAnchor.MiddleRight, true);
            }

            var playerData = snapshot.Player ?? new PlayerData();
            var profile = root + ".Player";
            AddPanel(ui, root, profile, "0 0.035", "1 0.235", surface);
            AddSteamAvatar(ui, profile, player.UserIDString, "0.055 0.5", 25f, "1 1 1 1");
            AddLabel(ui, profile, "ВАШ РЕЗУЛЬТАТ", "0.105 0.57", "0.30 0.84", 8, textSecondary, TextAnchor.MiddleLeft, true);
            AddLabel(ui, profile, Short(Safe(string.IsNullOrEmpty(playerData.Username) ? player.displayName : playerData.Username), 22), "0.105 0.22", "0.30 0.60", 13, textMain, TextAnchor.MiddleLeft, true);
            AddPlayerMetric(ui, profile, profile + ".Found", "0.32 0.18", "0.475 0.82", "НАЙДЕНО", playerData.KeysFound.ToString(CultureInfo.InvariantCulture), warning, raised);
            AddPlayerMetric(ui, profile, profile + ".Lost", "0.49 0.18", "0.645 0.82", "ПОТЕРЯНО", playerData.KeysLost.ToString(CultureInfo.InvariantCulture), danger, raised);
            AddPlayerMetric(ui, profile, profile + ".Deposited", "0.66 0.18", "0.815 0.82", "СДАНО", playerData.KeysDeposited.ToString(CultureInfo.InvariantCulture), success, raised);
            AddPlayerMetric(ui, profile, profile + ".Place", "0.83 0.18", "0.965 0.82", "МЕСТО", playerData.Position.HasValue ? "#" + playerData.Position.Value : "—", textMain, raised);
            CuiHelper.AddUi(player, ui);
        }

        private void AddInstructionRow(CuiElementContainer ui, string parent, string name, string min, string max,
            string iconFile, string title, string description, string iconColor, string panelColor, string textMain, string textSecondary)
        {
            AddPanel(ui, parent, name, min, max, panelColor);
            var iconPng = GetImage(SidebarIconUrl(iconFile));
            if (!string.IsNullOrEmpty(iconPng))
                AddFixedPng(ui, name, iconPng, "0.08 0.5", 14f, iconColor);
            AddLabel(ui, name, title, "0.16 0.50", "0.95 0.86", 10, textMain, TextAnchor.MiddleLeft, true);
            AddLabel(ui, name, description, "0.16 0.12", "0.95 0.52", 9, textSecondary, TextAnchor.MiddleLeft, false);
        }

        private string SidebarIconUrl(string file)
        {
            var relative = "rust-menu/icons/" + (file ?? string.Empty).TrimStart('/');
            var themed = ProstojMenu?.Call("API_GetImageUrl", relative) as string;
            return string.IsNullOrWhiteSpace(themed) ? "https://prostoj.store/images/" + relative : themed;
        }

        private void AddPlayerMetric(CuiElementContainer ui, string parent, string name, string min, string max, string label, string value, string color, string panelColor)
        {
            AddPanel(ui, parent, name, min, max, panelColor);
            AddLabel(ui, name, label, "0.08 0.60", "0.92 0.88", 8, "0.53 0.57 0.54 1", TextAnchor.MiddleCenter, true);
            AddLabel(ui, name, value, "0.08 0.12", "0.92 0.63", 18, color, TextAnchor.MiddleCenter, true);
        }

        private void AddMetric(CuiElementContainer ui, string parent, string name, string min, string max, string label, string value, string color)
        {
            AddPanel(ui, parent, name, min, max, "0.03 0.04 0.04 0.82");
            AddLabel(ui, name, label, "0.08 0.62", "0.92 0.90", 8, "0.58 0.61 0.59 1", TextAnchor.MiddleCenter, true);
            AddLabel(ui, name, value, "0.08 0.12", "0.92 0.64", 20, color, TextAnchor.MiddleCenter, true);
        }

        private bool IsMenuTabActive(BasePlayer player)
        {
            var result = ProstojMenu?.Call("API_IsTabActive", player, "tournament");
            return result is bool && (bool)result;
        }

        private void CacheImages()
        {
            if (ProstojMenu == null) return;
            ProstojMenu.Call("API_CacheImage", SidebarIconUrl("nav-tournament.png"));
            ProstojMenu.Call("API_CacheImage", SidebarIconUrl("cashrace-keep-key.png"));
            ProstojMenu.Call("API_CacheImage", SidebarIconUrl("cashrace-wait-terminal.png"));
            ProstojMenu.Call("API_CacheImage", SidebarIconUrl("cashrace-deposit-key.png"));
        }

        private string GetImage(string url)
        {
            var id = ProstojMenu?.Call("API_GetImage", url) as string;
            if (string.IsNullOrEmpty(id)) ProstojMenu?.Call("API_CacheImage", url);
            return id;
        }

        private void Post(string path, string body, Action<int, string> callback)
        {
            webrequest.Enqueue(config.ApiUrl + path, body, callback, this, RequestMethod.POST, Headers(), 15f);
        }

        private Dictionary<string, string> Headers() => new Dictionary<string, string> { ["Accept"] = "application/json", ["Content-Type"] = "application/json" };

        private bool TryEnvelope<T>(int code, string response, out ApiEnvelope<T> envelope)
        {
            envelope = null;
            if (code < 200 || code >= 300 || string.IsNullOrWhiteSpace(response))
            {
                if (config.Debug) PrintWarning("API request failed: HTTP " + code + " " + Safe(response));
                return false;
            }
            try { envelope = JsonConvert.DeserializeObject<ApiEnvelope<T>>(response); }
            catch (Exception exception) { PrintWarning("API JSON error: " + exception.Message); return false; }
            if (envelope == null || !envelope.Success)
            {
                if (config.Debug) PrintWarning("API error: " + (envelope?.Error?.Message ?? "unknown"));
                return false;
            }
            return true;
        }

        private void SaveData() => Interface.Oxide.DataFileSystem.WriteObject(DataFileName, storedData);

        private static string Safe(string value) => string.IsNullOrWhiteSpace(value) ? string.Empty : value.Replace("<", "").Replace(">", "");

        private static string ThemeToken(Dictionary<string, string> theme, string key, string fallback)
        {
            string value;
            return theme != null && theme.TryGetValue(key, out value) && !string.IsNullOrWhiteSpace(value) ? value : fallback;
        }

        private static string Short(string value, int length)
        {
            if (string.IsNullOrEmpty(value) || value.Length <= length) return value ?? string.Empty;
            return value.Substring(0, Math.Max(1, length - 1)) + "…";
        }

        private static string Wrap(string value, int width, int lines)
        {
            if (string.IsNullOrWhiteSpace(value)) return string.Empty;
            var words = value.Split(new[] { ' ' }, StringSplitOptions.RemoveEmptyEntries);
            var result = new List<string>();
            var current = string.Empty;
            foreach (var word in words)
            {
                if ((current.Length + word.Length + 1) > width)
                {
                    result.Add(current);
                    current = word;
                    if (result.Count >= lines) break;
                }
                else current = string.IsNullOrEmpty(current) ? word : current + " " + word;
            }
            if (result.Count < lines && !string.IsNullOrEmpty(current)) result.Add(current);
            return string.Join("\n", result.Take(lines));
        }

        private static string FormatDuration(long seconds)
        {
            var span = TimeSpan.FromSeconds(Math.Max(0, seconds));
            return span.Days > 0
                ? span.Days + " ДН.  " + span.Hours.ToString("00") + " Ч.  " + span.Minutes.ToString("00") + " МИН."
                : span.Hours.ToString("00") + " Ч.  " + span.Minutes.ToString("00") + " МИН.  " + span.Seconds.ToString("00") + " СЕК.";
        }

        private static string Word(int value, string one, string few, string many)
        {
            var n = value % 100;
            if (n >= 11 && n <= 19) return many;
            switch (value % 10) { case 1: return one; case 2: case 3: case 4: return few; default: return many; }
        }

        private static string F(float x, float y) => x.ToString("0.###", CultureInfo.InvariantCulture) + " " + y.ToString("0.###", CultureInfo.InvariantCulture);

        private static void AddPanel(CuiElementContainer ui, string parent, string name, string min, string max, string color)
        {
            ui.Add(new CuiPanel { Image = { Color = color }, RectTransform = { AnchorMin = min, AnchorMax = max } }, parent, name);
        }

        private static void AddLabel(CuiElementContainer ui, string parent, string text, string min, string max, int size, string color, TextAnchor align, bool bold)
        {
            ui.Add(new CuiLabel
            {
                Text = { Text = text ?? string.Empty, FontSize = size, Color = color, Align = align, Font = bold ? "robotocondensed-bold.ttf" : "robotocondensed-regular.ttf" },
                RectTransform = { AnchorMin = min, AnchorMax = max }
            }, parent);
        }

        private static void AddButton(CuiElementContainer ui, string parent, string min, string max, string command, string text, string color)
        {
            ui.Add(new CuiButton
            {
                Button = { Color = color, Command = command },
                RectTransform = { AnchorMin = min, AnchorMax = max },
                Text = { Text = text, FontSize = 11, Color = "0.94 0.96 0.95 1", Align = TextAnchor.MiddleCenter, Font = "robotocondensed-bold.ttf" }
            }, parent);
        }

        private static void AddPng(CuiElementContainer ui, string parent, string png, string min, string max, string color)
        {
            ui.Add(new CuiElement
            {
                Parent = parent,
                Components =
                {
                    new CuiRawImageComponent { Png = png, Color = color },
                    new CuiRectTransformComponent { AnchorMin = min, AnchorMax = max }
                }
            });
        }

        private static void AddFixedPng(CuiElementContainer ui, string parent, string png, string center, float halfSizePixels, string color)
        {
            var half = Mathf.Max(1f, halfSizePixels).ToString("0.###", CultureInfo.InvariantCulture);
            ui.Add(new CuiElement
            {
                Parent = parent,
                Components =
                {
                    new CuiRawImageComponent { Png = png, Color = color },
                    new CuiRectTransformComponent
                    {
                        AnchorMin = center,
                        AnchorMax = center,
                        OffsetMin = "-" + half + " -" + half,
                        OffsetMax = half + " " + half
                    }
                }
            });
        }

        private static void AddSteamAvatar(CuiElementContainer ui, string parent, string steamId, string center, float halfSizePixels, string color)
        {
            ulong parsed;
            if (!ulong.TryParse(steamId, NumberStyles.Integer, CultureInfo.InvariantCulture, out parsed) || parsed == 0) return;
            var half = Mathf.Max(1f, halfSizePixels).ToString("0.###", CultureInfo.InvariantCulture);
            ui.Add(new CuiElement
            {
                Parent = parent,
                Components =
                {
                    new CuiRawImageComponent { SteamId = parsed.ToString(CultureInfo.InvariantCulture), Color = color },
                    new CuiRectTransformComponent
                    {
                        AnchorMin = center,
                        AnchorMax = center,
                        OffsetMin = "-" + half + " -" + half,
                        OffsetMax = half + " " + half
                    }
                }
            });
        }
    }
}
