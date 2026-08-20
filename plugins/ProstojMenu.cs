using System;
using System.Collections;
using System.Collections.Generic;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Security.Cryptography;
using System.Text;
using Newtonsoft.Json;
using Oxide.Core;
using Oxide.Core.Libraries;
using Oxide.Core.Plugins;
using Oxide.Game.Rust.Cui;
using UnityEngine;
using UnityEngine.Networking;

#pragma warning disable 0649 // DTO fields are populated by Newtonsoft.Json.

namespace Oxide.Plugins
{
    [Info("ProstojMenu", "Prostoj Team", "3.0.20")]
    [Description("Unified Prostoj in-game menu with pluggable tabs and website data.")]
    public class ProstojMenu : RustPlugin
    {
        private const string Root = "ProstojMenu.Root";
        private const string Frame = Root + ".Frame";
        private const string Sidebar = Frame + ".Sidebar";
        private const string Navigation = Sidebar + ".Dynamic";
        private const string Main = Frame + ".Main";
        private const string Header = Main + ".Header";
        private const string Content = Main + ".Content";
        private const string Toast = Frame + ".Toast";
        private const float SidebarWidth = 0.1725f;
        private const float SidebarContentGap = 0.005f;
        private const string PermissionUse = "prostojmenu.use";
        private const string ImageCacheDirectory = "ProstojMenu/Images";
        private const int MaxConcurrentImageDownloads = 3;
        private const int MaxAutomaticImageRetries = 4;
        private const float FileStorageStartupGuardSeconds = 180f;
        private const string StoreCartImageUrl = "https://img.icons8.com/material-rounded/256/ffffff/shopping-cart.png";
        private const string ProstojMenuBackgroundImageUrl = "https://prostoj.store/images/rust-menu/prostoj-command-center-v3.jpg";
        private const string MoscowMenuBackgroundImageUrl = "https://prostoj.store/images/rust-menu/moscow77-command-center-v1.jpg";
        private const string LegacyMenuBackgroundImageUrl = "https://prostoj.store/images/rust-menu/prostoj-command-center-v2.jpg";
        private static bool useMoscowVisualTheme;

        // Shared CUI design tokens. Neutral translucent surfaces mirror the
        // lightweight in-game reference; only the selected navigation row is branded.
        private const string Scrim = "0.020 0.020 0.018 0.62";
        private const string BgMain = "0.259 0.247 0.220 0.94";
        private const string BgSecondary = "0.259 0.247 0.220 0.92";
        private const string BgTertiary = "0.330 0.314 0.290 0.84";
        private const string BgRaised = "0.412 0.392 0.369 0.80";
        private const string ProfileSurface = "0.330 0.314 0.290 0.42";
        private const string TextMain = "0.835 0.808 0.769 1";
        private const string TextSecondary = "0.650 0.625 0.590 1";
        private const string Border = "0.070 0.068 0.060 0.78";
        // Actions stay neutral like the reference. Only the active navigation
        // item is brand-coloured (red for Prostoj, emerald for Moscow).
        private const string Accent = "0.412 0.392 0.369 1";
        private const string AccentWarm = "0.520 0.495 0.455 1";
        private const string AccentSoft = "0.412 0.392 0.369 0.20";
        private const string AccentActive = "0.860 0.220 0.330 0.88";
        private const string AccentActiveBorder = "0.860 0.220 0.330 1";
        private const string Success = "0.340 0.720 0.540 1";
        private const string Warning = "0.820 0.660 0.400 1";
        private const string Danger = "0.860 0.420 0.480 1";
        private const string Gold = "0.880 0.670 0.390 1";
        private const string FrameBorder = "0.070 0.068 0.060 0.92";
        private const string Silver = "0.706 0.706 0.706 1";
        private const string Bronze = "0.686 0.451 0.333 1";
        private const string FontRegular = "robotocondensed-regular.ttf";
        private const string FontBold = "robotocondensed-bold.ttf";

        private Configuration settings;
        private readonly Dictionary<ulong, PlayerView> views = new Dictionary<ulong, PlayerView>();
        private readonly Dictionary<string, MenuTab> tabs = new Dictionary<string, MenuTab>(StringComparer.OrdinalIgnoreCase);
        private readonly Dictionary<string, CachedImage> cachedImages = new Dictionary<string, CachedImage>(StringComparer.Ordinal);
        private readonly Dictionary<string, CachedCalendarMonth> cachedCalendarMonths = new Dictionary<string, CachedCalendarMonth>(StringComparer.Ordinal);
        private readonly System.Collections.Generic.Queue<CachedImage> pendingImages = new System.Collections.Generic.Queue<CachedImage>();
        private readonly System.Collections.Generic.Queue<ulong> pendingSnapshotPrefetches = new System.Collections.Generic.Queue<ulong>();
        private readonly HashSet<ulong> queuedSnapshotPrefetches = new HashSet<ulong>();
        private int activeImageDownloads;
        private int activeSnapshotPrefetches;
        private DateTime imageCacheAllowedAtUtc = DateTime.MaxValue;
        private bool imageRefreshScheduled;
        private bool imageCacheDeferredStartupScheduled;
        private bool imageShellRefreshRequired;
        private bool imageCacheReady;
        private bool imageCacheUnloading;

        private class Configuration
        {
            [JsonProperty("API URL")]
            public string ApiUrl = "https://api.prostoj.store/v1/rust-menu/snapshot";

            [JsonProperty("Support API URL")]
            public string SupportApiUrl = "https://api.prostoj.store/v1/rust-menu/support";

            [JsonProperty("Battle Pass API URL")]
            public string BattlePassApiUrl = "https://api.prostoj.store/v1/rust-menu/battlepass";

            [JsonProperty("Calendar API URL")]
            public string CalendarApiUrl = "https://api.prostoj.store/v1/wipe-calendar/server";

            [JsonProperty("Rust server secret")]
            public string ServerSecret = "CHANGE_ME";

            [JsonProperty("Server tag (recommended; IP and port are used when empty)")]
            public string ServerTag = string.Empty;

            [JsonProperty("Chat command")]
            public string ChatCommand = "menu";

            [JsonProperty("Snapshot cache seconds")]
            public int CacheSeconds = 30;

            [JsonProperty("Prefetch snapshots for online players")]
            public bool PrefetchOnlinePlayers = true;

            [JsonProperty("Online snapshot prefetch delay seconds")]
            public float PrefetchDelaySeconds = 12f;

            [JsonProperty("Maximum simultaneous snapshot prefetches")]
            public int MaxConcurrentPrefetches = 1;

            [JsonProperty("Require permission")]
            public bool RequirePermission;

            [JsonProperty("Visual theme (prostoj or moscow77)")]
            public string VisualTheme = "prostoj";

            [JsonProperty("Background artwork URL")]
            public string BackgroundImageUrl = ProstojMenuBackgroundImageUrl;

            [JsonProperty("Website images base URL")]
            public string ImagesBaseUrl = "https://prostoj.store/images";
        }

        private class PlayerView
        {
            public bool Open;
            public bool Loading;
            public string ActiveTab = "calendar";
            public int Page;
            public int RequestVersion;
            public DateTime LoadedAtUtc = DateTime.MinValue;
            public DateTime NextManualRefreshAtUtc = DateTime.MinValue;
            public MenuSnapshot Snapshot;
            public string Error;
            public bool SupportLoading;
            public bool SupportSending;
            public bool SupportClosing;
            public bool SupportComposeNew;
            public int SupportRequestVersion;
            public DateTime SupportNextPollAtUtc = DateTime.MinValue;
            public long? SupportSelectedTicket;
            public string SupportDraft = string.Empty;
            public SupportData Support;
            public string SupportError;
            public bool BattlePassLoading;
            public bool BattlePassChecking;
            public int BattlePassRequestVersion;
            public int BattlePassPage;
            public DateTime BattlePassLoadedAtUtc = DateTime.MinValue;
            public BattlePassData BattlePass;
            public string BattlePassError;
            public bool CalendarLoading;
            public int CalendarRequestVersion;
            public string CalendarRequestedMonthKey;
            public string CalendarMonthKey;
            public CalendarMonthData CalendarMonth;
            public string CalendarError;
        }

        private class MenuTab
        {
            public Plugin Owner;
            public string Key;
            public string Title;
            public string Glyph;
            public int Order;
            public bool BuiltIn;
        }

        private enum CachedImageStatus
        {
            Pending,
            Loading,
            Loaded,
            Failed
        }

        private class CachedImage
        {
            public string Url;
            public string LocalPath;
            public string PngId;
            public int Width;
            public int Height;
            public CachedImageStatus Status;
            public bool RequiresShellRefresh;
            public int FailureCount;
            public DateTime RetryAtUtc;
        }

        private class CachedCalendarMonth
        {
            public CalendarMonthData Data;
            public DateTime LoadedAtUtc;
        }

        private class ApiEnvelope
        {
            [JsonProperty("success")] public bool Success;
            [JsonProperty("data")] public MenuSnapshot Data;
            [JsonProperty("error")] public ApiError Error;
        }

        private class ApiError
        {
            [JsonProperty("message")] public string Message;
        }

        private class SupportApiEnvelope
        {
            [JsonProperty("success")] public bool Success;
            [JsonProperty("data")] public SupportData Data;
            [JsonProperty("error")] public ApiError Error;
        }

        private class BattlePassApiEnvelope
        {
            [JsonProperty("success")] public bool Success;
            [JsonProperty("data")] public BattlePassData Data;
            [JsonProperty("error")] public ApiError Error;
        }

        private class BattlePassCheckEnvelope
        {
            [JsonProperty("success")] public bool Success;
            [JsonProperty("data")] public BattlePassCheckData Data;
            [JsonProperty("error")] public ApiError Error;
        }

        private class BattlePassCheckData
        {
            [JsonProperty("success")] public bool Success;
            [JsonProperty("message")] public string Message;
        }

        private class BattlePassData
        {
            [JsonProperty("isAuthenticated")] public bool IsAuthenticated;
            [JsonProperty("season")] public BattlePassSeasonData Season = new BattlePassSeasonData();
            [JsonProperty("progress")] public BattlePassProgressData Progress = new BattlePassProgressData();
            [JsonProperty("hasVip")] public bool HasVip;
            [JsonProperty("freeTasks")] public List<BattlePassTaskData> FreeTasks = new List<BattlePassTaskData>();
            [JsonProperty("vipTasks")] public List<BattlePassTaskData> VipTasks = new List<BattlePassTaskData>();
        }

        private class BattlePassSeasonData
        {
            [JsonProperty("id")] public int Id;
            [JsonProperty("name")] public string Name;
            [JsonProperty("seasonNumber")] public int SeasonNumber;
            [JsonProperty("description")] public string Description;
            [JsonProperty("startsAt")] public string StartsAt;
            [JsonProperty("endsAt")] public string EndsAt;
            [JsonProperty("medal")] public BattlePassMedalData Medal;
        }

        private class BattlePassMedalData
        {
            [JsonProperty("id")] public int Id;
            [JsonProperty("name")] public string Name;
            [JsonProperty("description")] public string Description;
            [JsonProperty("image")] public string Image;
        }

        private class BattlePassProgressData
        {
            [JsonProperty("completed")] public int Completed;
            [JsonProperty("total")] public int Total;
            [JsonProperty("percent")] public int Percent;
            [JsonProperty("isCompleted")] public bool IsCompleted;
            [JsonProperty("currentTaskId")] public int? CurrentTaskId;
        }

        private class BattlePassTaskData
        {
            [JsonProperty("id")] public int Id;
            [JsonProperty("position")] public int Position;
            [JsonProperty("title")] public string Title;
            [JsonProperty("short_description")] public string ShortDescription;
            [JsonProperty("reward_type")] public string RewardType;
            [JsonProperty("reward_amount")] public double? RewardAmount;
            [JsonProperty("reward_item")] public BattlePassRewardItemData RewardItem;
            [JsonProperty("is_vip_only")] public bool IsVipOnly;
            [JsonProperty("sort")] public int Sort;
            [JsonProperty("userStatus")] public BattlePassTaskStatusData UserStatus = new BattlePassTaskStatusData();
            [JsonProperty("progress")] public int? Progress;
            [JsonProperty("maxProgress")] public int? MaxProgress;
        }

        private class BattlePassRewardItemData
        {
            [JsonProperty("id")] public int Id;
            [JsonProperty("item_id")] public int? ItemId;
            [JsonProperty("name")] public string Name;
            [JsonProperty("image")] public string Image;
            [JsonProperty("count")] public int Count;
        }

        private class BattlePassTaskStatusData
        {
            [JsonProperty("status")] public string Status;
            [JsonProperty("message")] public string Message;
        }

        private class SupportData
        {
            [JsonProperty("unchanged")] public bool Unchanged;
            [JsonProperty("revision")] public string Revision;
            [JsonProperty("registered")] public bool Registered;
            [JsonProperty("can_write")] public bool CanWrite;
            [JsonProperty("notice")] public string Notice;
            [JsonProperty("unread_count")] public int UnreadCount;
            [JsonProperty("unread_count_capped")] public bool UnreadCountCapped;
            [JsonProperty("server_tag")] public string ServerTag;
            [JsonProperty("tickets")] public List<SupportTicketData> Tickets = new List<SupportTicketData>();
            [JsonProperty("active_ticket")] public SupportTicketData ActiveTicket;
            [JsonProperty("messages")] public List<SupportMessageData> Messages = new List<SupportMessageData>();
        }

        private class SupportTicketData
        {
            [JsonProperty("number")] public long Number;
            [JsonProperty("status")] public string Status;
            [JsonProperty("server_tag")] public string ServerTag;
            [JsonProperty("updated_at")] public string UpdatedAt;
            [JsonProperty("unread_count")] public int UnreadCount;
        }

        private class SupportMessageData
        {
            [JsonProperty("id")] public long Id;
            [JsonProperty("is_own")] public bool IsOwn;
            [JsonProperty("is_staff")] public bool IsStaff;
            [JsonProperty("author")] public string Author;
            [JsonProperty("steam_id")] public string SteamId;
            [JsonProperty("avatar")] public string Avatar;
            [JsonProperty("message")] public string Message;
            [JsonProperty("created_at")] public string CreatedAt;
        }

        private class SupportSummaryData
        {
            [JsonProperty("unread_count")] public int UnreadCount;
            [JsonProperty("open_count")] public int OpenCount;
        }

        private class MenuSnapshot
        {
            [JsonProperty("generated_at")] public long GeneratedAt;
            [JsonProperty("server")] public ServerData Server = new ServerData();
            [JsonProperty("player")] public PlayerData Player = new PlayerData();
            [JsonProperty("calendar")] public List<CalendarEvent> Calendar = new List<CalendarEvent>();
            [JsonProperty("leaderboard")] public LeaderboardData Leaderboard = new LeaderboardData();
            [JsonProperty("clans")] public ClansData Clans = new ClansData();
            [JsonProperty("support")] public SupportSummaryData Support = new SupportSummaryData();
        }

        private class ServerData
        {
            [JsonProperty("id")] public int Id;
            [JsonProperty("tag")] public string Tag;
            [JsonProperty("name")] public string Name;
            [JsonProperty("players")] public int Players;
            [JsonProperty("max_players")] public int MaxPlayers;
            [JsonProperty("current_wipe")] public string CurrentWipe;
            [JsonProperty("next_wipe_at")] public string NextWipeAt;
        }

        private class PlayerData
        {
            [JsonProperty("registered")] public bool Registered;
            [JsonProperty("steam_id")] public string SteamId;
            [JsonProperty("username")] public string Username;
            [JsonProperty("avatar")] public string Avatar;
            [JsonProperty("balance")] public int Balance;
            [JsonProperty("currency")] public string Currency = "RUB";
            [JsonProperty("stats")] public PlayerStats Stats = new PlayerStats();
        }

        private class PlayerStats
        {
            [JsonProperty("kills")] public int Kills;
            [JsonProperty("deaths")] public int Deaths;
            [JsonProperty("kd")] public double Kd;
            [JsonProperty("playtime")] public int Playtime;
            [JsonProperty("scientists")] public int Scientists;
            [JsonProperty("headshots")] public int Headshots;
            [JsonProperty("raid_score")] public double RaidScore;
            [JsonProperty("loot")] public List<LootMetric> Loot = new List<LootMetric>();
            [JsonProperty("found")] public List<LootMetric> Found = new List<LootMetric>();
            [JsonProperty("resources")] public ResourceData Resources = new ResourceData();
        }

        private class LootMetric
        {
            [JsonProperty("key")] public string Key;
            [JsonProperty("name")] public string Name;
            [JsonProperty("image")] public string Image;
            [JsonProperty("count")] public int Count;
        }

        private class ResourceData
        {
            [JsonProperty("wood")] public int Wood;
            [JsonProperty("stones")] public int Stones;
            [JsonProperty("metal")] public int Metal;
            [JsonProperty("sulfur")] public int Sulfur;
        }

        private class CalendarEvent
        {
            [JsonProperty("id")] public int Id;
            [JsonProperty("type")] public string Type;
            [JsonProperty("title")] public string Title;
            [JsonProperty("label")] public string Label;
            [JsonProperty("event_at")] public string EventAt;
        }

        private class CalendarApiEnvelope
        {
            [JsonProperty("success")] public bool Success;
            [JsonProperty("data")] public CalendarMonthData Data;
            [JsonProperty("error")] public ApiError Error;
        }

        private class CalendarMonthData
        {
            [JsonProperty("events")] public Dictionary<string, List<CalendarMonthEvent>> Events =
                new Dictionary<string, List<CalendarMonthEvent>>(StringComparer.Ordinal);
            [JsonProperty("highlights")] public Dictionary<string, string> Highlights =
                new Dictionary<string, string>(StringComparer.Ordinal);
        }

        private class CalendarMonthEvent
        {
            [JsonProperty("id")] public int Id;
            [JsonProperty("date")] public string Date;
            [JsonProperty("event_type")] public string Type;
        }

        private class LeaderboardData
        {
            [JsonProperty("categories")] public Dictionary<string, LeaderboardCategory> Categories
                = new Dictionary<string, LeaderboardCategory>(StringComparer.OrdinalIgnoreCase);
        }

        private class LeaderboardCategory
        {
            [JsonProperty("label")] public string Label;
            [JsonProperty("items")] public List<LeaderboardRow> Items = new List<LeaderboardRow>();
        }

        private class LeaderboardRow
        {
            [JsonProperty("position")] public int Position;
            [JsonProperty("steam_id")] public string SteamId;
            [JsonProperty("username")] public string Username;
            [JsonProperty("avatar")] public string Avatar;
            [JsonProperty("score")] public string Score;
            [JsonProperty("status")] public bool? Status;
            [JsonProperty("is_hidden")] public bool IsHidden;
        }

        private class ClansData
        {
            [JsonProperty("my_clan")] public ClanData MyClan;
            [JsonProperty("items")] public List<ClanData> Items = new List<ClanData>();
        }

        private class ClanData
        {
            [JsonProperty("id")] public int Id;
            [JsonProperty("name")] public string Name;
            [JsonProperty("tag")] public string Tag;
            [JsonProperty("tag_color")] public string TagColor;
            [JsonProperty("motto")] public string Motto;
            [JsonProperty("members")] public int Members;
            [JsonProperty("level")] public int Level;
            [JsonProperty("experience")] public int Experience;
            [JsonProperty("role")] public string Role;
        }

        protected override void LoadDefaultConfig()
        {
            settings = new Configuration();
            SaveConfig();
        }

        protected override void LoadConfig()
        {
            base.LoadConfig();
            try
            {
                settings = Config.ReadObject<Configuration>() ?? new Configuration();
            }
            catch (Exception exception)
            {
                PrintError("Invalid configuration: " + exception.Message);
                settings = new Configuration();
            }

            settings.CacheSeconds = Mathf.Clamp(settings.CacheSeconds, 10, 300);
            settings.PrefetchDelaySeconds = Mathf.Clamp(settings.PrefetchDelaySeconds, 5f, 120f);
            settings.MaxConcurrentPrefetches = Mathf.Clamp(settings.MaxConcurrentPrefetches, 1, 4);
            settings.ApiUrl = (settings.ApiUrl ?? string.Empty).Trim();
            settings.SupportApiUrl = (settings.SupportApiUrl ?? string.Empty).Trim();
            if (string.IsNullOrEmpty(settings.SupportApiUrl) && settings.ApiUrl.EndsWith("/snapshot", StringComparison.OrdinalIgnoreCase))
                settings.SupportApiUrl = settings.ApiUrl.Substring(0, settings.ApiUrl.Length - "/snapshot".Length) + "/support";
            settings.BattlePassApiUrl = (settings.BattlePassApiUrl ?? string.Empty).Trim();
            if (string.IsNullOrEmpty(settings.BattlePassApiUrl) && settings.ApiUrl.EndsWith("/snapshot", StringComparison.OrdinalIgnoreCase))
                settings.BattlePassApiUrl = settings.ApiUrl.Substring(0, settings.ApiUrl.Length - "/snapshot".Length) + "/battlepass";
            settings.CalendarApiUrl = (settings.CalendarApiUrl ?? string.Empty).Trim();
            if (string.IsNullOrEmpty(settings.CalendarApiUrl))
                settings.CalendarApiUrl = "https://api.prostoj.store/v1/wipe-calendar/server";
            settings.ServerTag = (settings.ServerTag ?? string.Empty).Trim();
            settings.ChatCommand = string.IsNullOrWhiteSpace(settings.ChatCommand) ? "menu" : settings.ChatCommand.Trim();
            settings.VisualTheme = NormalizeVisualTheme(settings.VisualTheme);
            useMoscowVisualTheme = string.Equals(settings.VisualTheme, "moscow77", StringComparison.Ordinal);
            settings.BackgroundImageUrl = (settings.BackgroundImageUrl ?? string.Empty).Trim();
            if (string.IsNullOrEmpty(settings.BackgroundImageUrl) ||
                string.Equals(settings.BackgroundImageUrl, LegacyMenuBackgroundImageUrl, StringComparison.OrdinalIgnoreCase) ||
                string.Equals(settings.BackgroundImageUrl, ProstojMenuBackgroundImageUrl, StringComparison.OrdinalIgnoreCase) ||
                string.Equals(settings.BackgroundImageUrl, MoscowMenuBackgroundImageUrl, StringComparison.OrdinalIgnoreCase))
                settings.BackgroundImageUrl = useMoscowVisualTheme ? MoscowMenuBackgroundImageUrl : ProstojMenuBackgroundImageUrl;
            settings.ImagesBaseUrl = (settings.ImagesBaseUrl ?? string.Empty).Trim().TrimEnd('/');
            SaveConfig();
        }

        protected override void SaveConfig() => Config.WriteObject(settings, true);

        private void Init()
        {
            // On a real server boot FileStorage must not be touched until the
            // world is ready. OnPlugin hot reloads are distinguished later by
            // OnServerInitialized(initial), so do not start a new three-minute
            // blackout here on every edit/reload.
            imageCacheAllowedAtUtc = DateTime.MaxValue;
            permission.RegisterPermission(PermissionUse, this);
            cmd.AddChatCommand(settings.ChatCommand, this, nameof(ChatOpenMenu));
            RegisterBuiltInTabs();
        }

        private void OnServerInitialized(bool initial)
        {
            imageCacheUnloading = false;
            imageCacheAllowedAtUtc = initial
                ? DateTime.UtcNow.AddSeconds(FileStorageStartupGuardSeconds)
                : DateTime.UtcNow;
            StartImageCache();

            // A short scheduler tick lets every player keep an independent,
            // jittered polling deadline instead of hitting the API in one burst.
            timer.Every(2f, PollSupportViews);
            timer.Every(1f, PumpSnapshotPrefetches);
            if (settings.PrefetchOnlinePlayers)
            {
                timer.Once(settings.PrefetchDelaySeconds, () =>
                {
                    foreach (var player in BasePlayer.activePlayerList)
                        QueueSnapshotPrefetch(player);
                });
            }
        }

        private void OnPlayerConnected(BasePlayer player)
        {
            if (!settings.PrefetchOnlinePlayers || player == null) return;
            var jitter = (float)(player.userID % 8UL);
            timer.Once(settings.PrefetchDelaySeconds + jitter, () => QueueSnapshotPrefetch(player));
        }

        private void Unload()
        {
            imageCacheUnloading = true;
            imageCacheReady = false;
            imageCacheDeferredStartupScheduled = false;
            pendingImages.Clear();
            pendingSnapshotPrefetches.Clear();
            queuedSnapshotPrefetches.Clear();
            foreach (var player in BasePlayer.activePlayerList)
                CuiHelper.DestroyUi(player, Root);
            views.Clear();
            tabs.Clear();
            cachedImages.Clear();
        }

        private void OnPlayerDisconnected(BasePlayer player, string reason)
        {
            views.Remove(player.userID);
            queuedSnapshotPrefetches.Remove(player.userID);
        }

        private void OnPluginUnloaded(Plugin plugin)
        {
            if (plugin == null) return;

            var ownedTabs = tabs.Values.Where(tab => tab.Owner == plugin).ToList();
            if (ownedTabs.Count == 0) return;

            var affectedKeys = new HashSet<string>(ownedTabs.Select(tab => tab.Key), StringComparer.OrdinalIgnoreCase);
            foreach (var tab in ownedTabs)
            {
                if (tab.BuiltIn)
                    RestoreBuiltIn(tab.Key);
                else
                    tabs.Remove(tab.Key);
            }

            foreach (var entry in views)
            {
                var view = entry.Value;
                if (!view.Open || !affectedKeys.Contains(view.ActiveTab)) continue;

                if (!tabs.ContainsKey(view.ActiveTab))
                    view.ActiveTab = "calendar";

                var player = BasePlayer.FindByID(entry.Key);
                if (player != null && player.IsConnected)
                {
                    RenderNavigation(player, view);
                    RenderHeader(player, view);
                    RenderContent(player, view);
                }
            }
        }

        private void RegisterBuiltInTabs()
        {
            RegisterBuiltIn("store", "КОРЗИНА", "CART", 10);
            RegisterBuiltIn("battlepass", "BATTLE PASS", "PASS", 20);
            RegisterBuiltIn("calendar", "КАЛЕНДАРЬ", "WIPE", 30);
            RegisterBuiltIn("stats", "МОЯ СТАТИСТИКА", "STAT", 40);
            RegisterBuiltIn("top", "ТОП ИГРОКОВ", "TOP", 50);
            RegisterBuiltIn("support", "ПОДДЕРЖКА", "SUPPORT", 70);
        }

        private void RegisterBuiltIn(string key, string title, string glyph, int order)
        {
            tabs[key] = new MenuTab { Key = key, Title = title, Glyph = glyph, Order = order, BuiltIn = true };
        }

        private bool RestoreBuiltIn(string key)
        {
            switch (key)
            {
                case "store":
                    RegisterBuiltIn("store", "КОРЗИНА", "CART", 10);
                    return true;
                case "battlepass":
                    RegisterBuiltIn("battlepass", "BATTLE PASS", "PASS", 20);
                    return true;
                case "calendar":
                    RegisterBuiltIn("calendar", "КАЛЕНДАРЬ", "WIPE", 30);
                    return true;
                case "stats":
                    RegisterBuiltIn("stats", "МОЯ СТАТИСТИКА", "STAT", 40);
                    return true;
                case "top":
                    RegisterBuiltIn("top", "ТОП ИГРОКОВ", "TOP", 50);
                    return true;
                case "support":
                    RegisterBuiltIn("support", "ПОДДЕРЖКА", "SUPPORT", 70);
                    return true;
                default:
                    return false;
            }
        }

        private object API_RegisterTab(Plugin owner, string key, string title, string glyph = "APP", int order = 100)
        {
            key = NormalizeKey(key);
            if (owner == null || string.IsNullOrEmpty(key) || string.IsNullOrWhiteSpace(title))
                return false;

            MenuTab existing;
            tabs.TryGetValue(key, out existing);
            tabs[key] = new MenuTab
            {
                Owner = owner,
                Key = key,
                Title = title.Trim().ToUpperInvariant(),
                Glyph = string.IsNullOrWhiteSpace(glyph) ? "APP" : glyph.Trim().ToUpperInvariant(),
                Order = order,
                BuiltIn = existing != null && existing.BuiltIn
            };
            RedrawOpenViews(key);
            return true;
        }

        private object API_UnregisterTab(Plugin owner, string key)
        {
            key = NormalizeKey(key);
            MenuTab tab;
            if (!tabs.TryGetValue(key, out tab) || tab.Owner != owner)
                return false;

            if (tab.BuiltIn)
                RestoreBuiltIn(key);
            else
                tabs.Remove(key);
            RedrawOpenViews(key);
            return true;
        }

        // Returns a Rust FileStorage PNG id. On the first call the URL is queued
        // for download; connected plugins can call this hook again after their
        // UI is redrawn, or use ProstojMenu's automatic shell refresh.
        private object API_GetImage(string url)
        {
            return ResolveImage(url);
        }

        private object API_GetImageInfo(string url)
        {
            var image = EnsureImage(url);
            if (image == null || image.Status != CachedImageStatus.Loaded || string.IsNullOrEmpty(image.PngId))
                return null;

            return new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase)
            {
                ["png"] = image.PngId,
                ["width"] = Math.Max(1, image.Width).ToString(CultureInfo.InvariantCulture),
                ["height"] = Math.Max(1, image.Height).ToString(CultureInfo.InvariantCulture)
            };
        }

        private object API_CacheImage(string url)
        {
            return EnsureImage(url) != null;
        }

        private object API_GetImageUrl(string relativePath)
        {
            return ImageUrl(relativePath);
        }

        // Shared visual tokens keep connected plugin tabs in the same theme as
        // the shell without duplicating the theme selector in every plugin.
        private object API_GetTheme()
        {
            return new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase)
            {
                ["name"] = useMoscowVisualTheme ? "moscow77" : "prostoj",
                ["bg_main"] = ThemeColor(BgMain),
                ["bg_secondary"] = ThemeColor(BgSecondary),
                ["bg_tertiary"] = ThemeColor(BgTertiary),
                ["bg_raised"] = ThemeColor(BgRaised),
                ["bg_image"] = ThemeColor("0.259 0.247 0.220 0.78"),
                ["bg_disabled"] = ThemeColor("0.330 0.314 0.290 0.38"),
                ["text_main"] = ThemeColor(TextMain),
                ["text_secondary"] = ThemeColor(TextSecondary),
                ["border"] = ThemeColor(Border),
                ["accent"] = ThemeColor(AccentWarm),
                ["accent_primary"] = ThemeColor(Accent),
                ["accent_soft"] = ThemeColor(AccentSoft),
                ["accent_active"] = ThemeColor(AccentActive),
                ["danger"] = ThemeColor(Danger),
                ["danger_soft"] = ThemeColor("0.860 0.420 0.480 0.24"),
                ["success"] = ThemeColor(Success),
                ["warning"] = ThemeColor(Warning),
                ["gold"] = ThemeColor(Gold)
            };
        }

        private object API_GetServerIdentity()
        {
            return new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase)
            {
                ["server_tag"] = settings.ServerTag ?? string.Empty,
                ["server_ip"] = ConVar.Server.ip ?? string.Empty,
                ["server_port"] = ConVar.Server.port.ToString(CultureInfo.InvariantCulture),
                ["skindrops_api_url"] = settings.ApiUrl.EndsWith("/snapshot", StringComparison.OrdinalIgnoreCase)
                    ? settings.ApiUrl.Substring(0, settings.ApiUrl.Length - "/snapshot".Length) + "/skindrops"
                    : settings.ApiUrl.TrimEnd('/') + "/skindrops",
                ["notifications_api_url"] = settings.ApiUrl.EndsWith("/snapshot", StringComparison.OrdinalIgnoreCase)
                    ? settings.ApiUrl.Substring(0, settings.ApiUrl.Length - "/snapshot".Length) + "/notifications"
                    : settings.ApiUrl.TrimEnd('/') + "/notifications",
                ["shop_api_url"] = settings.ApiUrl.EndsWith("/snapshot", StringComparison.OrdinalIgnoreCase)
                    ? settings.ApiUrl.Substring(0, settings.ApiUrl.Length - "/snapshot".Length) + "/shop"
                    : settings.ApiUrl.TrimEnd('/') + "/shop"
            };
        }

        private void RedrawOpenViews(string key)
        {
            foreach (var entry in views.ToArray())
            {
                var view = entry.Value;
                if (!view.Open || !string.Equals(view.ActiveTab, key, StringComparison.OrdinalIgnoreCase)) continue;

                if (!tabs.ContainsKey(view.ActiveTab))
                    view.ActiveTab = "calendar";

                var player = BasePlayer.FindByID(entry.Key);
                if (player != null && player.IsConnected)
                {
                    RenderNavigation(player, view);
                    RenderHeader(player, view);
                    RenderContent(player, view);
                }
            }
        }

        private object API_Open(BasePlayer player, string tab = "calendar")
        {
            if (player == null || !CanUse(player)) return false;
            OpenMenu(player, tab, false);
            return true;
        }

        private object API_Close(BasePlayer player)
        {
            if (player == null) return false;
            CloseMenu(player);
            return true;
        }

        private object API_IsTabActive(BasePlayer player, string tab)
        {
            PlayerView view;
            return player != null && views.TryGetValue(player.userID, out view) && view.Open &&
                   string.Equals(view.ActiveTab, NormalizeKey(tab), StringComparison.OrdinalIgnoreCase);
        }

        private object API_RefreshTab(BasePlayer player)
        {
            PlayerView view;
            if (player == null || !views.TryGetValue(player.userID, out view) || !view.Open) return false;
            RenderContent(player, view);
            return true;
        }

        private object API_ShowToast(BasePlayer player, string message, string kind = "info")
        {
            if (player == null || string.IsNullOrWhiteSpace(message)) return false;
            ShowToast(player, message, kind);
            return true;
        }

        private object API_UpdateBalance(BasePlayer player, int balance)
        {
            PlayerView view;
            if (player == null || !views.TryGetValue(player.userID, out view) || !view.Open ||
                view.Snapshot == null || view.Snapshot.Player == null)
                return false;

            view.Snapshot.Player.Balance = Math.Max(0, balance);
            RenderNavigation(player, view);
            return true;
        }

        private void ChatOpenMenu(BasePlayer player, string command, string[] args)
        {
            if (!CanUse(player)) return;
            OpenMenu(player, args != null && args.Length > 0 ? args[0] : "calendar", false);
        }

        [ConsoleCommand("prostojmenu.ui")]
        private void ConsoleMenu(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || arg.Connection == null || !CanUse(player) || arg.Args == null || arg.Args.Length == 0)
                return;

            PlayerView view;
            views.TryGetValue(player.userID, out view);
            switch ((arg.GetString(0) ?? string.Empty).ToLowerInvariant())
            {
                case "close":
                    CloseMenu(player);
                    return;
                case "open":
                    OpenMenu(player, arg.Args.Length > 1 ? arg.Args[1].ToString() : "calendar", false);
                    return;
                case "tab":
                    if (view == null || !view.Open || arg.Args.Length < 2) return;
                    var key = NormalizeKey(arg.Args[1].ToString());
                    MenuTab selectedTab;
                    if (!tabs.TryGetValue(key, out selectedTab) || !CanViewTab(player, selectedTab)) return;
                    view.ActiveTab = key;
                    view.Page = 0;
                    RenderNavigation(player, view);
                    RenderHeader(player, view);
                    RenderContent(player, view);
                    if (string.Equals(key, "support", StringComparison.OrdinalIgnoreCase))
                        FetchSupport(player, view, false);
                    if (string.Equals(key, "battlepass", StringComparison.OrdinalIgnoreCase))
                        FetchBattlePass(player, view, false);
                    if (string.Equals(key, "calendar", StringComparison.OrdinalIgnoreCase))
                        FetchCalendarMonth(player, view, false);
                    return;
                case "refresh":
                    if (view == null || !view.Open) return;
                    if (!BeginManualRefresh(view)) return;
                    if (string.Equals(view.ActiveTab, "battlepass", StringComparison.OrdinalIgnoreCase))
                        FetchBattlePass(player, view, true);
                    else if (string.Equals(view.ActiveTab, "calendar", StringComparison.OrdinalIgnoreCase))
                    {
                        FetchSnapshot(player, view, true);
                        FetchCalendarMonth(player, view, true);
                    }
                    else
                        FetchSnapshot(player, view, true);
                    return;
                case "battlepass_prev":
                    if (!CanUseBattlePassCommand(view)) return;
                    view.BattlePassPage = Math.Max(0, view.BattlePassPage - 1);
                    RenderContent(player, view);
                    return;
                case "battlepass_next":
                    if (!CanUseBattlePassCommand(view)) return;
                    var taskCount = GetBattlePassTasks(view.BattlePass).Count;
                    var lastPage = Math.Max(0, (taskCount - 1) / 6);
                    view.BattlePassPage = Math.Min(lastPage, view.BattlePassPage + 1);
                    RenderContent(player, view);
                    return;
                case "battlepass_check":
                    if (!CanUseBattlePassCommand(view) || view.BattlePassChecking || arg.Args.Length < 2) return;
                    int battlePassTaskId;
                    if (!int.TryParse(arg.Args[1].ToString(), NumberStyles.Integer, CultureInfo.InvariantCulture, out battlePassTaskId) || battlePassTaskId <= 0) return;
                    CheckBattlePassTask(player, view, battlePassTaskId);
                    return;
                case "calendar_prev":
                    if (view == null || !view.Open || !string.Equals(view.ActiveTab, "calendar", StringComparison.OrdinalIgnoreCase)) return;
                    view.Page = Mathf.Clamp(view.Page - 1, -24, 12);
                    FetchCalendarMonth(player, view, false);
                    return;
                case "calendar_next":
                    if (view == null || !view.Open || !string.Equals(view.ActiveTab, "calendar", StringComparison.OrdinalIgnoreCase)) return;
                    view.Page = Mathf.Clamp(view.Page + 1, -24, 12);
                    FetchCalendarMonth(player, view, false);
                    return;
                case "support_refresh":
                    if (!CanUseSupportCommand(view) || view.SupportClosing) return;
                    if (!BeginManualRefresh(view)) return;
                    view.SupportComposeNew = false;
                    FetchSupport(player, view, true);
                    return;
                case "support_ticket":
                    if (!CanUseSupportCommand(view) || view.SupportClosing || arg.Args.Length < 2) return;
                    long ticketNumber;
                    if (!long.TryParse(arg.Args[1].ToString(), NumberStyles.Integer, CultureInfo.InvariantCulture, out ticketNumber) || ticketNumber <= 0) return;
                    view.SupportSelectedTicket = ticketNumber;
                    view.SupportComposeNew = false;
                    FetchSupport(player, view, true);
                    return;
                case "support_new":
                    if (!CanUseSupportCommand(view) || view.SupportClosing) return;
                    var existingOpenTicket = view.Support != null && view.Support.Tickets != null
                        ? view.Support.Tickets.FirstOrDefault(ticket => string.Equals(ticket.Status, "open", StringComparison.OrdinalIgnoreCase))
                        : null;
                    if (existingOpenTicket != null)
                    {
                        view.SupportSelectedTicket = existingOpenTicket.Number;
                        view.SupportComposeNew = false;
                        FetchSupport(player, view, true);
                        return;
                    }
                    view.SupportSelectedTicket = null;
                    view.SupportComposeNew = true;
                    view.SupportDraft = string.Empty;
                    view.SupportError = null;
                    RenderContent(player, view);
                    return;
                case "support_input":
                    if (!CanUseSupportCommand(view) || view.SupportSending || view.SupportClosing) return;
                    var input = arg.Args.Length > 1
                        ? string.Join(" ", arg.Args.Skip(1).Select(item => item.ToString()).ToArray())
                        : string.Empty;
                    view.SupportDraft = input.Length > 500 ? input.Substring(0, 500) : input;
                    return;
                case "support_send":
                    if (!CanUseSupportCommand(view)) return;
                    SendSupportMessage(player, view);
                    return;
                case "support_close":
                    if (!CanUseSupportCommand(view)) return;
                    CloseSupportTicket(player, view);
                    return;
            }
        }

        private static bool CanUseSupportCommand(PlayerView view)
        {
            return view != null && view.Open && string.Equals(view.ActiveTab, "support", StringComparison.OrdinalIgnoreCase);
        }

        private static bool CanUseBattlePassCommand(PlayerView view)
        {
            return view != null && view.Open && string.Equals(view.ActiveTab, "battlepass", StringComparison.OrdinalIgnoreCase);
        }

        private static bool BeginManualRefresh(PlayerView view)
        {
            if (view == null || view.NextManualRefreshAtUtc > DateTime.UtcNow) return false;
            view.NextManualRefreshAtUtc = DateTime.UtcNow.AddSeconds(2);
            return true;
        }

        private bool CanUse(BasePlayer player)
        {
            if (!settings.RequirePermission || permission.UserHasPermission(player.UserIDString, PermissionUse))
                return true;
            player.ChatMessage("У вас нет доступа к меню.");
            return false;
        }

        private void OpenMenu(BasePlayer player, string requestedTab, bool forceRefresh)
        {
            // A real player can only open the menu after Rust has completed
            // world/save initialization, so FileStorage is safe from here on.
            StartImageCache();

            var key = NormalizeKey(requestedTab);
            MenuTab requested;
            if (!tabs.TryGetValue(key, out requested) || !CanViewTab(player, requested)) key = "calendar";

            PlayerView view;
            if (!views.TryGetValue(player.userID, out view))
            {
                view = new PlayerView();
                views[player.userID] = view;
            }

            view.Open = true;
            view.ActiveTab = key;
            view.Page = 0;
            DrawShell(player, view);

            var stale = view.Snapshot == null || (DateTime.UtcNow - view.LoadedAtUtc).TotalSeconds >= settings.CacheSeconds;
            if (forceRefresh || stale)
                FetchSnapshot(player, view, forceRefresh);
            if (string.Equals(key, "support", StringComparison.OrdinalIgnoreCase))
                FetchSupport(player, view, forceRefresh);
            if (string.Equals(key, "battlepass", StringComparison.OrdinalIgnoreCase))
                FetchBattlePass(player, view, forceRefresh);
            if (string.Equals(key, "calendar", StringComparison.OrdinalIgnoreCase) && view.Snapshot != null)
                FetchCalendarMonth(player, view, forceRefresh);
        }

        private void CloseMenu(BasePlayer player)
        {
            PlayerView view;
            if (views.TryGetValue(player.userID, out view)) view.Open = false;
            CuiHelper.DestroyUi(player, Root);
        }

        private void QueueSnapshotPrefetch(BasePlayer player)
        {
            if (!settings.PrefetchOnlinePlayers || player == null || !player.IsConnected) return;
            if (settings.RequirePermission && !permission.UserHasPermission(player.UserIDString, PermissionUse)) return;

            PlayerView view;
            if (views.TryGetValue(player.userID, out view))
            {
                if (view.Open || view.Loading) return;
                if (view.Snapshot != null &&
                    (DateTime.UtcNow - view.LoadedAtUtc).TotalSeconds < settings.CacheSeconds)
                    return;
            }
            if (!queuedSnapshotPrefetches.Add(player.userID)) return;
            pendingSnapshotPrefetches.Enqueue(player.userID);
        }

        private void PumpSnapshotPrefetches()
        {
            if (!settings.PrefetchOnlinePlayers || imageCacheUnloading) return;
            while (activeSnapshotPrefetches < settings.MaxConcurrentPrefetches && pendingSnapshotPrefetches.Count > 0)
            {
                var userId = pendingSnapshotPrefetches.Dequeue();
                queuedSnapshotPrefetches.Remove(userId);
                var player = BasePlayer.FindByID(userId);
                if (player == null || !player.IsConnected) continue;

                PlayerView view;
                if (!views.TryGetValue(userId, out view))
                {
                    view = new PlayerView();
                    views[userId] = view;
                }
                if (view.Open || view.Loading || (view.Snapshot != null &&
                    (DateTime.UtcNow - view.LoadedAtUtc).TotalSeconds < settings.CacheSeconds))
                    continue;

                FetchSnapshot(player, view, false, true);
            }
        }

        private void FetchSnapshot(BasePlayer player, PlayerView view, bool force, bool silent = false)
        {
            if (view.Loading) return;
            if (string.IsNullOrWhiteSpace(settings.ApiUrl))
            {
                view.Error = "API меню не настроен";
                if (!silent) RenderContent(player, view);
                return;
            }

            view.Loading = true;
            view.Error = null;
            var requestVersion = ++view.RequestVersion;
            if (silent)
                activeSnapshotPrefetches++;
            else
            {
                RenderHeader(player, view);
                RenderContent(player, view);
            }

            var query = "steam_id=" + Uri.EscapeDataString(player.UserIDString);
            if (!string.IsNullOrEmpty(settings.ServerTag))
                query += "&server_tag=" + Uri.EscapeDataString(settings.ServerTag);
            else
                query += "&server_ip=" + Uri.EscapeDataString(ConVar.Server.ip ?? string.Empty) +
                         "&server_port=" + ConVar.Server.port.ToString(CultureInfo.InvariantCulture);
            var apiUrl = settings.ApiUrl.Trim();
            var separator = apiUrl.EndsWith("?", StringComparison.Ordinal) || apiUrl.EndsWith("&", StringComparison.Ordinal)
                ? string.Empty
                : apiUrl.Contains("?") ? "&" : "?";
            var url = apiUrl + separator + query;
            var headers = new Dictionary<string, string>
            {
                ["Accept"] = "application/json",
                ["X-Rust-Server-Secret"] = settings.ServerSecret ?? string.Empty
            };

            webrequest.Enqueue(url, null, (code, response) =>
            {
                if (silent && activeSnapshotPrefetches > 0)
                    activeSnapshotPrefetches--;
                PlayerView current;
                if (!views.TryGetValue(player.userID, out current) || current.RequestVersion != requestVersion)
                    return;

                current.Loading = false;
                if (code != 200 || string.IsNullOrWhiteSpace(response))
                {
                    current.Error = "Данные временно недоступны";
                }
                else
                {
                    try
                    {
                        var envelope = JsonConvert.DeserializeObject<ApiEnvelope>(response);
                        if (envelope == null || !envelope.Success || envelope.Data == null)
                            current.Error = envelope != null && envelope.Error != null ? envelope.Error.Message : "Некорректный ответ API";
                        else
                        {
                            current.Snapshot = envelope.Data;
                            current.LoadedAtUtc = DateTime.UtcNow;
                            current.Error = null;
                        }
                    }
                    catch (Exception exception)
                    {
                        current.Error = "Ошибка чтения данных";
                        PrintWarning("Snapshot parse failed: " + exception.Message);
                    }
                }

                if (current.Open && player.IsConnected)
                {
                    RenderHeader(player, current);
                    RenderNavigation(player, current);
                    RenderContent(player, current);
                    if (string.Equals(current.ActiveTab, "calendar", StringComparison.OrdinalIgnoreCase))
                        FetchCalendarMonth(player, current, false);
                }
            }, this, RequestMethod.GET, headers);
        }

        private void FetchCalendarMonth(BasePlayer player, PlayerView view, bool force)
        {
            if (view == null) return;
            var snapshot = view != null ? view.Snapshot : null;
            var server = snapshot != null ? snapshot.Server : null;
            if (server == null || server.Id <= 0)
            {
                RenderContent(player, view);
                return;
            }

            var month = SelectedCalendarMonth(view);
            var monthKey = server.Id.ToString(CultureInfo.InvariantCulture) + "/" + month.ToString("yyyy-MM", CultureInfo.InvariantCulture);
            CachedCalendarMonth cached;
            if (!force && cachedCalendarMonths.TryGetValue(monthKey, out cached) && cached.Data != null &&
                (DateTime.UtcNow - cached.LoadedAtUtc).TotalMinutes < 10d)
            {
                view.CalendarMonthKey = monthKey;
                view.CalendarMonth = cached.Data;
                view.CalendarLoading = false;
                view.CalendarRequestedMonthKey = null;
                view.CalendarError = null;
                RenderContent(player, view);
                return;
            }

            if (view.CalendarLoading &&
                string.Equals(view.CalendarRequestedMonthKey, monthKey, StringComparison.Ordinal)) return;
            if (string.IsNullOrWhiteSpace(settings.CalendarApiUrl))
            {
                view.CalendarError = "API календаря не настроен";
                RenderContent(player, view);
                return;
            }

            view.CalendarLoading = true;
            view.CalendarRequestedMonthKey = monthKey;
            view.CalendarError = null;
            var requestVersion = ++view.CalendarRequestVersion;
            RenderContent(player, view);
            var url = settings.CalendarApiUrl.Trim() + (settings.CalendarApiUrl.Contains("?") ? "&" : "?") +
                      "server_id=" + server.Id.ToString(CultureInfo.InvariantCulture) +
                      "&year=" + month.Year.ToString(CultureInfo.InvariantCulture) +
                      "&month=" + month.Month.ToString(CultureInfo.InvariantCulture) + "&months=1";

            webrequest.Enqueue(url, null, (code, response) =>
            {
                PlayerView current;
                if (!views.TryGetValue(player.userID, out current) || current.CalendarRequestVersion != requestVersion)
                    return;

                current.CalendarLoading = false;
                current.CalendarRequestedMonthKey = null;
                if (code != 200 || string.IsNullOrWhiteSpace(response))
                {
                    current.CalendarError = "Календарь временно недоступен";
                }
                else
                {
                    try
                    {
                        var envelope = JsonConvert.DeserializeObject<CalendarApiEnvelope>(response);
                        if (envelope == null || !envelope.Success || envelope.Data == null)
                            current.CalendarError = envelope != null && envelope.Error != null
                                ? envelope.Error.Message
                                : "Некорректный ответ API календаря";
                        else
                        {
                            current.CalendarMonthKey = monthKey;
                            current.CalendarMonth = envelope.Data;
                            current.CalendarError = null;
                            cachedCalendarMonths[monthKey] = new CachedCalendarMonth
                            {
                                Data = envelope.Data,
                                LoadedAtUtc = DateTime.UtcNow
                            };
                            PruneCalendarCache();
                        }
                    }
                    catch (Exception exception)
                    {
                        current.CalendarError = "Ошибка чтения календаря";
                        PrintWarning("Calendar parse failed: " + exception.Message);
                    }
                }

                if (current.Open && player.IsConnected &&
                    string.Equals(current.ActiveTab, "calendar", StringComparison.OrdinalIgnoreCase))
                    RenderContent(player, current);
            }, this, RequestMethod.GET, new Dictionary<string, string> { ["Accept"] = "application/json" });
        }

        private static DateTime SelectedCalendarMonth(PlayerView view)
        {
            var now = DateTime.Now;
            return new DateTime(now.Year, now.Month, 1).AddMonths(view != null ? view.Page : 0);
        }

        private void PruneCalendarCache()
        {
            if (cachedCalendarMonths.Count <= 48) return;
            var staleKeys = cachedCalendarMonths
                .OrderBy(pair => pair.Value != null ? pair.Value.LoadedAtUtc : DateTime.MinValue)
                .Take(cachedCalendarMonths.Count - 48)
                .Select(pair => pair.Key)
                .ToList();
            foreach (var key in staleKeys) cachedCalendarMonths.Remove(key);
        }

        private void FetchBattlePass(BasePlayer player, PlayerView view, bool force)
        {
            if (view.BattlePassLoading) return;
            if (!force && view.BattlePass != null &&
                (DateTime.UtcNow - view.BattlePassLoadedAtUtc).TotalSeconds < settings.CacheSeconds)
            {
                RenderContent(player, view);
                return;
            }
            if (string.IsNullOrWhiteSpace(settings.BattlePassApiUrl))
            {
                view.BattlePassError = "API Battle Pass не настроен";
                RenderContent(player, view);
                return;
            }

            view.BattlePassLoading = true;
            view.BattlePassError = null;
            var requestVersion = ++view.BattlePassRequestVersion;
            RenderContent(player, view);
            var url = BuildPlayerApiUrl(settings.BattlePassApiUrl, player);

            webrequest.Enqueue(url, null, (code, response) =>
            {
                PlayerView current;
                if (!views.TryGetValue(player.userID, out current) || current.BattlePassRequestVersion != requestVersion)
                    return;

                current.BattlePassLoading = false;
                if (code != 200 || string.IsNullOrWhiteSpace(response))
                {
                    current.BattlePassError = "Battle Pass временно недоступен";
                }
                else
                {
                    try
                    {
                        var envelope = JsonConvert.DeserializeObject<BattlePassApiEnvelope>(response);
                        if (envelope == null || !envelope.Success || envelope.Data == null)
                            current.BattlePassError = envelope != null && envelope.Error != null
                                ? envelope.Error.Message
                                : "Некорректный ответ Battle Pass API";
                        else
                        {
                            current.BattlePass = envelope.Data;
                            current.BattlePassLoadedAtUtc = DateTime.UtcNow;
                            current.BattlePassError = null;
                            FocusCurrentBattlePassPage(current);
                        }
                    }
                    catch (Exception exception)
                    {
                        current.BattlePassError = "Ошибка чтения Battle Pass";
                        PrintWarning("Battle Pass parse failed: " + exception.Message);
                    }
                }

                if (current.Open && player.IsConnected &&
                    string.Equals(current.ActiveTab, "battlepass", StringComparison.OrdinalIgnoreCase))
                    RenderContent(player, current);
            }, this, RequestMethod.GET, BuildApiHeaders(false));
        }

        private void CheckBattlePassTask(BasePlayer player, PlayerView view, int taskId)
        {
            if (view.BattlePassChecking || string.IsNullOrWhiteSpace(settings.BattlePassApiUrl)) return;
            view.BattlePassChecking = true;
            RenderContent(player, view);
            var endpoint = settings.BattlePassApiUrl.Trim().TrimEnd('/') + "/tasks/" +
                           taskId.ToString(CultureInfo.InvariantCulture) + "/check";
            var url = BuildPlayerApiUrl(endpoint, player);

            webrequest.Enqueue(url, "{}", (code, response) =>
            {
                PlayerView current;
                if (!views.TryGetValue(player.userID, out current)) return;
                current.BattlePassChecking = false;

                if (code != 200 || string.IsNullOrWhiteSpace(response))
                {
                    current.BattlePassError = ReadApiError(response, "Не удалось проверить задание");
                    if (current.Open && player.IsConnected && string.Equals(current.ActiveTab, "battlepass", StringComparison.OrdinalIgnoreCase))
                    {
                        RenderContent(player, current);
                        ShowToast(player, current.BattlePassError, "error");
                    }
                    return;
                }

                try
                {
                    var envelope = JsonConvert.DeserializeObject<BattlePassCheckEnvelope>(response);
                    if (envelope == null || !envelope.Success || envelope.Data == null)
                    {
                        var message = envelope != null && envelope.Error != null
                            ? envelope.Error.Message
                            : "Некорректный ответ проверки задания";
                        current.BattlePassError = message;
                        if (current.Open && player.IsConnected)
                        {
                            RenderContent(player, current);
                            ShowToast(player, message, "error");
                        }
                        return;
                    }

                    current.BattlePassError = null;
                    if (current.Open && player.IsConnected)
                        ShowToast(player, Safe(envelope.Data.Message), envelope.Data.Success ? "success" : "info");
                    current.BattlePassLoadedAtUtc = DateTime.MinValue;
                    FetchBattlePass(player, current, true);
                }
                catch (Exception exception)
                {
                    current.BattlePassError = "Ошибка чтения результата задания";
                    PrintWarning("Battle Pass check parse failed: " + exception.Message);
                    if (current.Open && player.IsConnected)
                    {
                        RenderContent(player, current);
                        ShowToast(player, current.BattlePassError, "error");
                    }
                }
            }, this, RequestMethod.POST, BuildApiHeaders(true));
        }

        private string BuildPlayerApiUrl(string endpoint, BasePlayer player)
        {
            endpoint = (endpoint ?? string.Empty).Trim();
            var query = "steam_id=" + Uri.EscapeDataString(player.UserIDString);
            if (!string.IsNullOrEmpty(settings.ServerTag))
                query += "&server_tag=" + Uri.EscapeDataString(settings.ServerTag);
            else
                query += "&server_ip=" + Uri.EscapeDataString(ConVar.Server.ip ?? string.Empty) +
                         "&server_port=" + ConVar.Server.port.ToString(CultureInfo.InvariantCulture);
            return endpoint + (endpoint.Contains("?") ? "&" : "?") + query;
        }

        private static void FocusCurrentBattlePassPage(PlayerView view)
        {
            if (view == null || view.BattlePass == null || view.BattlePass.Progress == null ||
                !view.BattlePass.Progress.CurrentTaskId.HasValue)
                return;
            var tasks = GetBattlePassTasks(view.BattlePass);
            var index = tasks.FindIndex(item => item.Id == view.BattlePass.Progress.CurrentTaskId.Value);
            if (index >= 0) view.BattlePassPage = index / 6;
        }

        private static List<BattlePassTaskData> GetBattlePassTasks(BattlePassData battlePass)
        {
            if (battlePass == null) return new List<BattlePassTaskData>();
            return (battlePass.FreeTasks ?? new List<BattlePassTaskData>())
                .Concat(battlePass.VipTasks ?? new List<BattlePassTaskData>())
                .OrderBy(item => item.Position > 0 ? item.Position : item.Sort)
                .ToList();
        }

        private void PollSupportViews()
        {
            var now = DateTime.UtcNow;
            foreach (var entry in views.ToList())
            {
                var view = entry.Value;
                if (!CanUseSupportCommand(view) || view.SupportLoading || view.SupportSending || view.SupportClosing || view.SupportComposeNew || !string.IsNullOrWhiteSpace(view.SupportDraft))
                    continue;
                if (view.SupportNextPollAtUtc > now)
                    continue;
                var player = BasePlayer.FindByID(entry.Key);
                if (player != null && player.IsConnected)
                    FetchSupport(player, view, false, true);
            }
        }

        private void FetchSupport(BasePlayer player, PlayerView view, bool force, bool silent = false)
        {
            if (view.SupportSending || view.SupportClosing) return;
            // Background polls never overlap. An explicit ticket switch may replace
            // an older read request; its version is invalidated when this one starts.
            if (view.SupportLoading && !force) return;
            if (view.SupportLoading) view.SupportLoading = false;
            if (string.IsNullOrWhiteSpace(settings.SupportApiUrl))
            {
                view.SupportError = "API поддержки не настроен";
                RenderContent(player, view);
                return;
            }

            view.SupportLoading = true;
            view.SupportError = null;
            var requestVersion = ++view.SupportRequestVersion;
            ScheduleNextSupportPoll(view, player.userID);
            if (!silent || view.Support == null)
                RenderContent(player, view);
            var knownRevision = silent && !force && view.Support != null ? view.Support.Revision : null;
            var url = BuildSupportUrl(player, false, view.SupportSelectedTicket, knownRevision);
            var headers = BuildApiHeaders(false);

            webrequest.Enqueue(url, null, (code, response) =>
            {
                PlayerView current;
                if (!views.TryGetValue(player.userID, out current) || current.SupportRequestVersion != requestVersion)
                    return;

                current.SupportLoading = false;
                ScheduleNextSupportPoll(current, player.userID);
                var shouldRender = !silent || current.Support == null;
                if (code != 200 || string.IsNullOrWhiteSpace(response))
                {
                    if (!silent || current.Support == null)
                        current.SupportError = ReadApiError(response, "Поддержка временно недоступна");
                }
                else
                {
                    try
                    {
                        var envelope = JsonConvert.DeserializeObject<SupportApiEnvelope>(response);
                        if (envelope == null || !envelope.Success || envelope.Data == null)
                        {
                            if (!silent || current.Support == null)
                                current.SupportError = envelope != null && envelope.Error != null ? envelope.Error.Message : "Некорректный ответ поддержки";
                        }
                        else if (envelope.Data.Unchanged && current.Support != null)
                        {
                            current.Support.Revision = envelope.Data.Revision;
                            current.Support.Registered = envelope.Data.Registered;
                            current.Support.CanWrite = envelope.Data.CanWrite;
                            current.Support.Notice = envelope.Data.Notice;
                            current.Support.UnreadCount = envelope.Data.UnreadCount;
                            current.Support.UnreadCountCapped = envelope.Data.UnreadCountCapped;
                            if (current.Snapshot != null && current.Snapshot.Support != null)
                                current.Snapshot.Support.UnreadCount = envelope.Data.UnreadCount;
                            current.SupportError = null;
                        }
                        else
                        {
                            current.Support = envelope.Data;
                            current.SupportSelectedTicket = envelope.Data.ActiveTicket != null ? (long?) envelope.Data.ActiveTicket.Number : null;
                            if (current.Snapshot != null && current.Snapshot.Support != null)
                                current.Snapshot.Support.UnreadCount = envelope.Data.UnreadCount;
                            current.SupportError = null;
                            shouldRender = true;
                        }
                    }
                    catch (Exception exception)
                    {
                        if (!silent || current.Support == null)
                            current.SupportError = "Ошибка чтения сообщений";
                        PrintWarning("Support parse failed: " + exception.Message);
                    }
                }

                if (shouldRender && current.Open && player.IsConnected && string.Equals(current.ActiveTab, "support", StringComparison.OrdinalIgnoreCase))
                    RenderContent(player, current);
            }, this, RequestMethod.GET, headers);
        }

        private static void ScheduleNextSupportPoll(PlayerView view, ulong userId)
        {
            var spreadSeconds = 20 + (int) ((userId + (ulong) view.SupportRequestVersion) % 11UL);
            view.SupportNextPollAtUtc = DateTime.UtcNow.AddSeconds(spreadSeconds);
        }

        private void SendSupportMessage(BasePlayer player, PlayerView view)
        {
            if (view.SupportSending || view.SupportClosing) return;
            // Sending has priority over an in-flight silent poll. The request version
            // below makes the obsolete GET callback a no-op.
            if (view.SupportLoading) view.SupportLoading = false;
            var message = (view.SupportDraft ?? string.Empty).Trim();
            if (string.IsNullOrEmpty(message))
            {
                ShowToast(player, "Введите сообщение", "error");
                return;
            }
            if (message.Length > 500)
            {
                ShowToast(player, "Максимум 500 символов", "error");
                return;
            }
            if (view.Support != null && (!view.Support.Registered || !view.Support.CanWrite))
            {
                ShowToast(player, Safe(view.Support.Notice), "error");
                return;
            }

            var requestVersion = ++view.SupportRequestVersion;
            view.SupportSending = true;
            view.SupportError = null;
            RenderContent(player, view);

            var ticketNumber = !view.SupportComposeNew && view.Support != null && view.Support.ActiveTicket != null
                ? (long?) view.Support.ActiveTicket.Number
                : null;
            var body = JsonConvert.SerializeObject(new
            {
                message,
                ticket_number = ticketNumber,
                new_ticket = view.SupportComposeNew
            });
            var url = BuildSupportUrl(player, true, null, null);
            var headers = BuildApiHeaders(true);

            webrequest.Enqueue(url, body, (code, response) =>
            {
                PlayerView current;
                if (!views.TryGetValue(player.userID, out current) || current.SupportRequestVersion != requestVersion)
                    return;

                current.SupportSending = false;
                ScheduleNextSupportPoll(current, player.userID);
                if ((code != 200 && code != 201) || string.IsNullOrWhiteSpace(response))
                {
                    current.SupportError = ReadApiError(response, "Не удалось отправить сообщение");
                    if (current.Open && player.IsConnected && string.Equals(current.ActiveTab, "support", StringComparison.OrdinalIgnoreCase))
                    {
                        RenderContent(player, current);
                        ShowToast(player, current.SupportError, "error");
                    }
                    return;
                }

                try
                {
                    var envelope = JsonConvert.DeserializeObject<SupportApiEnvelope>(response);
                    if (envelope == null || !envelope.Success || envelope.Data == null)
                        throw new Exception(envelope != null && envelope.Error != null ? envelope.Error.Message : "Некорректный ответ API");
                    current.Support = envelope.Data;
                    current.SupportSelectedTicket = envelope.Data.ActiveTicket != null ? (long?) envelope.Data.ActiveTicket.Number : null;
                    if (current.Snapshot != null && current.Snapshot.Support != null)
                        current.Snapshot.Support.UnreadCount = envelope.Data.UnreadCount;
                    current.SupportComposeNew = false;
                    current.SupportDraft = string.Empty;
                    current.SupportError = null;
                    if (current.Open && player.IsConnected && string.Equals(current.ActiveTab, "support", StringComparison.OrdinalIgnoreCase))
                    {
                        RenderContent(player, current);
                        ShowToast(player, "Сообщение отправлено", "success");
                    }
                }
                catch (Exception exception)
                {
                    current.SupportError = exception.Message;
                    PrintWarning("Support send parse failed: " + exception.Message);
                    if (current.Open && player.IsConnected && string.Equals(current.ActiveTab, "support", StringComparison.OrdinalIgnoreCase))
                        RenderContent(player, current);
                }
            }, this, RequestMethod.POST, headers);
        }

        private void CloseSupportTicket(BasePlayer player, PlayerView view)
        {
            if (view.SupportClosing || view.SupportSending || view.SupportLoading || view.Support == null || view.Support.ActiveTicket == null)
                return;

            var ticket = view.Support.ActiveTicket;
            if (!string.Equals(ticket.Status, "open", StringComparison.OrdinalIgnoreCase))
            {
                ShowToast(player, "Обращение уже закрыто", "error");
                return;
            }

            view.SupportClosing = true;
            view.SupportError = null;
            var requestVersion = ++view.SupportRequestVersion;
            RenderContent(player, view);

            var endpoint = (settings.SupportApiUrl ?? string.Empty).Trim().TrimEnd('/') + "/tickets/" +
                           ticket.Number.ToString(CultureInfo.InvariantCulture) + "/close";
            var url = BuildPlayerApiUrl(endpoint, player);
            webrequest.Enqueue(url, "{}", (code, response) =>
            {
                PlayerView current;
                if (!views.TryGetValue(player.userID, out current) || current.SupportRequestVersion != requestVersion)
                    return;

                current.SupportClosing = false;
                ScheduleNextSupportPoll(current, player.userID);
                if (code != 200 || string.IsNullOrWhiteSpace(response))
                {
                    current.SupportError = ReadApiError(response, "Не удалось закрыть обращение");
                    if (current.Open && player.IsConnected && string.Equals(current.ActiveTab, "support", StringComparison.OrdinalIgnoreCase))
                    {
                        RenderContent(player, current);
                        ShowToast(player, current.SupportError, "error");
                    }
                    return;
                }

                try
                {
                    var envelope = JsonConvert.DeserializeObject<SupportApiEnvelope>(response);
                    if (envelope == null || !envelope.Success || envelope.Data == null)
                        throw new Exception(envelope != null && envelope.Error != null ? envelope.Error.Message : "Некорректный ответ API");

                    current.Support = envelope.Data;
                    current.SupportSelectedTicket = envelope.Data.ActiveTicket != null ? (long?) envelope.Data.ActiveTicket.Number : null;
                    current.SupportComposeNew = false;
                    current.SupportDraft = string.Empty;
                    current.SupportError = null;
                    if (current.Snapshot != null && current.Snapshot.Support != null)
                        current.Snapshot.Support.UnreadCount = envelope.Data.UnreadCount;
                    if (current.Open && player.IsConnected && string.Equals(current.ActiveTab, "support", StringComparison.OrdinalIgnoreCase))
                    {
                        RenderContent(player, current);
                        ShowToast(player, "Обращение закрыто", "success");
                    }
                }
                catch (Exception exception)
                {
                    current.SupportError = exception.Message;
                    PrintWarning("Support close parse failed: " + exception.Message);
                    if (current.Open && player.IsConnected && string.Equals(current.ActiveTab, "support", StringComparison.OrdinalIgnoreCase))
                        RenderContent(player, current);
                }
            }, this, RequestMethod.POST, BuildApiHeaders(true));
        }

        private string BuildSupportUrl(BasePlayer player, bool messagesEndpoint, long? ticketNumber, string knownRevision)
        {
            var endpoint = (settings.SupportApiUrl ?? string.Empty).Trim().TrimEnd('/');
            if (messagesEndpoint) endpoint += "/messages";
            var query = "steam_id=" + Uri.EscapeDataString(player.UserIDString);
            if (!string.IsNullOrEmpty(settings.ServerTag))
                query += "&server_tag=" + Uri.EscapeDataString(settings.ServerTag);
            else
                query += "&server_ip=" + Uri.EscapeDataString(ConVar.Server.ip ?? string.Empty) +
                         "&server_port=" + ConVar.Server.port.ToString(CultureInfo.InvariantCulture);
            if (ticketNumber.HasValue)
                query += "&ticket_number=" + ticketNumber.Value.ToString(CultureInfo.InvariantCulture);
            if (!string.IsNullOrWhiteSpace(knownRevision))
                query += "&known_revision=" + Uri.EscapeDataString(knownRevision);
            return endpoint + (endpoint.Contains("?") ? "&" : "?") + query;
        }

        private Dictionary<string, string> BuildApiHeaders(bool json)
        {
            var headers = new Dictionary<string, string>
            {
                ["Accept"] = "application/json",
                ["X-Rust-Server-Secret"] = settings.ServerSecret ?? string.Empty
            };
            if (json) headers["Content-Type"] = "application/json";
            return headers;
        }

        private static string ReadApiError(string response, string fallback)
        {
            if (string.IsNullOrWhiteSpace(response)) return fallback;
            try
            {
                var envelope = JsonConvert.DeserializeObject<SupportApiEnvelope>(response);
                return envelope != null && envelope.Error != null && !string.IsNullOrWhiteSpace(envelope.Error.Message)
                    ? envelope.Error.Message
                    : fallback;
            }
            catch
            {
                return fallback;
            }
        }

        private void DrawShell(BasePlayer player, PlayerView view)
        {
            CuiHelper.DestroyUi(player, Root);
            var ui = new CuiElementContainer();

            ui.Add(new CuiPanel
            {
                CursorEnabled = true,
                KeyboardEnabled = true,
                Image = { Color = ThemeColor(Scrim), Material = "assets/content/ui/uibackgroundblur.mat" },
                RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1" }
            }, "Overlay", Root, Root);

            AddPanel(ui, Root, Frame, "0.095 0.09", "0.905 0.91", "0 0 0 0");
            AddPanel(ui, Frame, Sidebar, "0 0", F(SidebarWidth, 1f), BgSecondary);
            // A narrow transparent gutter separates navigation from content,
            // matching the lightweight Performance-style shell.
            AddPanel(ui, Frame, Main, F(SidebarWidth + SidebarContentGap, 0f), "1 1", BgMain);
            // Keep the header inside the frame's top and right borders. Anchoring it
            // at exactly 1 1 could round a few pixels beyond the window in Rust CUI.
            AddPanel(ui, Main, Header, "0 0.895", "0.998 0.998", BgSecondary);
            AddPanel(ui, Main, Content, "0.018 0.02", "0.982 0.875", "0 0 0 0");

            AddPanel(ui, Sidebar, Navigation, "0 0", "1 1", "0 0 0 0");
            AddNavigation(ui, player, view);
            CuiHelper.AddUi(player, ui);

            RenderHeader(player, view);
            RenderContent(player, view);
        }

        private void RenderNavigation(BasePlayer player, PlayerView view)
        {
            CuiHelper.DestroyUi(player, Navigation);
            var ui = new CuiElementContainer();
            AddPanel(ui, Sidebar, Navigation, "0 0", "1 1", "0 0 0 0");
            AddNavigation(ui, player, view);
            CuiHelper.AddUi(player, ui);
        }

        private void AddNavigation(CuiElementContainer ui, BasePlayer player, PlayerView view)
        {
            var ordered = tabs.Values.Where(tab => CanViewTab(player, tab)).OrderBy(tab => tab.Order).ThenBy(tab => tab.Title).ToList();
            const float top = 0.985f;
            const float bottom = 0.235f;
            var gap = 0.0015f;
            var preferredHeight = ordered.Count > 8 ? 0.057f : 0.063f;
            var availableHeight = top - bottom - gap * Math.Max(0, ordered.Count - 1);
            var height = ordered.Count > 0 ? Math.Min(preferredHeight, availableHeight / ordered.Count) : preferredHeight;
            for (var index = 0; index < ordered.Count; index++)
            {
                var tab = ordered[index];
                var yMax = top - index * (height + gap);
                var yMin = yMax - height;
                var active = string.Equals(tab.Key, view.ActiveTab, StringComparison.OrdinalIgnoreCase);
                var root = Sidebar + ".Nav." + tab.Key;
                // Only the stronger border overhangs the sidebar. The active fill,
                // hit area, icon and text remain strictly inside the navigation grid.
                if (active)
                {
                    const float borderX = 0.008f;
                    const float borderY = 0.003f;
                    AddPanel(ui, Navigation, root + ".Active.Border.Left", F(-0.025f, yMin), F(borderX, yMax), AccentActiveBorder);
                    AddPanel(ui, Navigation, root + ".Active.Border.Right", F(1f - borderX, yMin), F(1.02f, yMax), AccentActiveBorder);
                    AddPanel(ui, Navigation, root + ".Active.Border.Top", F(borderX, yMax - borderY), F(1f - borderX, yMax), AccentActiveBorder);
                    AddPanel(ui, Navigation, root + ".Active.Border.Bottom", F(borderX, yMin), F(1f - borderX, yMin + borderY), AccentActiveBorder);
                    AddPanel(ui, Navigation, root + ".Active.Fill", F(borderX, yMin + borderY), F(1f - borderX, yMax - borderY), AccentActive);
                }
                AddButton(ui, Navigation, root, F(0f, yMin), F(1f, yMax), "0 0 0 0",
                    "prostojmenu.ui tab " + tab.Key, string.Empty, 1, TextMain);
                AddNavigationIcon(ui, root, tab, active);
                AddLabel(ui, root, root + ".Text", "0.205 0", "0.91 1", tab.Title, 12,
                    active ? TextMain : "0.78 0.76 0.72 1", TextAnchor.MiddleLeft, FontBold);
                var supportUnread = view.Support != null
                    ? view.Support.UnreadCount
                    : view.Snapshot != null && view.Snapshot.Support != null ? view.Snapshot.Support.UnreadCount : 0;
                if (string.Equals(tab.Key, "support", StringComparison.OrdinalIgnoreCase) && supportUnread > 0)
                {
                    AddPanel(ui, root, root + ".Unread", "0.809 0.33", "0.90 0.67", Accent);
                    AddLabel(ui, root + ".Unread", root + ".Unread.Text", "0 0", "1 1", supportUnread >= 100 ? "99+" : supportUnread.ToString(CultureInfo.InvariantCulture), 9, TextMain, TextAnchor.MiddleCenter, FontBold);
                }
            }

            AddSidebarProfile(ui, player, view);
            AddSidebarStatus(ui, view);
        }

        private bool CanViewTab(BasePlayer player, MenuTab tab)
        {
            if (player == null || tab == null) return false;
            if (tab.Owner == null) return true;
            try
            {
                var result = tab.Owner.Call("ProstojMenu_CanView", player);
                return !(result is bool) || (bool)result;
            }
            catch (Exception exception)
            {
                PrintWarning("Tab visibility check failed for '" + tab.Key + "': " + exception.Message);
                return false;
            }
        }

        private void AddSidebarProfile(CuiElementContainer ui, BasePlayer player, PlayerView view)
        {
            var snapshot = view.Snapshot;
            var playerData = snapshot != null ? snapshot.Player : null;
            var serverData = snapshot != null ? snapshot.Server : null;
            var playerName = playerData != null && !string.IsNullOrWhiteSpace(playerData.Username)
                ? playerData.Username
                : player.displayName;
            var steamId = playerData != null && !string.IsNullOrWhiteSpace(playerData.SteamId)
                ? playerData.SteamId
                : player.UserIDString;
            var serverName = serverData != null && !string.IsNullOrWhiteSpace(serverData.Name)
                ? serverData.Name
                : ConVar.Server.hostname;
            var cleanPlayerName = CleanText(playerName).Trim();
            var initial = string.IsNullOrEmpty(cleanPlayerName)
                ? "?"
                : cleanPlayerName.Substring(0, 1).ToUpperInvariant();

            var root = Sidebar + ".Profile";
            AddPanel(ui, Navigation, root, "0.055 0.137", "0.945 0.225", ProfileSurface);
            AddSquarePanel(ui, root, root + ".AvatarFrame", "0.11 0.5", 13f, "0 0 0 0");
            AddOffsetPanel(ui, root + ".AvatarFrame", root + ".AvatarSurface",
                "0 0", "1 1", "1 1", "-1 -1", BgTertiary);
            if (!TryAddSteamAvatar(ui, root + ".AvatarSurface", root + ".Avatar", "0 0", "1 1", steamId, "1 1 1 1"))
                AddLabel(ui, root + ".AvatarSurface", root + ".AvatarInitial", "0 0", "1 1", initial, 12, TextMain, TextAnchor.MiddleCenter, FontBold);
            AddOutline(ui, root + ".AvatarFrame", root + ".AvatarBorder", Border, 1f);
            AddLabel(ui, root, root + ".Name", "0.22 0.25", "0.96 0.95",
                CompactText(cleanPlayerName, 18).ToUpperInvariant(), 9, TextMain, TextAnchor.MiddleLeft, FontBold);
            var balance = playerData != null ? FormatNumber(playerData.Balance) : "—";
            AddLabel(ui, root, root + ".Server", "0.22 0.05", "0.60 0.75",
                CompactText(CleanText(serverName), 13), 7, TextSecondary, TextAnchor.MiddleLeft, FontRegular);
            AddLabel(ui, root, root + ".Balance", "0.64 0.05", "0.88 0.75",
                balance, 8, TextMain, TextAnchor.MiddleRight, FontBold);
            AddSquarePanel(ui, root, root + ".BalanceCoin", "0.93 0.4", 5f, "0 0 0 0");
            AddRawImage(ui, root + ".BalanceCoin", root + ".BalanceCoin.Image", "0 0", "1 1",
                ImageUrl("rust-menu/coin-hd.png"), "1 1 1 1");
        }

        private void AddSidebarStatus(CuiElementContainer ui, PlayerView view)
        {
            var snapshot = view.Snapshot;
            var hasServer = snapshot != null && snapshot.Server != null;
            var root = Sidebar + ".Status";
            AddPanel(ui, Navigation, root, "0.055 0.028", "0.945 0.13", "0 0 0 0");
            AddPanel(ui, root, root + ".Dot", "0.055 0.68", "0.078 0.79", hasServer ? Success : Warning);
            AddLabel(ui, root, root + ".State", "0.105 0.58", "0.90 0.9", hasServer ? "СЕРВЕР ОНЛАЙН" : "СИНХРОНИЗАЦИЯ", 9, TextMain, TextAnchor.MiddleLeft, FontBold);
            var online = hasServer ? snapshot.Server.Players + " / " + snapshot.Server.MaxPlayers + " ИГРОКОВ" : "ДАННЫЕ ЗАГРУЖАЮТСЯ";
            AddLabel(ui, root, root + ".Online", "0.055 0.28", "0.945 0.59", online, 9, TextSecondary, TextAnchor.MiddleLeft, FontRegular);

            AddPanel(ui, root, root + ".OnlineTrack", "0.055 0.1", "0.945 0.18", "1 1 1 0.10");
            var onlineRatio = hasServer && snapshot.Server.MaxPlayers > 0
                ? Mathf.Clamp01((float) snapshot.Server.Players / snapshot.Server.MaxPlayers)
                : 0f;
            if (onlineRatio > 0f)
            {
                var onlineColor = onlineRatio >= 0.95f ? Danger : onlineRatio >= 0.85f ? AccentWarm : Success;
                AddPanel(ui, root, root + ".OnlineFill", "0.055 0.1", F(0.055f + 0.89f * onlineRatio, 0.18f), onlineColor);
            }
        }

        private void AddNavigationIcon(CuiElementContainer ui, string parent, MenuTab tab, bool active)
        {
            var root = parent + ".Icon";
            var color = active ? TextMain : "0.68 0.66 0.62 1";
            // The icon canvas and the source images are square, so the original
            // proportions survive at every supported resolution.
            AddPanel(ui, parent, root, "0.055 0.17", "0.157 0.83", "0 0 0 0");

            var icon = NormalizeIcon(tab.Key, tab.Glyph);
            var imageUrl = NavigationIconUrl(icon);
            if (!string.IsNullOrWhiteSpace(imageUrl) &&
                AddRawImage(ui, root, root + ".Image", "0.12 0.12", "0.88 0.88", imageUrl, color))
                return;

            // Never leave a blank gutter while a first-run download is in
            // progress. The cached website PNG replaces this fallback as soon
            // as it becomes available.
            AddNavigationFallbackIcon(ui, root, icon, color);
        }

        private static void AddNavigationFallbackIcon(CuiElementContainer ui, string root, string icon, string color)
        {
            switch (icon)
            {
                case "store": AddStoreIcon(ui, root, color); break;
                case "battlepass": AddBattlePassIcon(ui, root, color); break;
                case "calendar": AddCalendarIcon(ui, root, color); break;
                case "stats": AddStatsIcon(ui, root, color); break;
                case "top": AddTopIcon(ui, root, color); break;
                case "clans": AddClansIcon(ui, root, color); break;
                case "support": AddSupportIcon(ui, root, color); break;
                default: AddGridIcon(ui, root, color); break;
            }
        }

        private string NavigationIconUrl(string icon)
        {
            switch (icon)
            {
                case "store": return ImageUrl("rust-menu/icons/nav-cart.png");
                case "shop": return ImageUrl("rust-menu/icons/nav-shop.png?v=2");
                case "battlepass": return ImageUrl("rust-menu/icons/nav-battlepass.png");
                case "calendar": return ImageUrl("rust-menu/icons/nav-calendar.png");
                case "stats": return ImageUrl("rust-menu/icons/nav-stats.png");
                case "top": return ImageUrl("rust-menu/icons/nav-top.png");
                case "support": return ImageUrl("rust-menu/icons/nav-support.png");
                case "tournament": return ImageUrl("rust-menu/icons/nav-tournament.png");
                case "skindrops": return ImageUrl("rust-menu/icons/nav-skindrops.png");
                case "notifications": return ImageUrl("rust-menu/icons/nav-notifications.png");
                default: return null;
            }
        }

        private static string NormalizeIcon(string key, string glyph)
        {
            var normalizedKey = (key ?? string.Empty).Trim().ToLowerInvariant();
            if (normalizedKey == "shop") return "shop";
            if (normalizedKey == "store") return "store";

            var value = ((glyph ?? string.Empty) + " " + normalizedKey).ToLowerInvariant();
            if (value.Contains("cart") || value.Contains("basket")) return "store";
            if (value.Contains("shop") || value.Contains("market")) return "shop";
            if (value.Contains("pass") || value.Contains("season") || value.Contains("task")) return "battlepass";
            if (value.Contains("wipe") || value.Contains("calendar")) return "calendar";
            if (value.Contains("stat") || value.Contains("chart")) return "stats";
            if (value.Contains("top") || value.Contains("rank") || value.Contains("leader")) return "top";
            if (value.Contains("clan") || value.Contains("team") || value.Contains("flag")) return "clans";
            if (value.Contains("support") || value.Contains("help") || value.Contains("chat")) return "support";
            if (value.Contains("skin") || value.Contains("drop") || value.Contains("gift")) return "skindrops";
            if (value.Contains("key") || value.Contains("tournament") || value.Contains("race")) return "tournament";
            return "grid";
        }

        private static string NavigationMeta(string key)
        {
            switch ((key ?? string.Empty).ToLowerInvariant())
            {
                case "tournament": return "КЛЮЧИ И ТЕРМИНАЛ";
                case "skindrops": return "УСЛОВИЯ РАЗДАЧИ";
                case "store": return "ВАШИ ПОКУПКИ";
                case "battlepass": return "СЕЗОННЫЕ НАГРАДЫ";
                case "calendar": return "ВАЙПЫ И ОБНОВЛЕНИЯ";
                case "stats": return "ПРОГРЕСС ИГРОКА";
                case "top": return "РЕЙТИНГ ВАЙПА";
                case "clans": return "КОМАНДЫ СЕРВЕРА";
                case "support": return "СВЯЗАТЬСЯ С АДМИНИСТРАЦИЕЙ";
                default: return "ПОДКЛЮЧЕННЫЙ МОДУЛЬ";
            }
        }

        private static void AddStoreIcon(CuiElementContainer ui, string root, string color)
        {
            AddIconPart(ui, root, "BagLeft", "0.24 0.18", "0.32 0.68", color);
            AddIconPart(ui, root, "BagRight", "0.68 0.18", "0.76 0.68", color);
            AddIconPart(ui, root, "BagBottom", "0.24 0.18", "0.76 0.27", color);
            AddIconPart(ui, root, "BagTop", "0.24 0.60", "0.76 0.69", color);
            AddIconPart(ui, root, "HandleLeft", "0.36 0.65", "0.44 0.83", color);
            AddIconPart(ui, root, "HandleRight", "0.56 0.65", "0.64 0.83", color);
            AddIconPart(ui, root, "HandleTop", "0.36 0.76", "0.64 0.84", color);
        }

        private static void AddBattlePassIcon(CuiElementContainer ui, string root, string color)
        {
            AddIconPart(ui, root, "TicketLeft", "0.18 0.25", "0.27 0.75", color);
            AddIconPart(ui, root, "TicketRight", "0.73 0.25", "0.82 0.75", color);
            AddIconPart(ui, root, "TicketTop", "0.22 0.67", "0.78 0.76", color);
            AddIconPart(ui, root, "TicketBottom", "0.22 0.24", "0.78 0.33", color);
            AddIconPart(ui, root, "Spine", "0.47 0.34", "0.53 0.66", color);
            AddIconPart(ui, root, "StarTop", "0.42 0.53", "0.58 0.61", color);
            AddIconPart(ui, root, "StarBottom", "0.42 0.39", "0.58 0.47", color);
        }

        private static void AddCalendarIcon(CuiElementContainer ui, string root, string color)
        {
            AddIconPart(ui, root, "Left", "0.2 0.18", "0.28 0.76", color);
            AddIconPart(ui, root, "Right", "0.72 0.18", "0.8 0.76", color);
            AddIconPart(ui, root, "Bottom", "0.2 0.18", "0.8 0.27", color);
            AddIconPart(ui, root, "Top", "0.2 0.68", "0.8 0.77", color);
            AddIconPart(ui, root, "Rule", "0.24 0.54", "0.76 0.62", color);
            AddIconPart(ui, root, "BindLeft", "0.34 0.72", "0.42 0.88", color);
            AddIconPart(ui, root, "BindRight", "0.58 0.72", "0.66 0.88", color);
            AddIconPart(ui, root, "Day1", "0.34 0.36", "0.43 0.46", color);
            AddIconPart(ui, root, "Day2", "0.57 0.36", "0.66 0.46", color);
        }

        private static void AddStatsIcon(CuiElementContainer ui, string root, string color)
        {
            AddIconPart(ui, root, "Base", "0.18 0.18", "0.82 0.27", color);
            AddIconPart(ui, root, "Bar1", "0.24 0.27", "0.38 0.48", color);
            AddIconPart(ui, root, "Bar2", "0.45 0.27", "0.59 0.68", color);
            AddIconPart(ui, root, "Bar3", "0.66 0.27", "0.8 0.84", color);
        }

        private static void AddTopIcon(CuiElementContainer ui, string root, string color)
        {
            AddIconPart(ui, root, "Base", "0.16 0.18", "0.84 0.27", color);
            AddIconPart(ui, root, "Second", "0.18 0.27", "0.39 0.55", color);
            AddIconPart(ui, root, "First", "0.4 0.27", "0.61 0.82", color);
            AddIconPart(ui, root, "Third", "0.62 0.27", "0.82 0.44", color);
            AddIconPart(ui, root, "Cap", "0.45 0.84", "0.56 0.91", color);
        }

        private static void AddClansIcon(CuiElementContainer ui, string root, string color)
        {
            AddIconPart(ui, root, "Pole", "0.25 0.16", "0.34 0.84", color);
            AddIconPart(ui, root, "FlagTop", "0.34 0.67", "0.78 0.84", color);
            AddIconPart(ui, root, "FlagMid", "0.34 0.52", "0.68 0.68", color);
            AddIconPart(ui, root, "FlagTip", "0.61 0.6", "0.78 0.68", color);
            AddIconPart(ui, root, "Base", "0.16 0.16", "0.52 0.25", color);
        }

        private static void AddSupportIcon(CuiElementContainer ui, string root, string color)
        {
            AddIconPart(ui, root, "Left", "0.18 0.28", "0.27 0.78", color);
            AddIconPart(ui, root, "Right", "0.73 0.28", "0.82 0.78", color);
            AddIconPart(ui, root, "Top", "0.18 0.69", "0.82 0.79", color);
            AddIconPart(ui, root, "Bottom", "0.18 0.27", "0.68 0.36", color);
            AddIconPart(ui, root, "Tail", "0.60 0.18", "0.72 0.36", color);
            AddIconPart(ui, root, "Dot1", "0.32 0.48", "0.40 0.57", color);
            AddIconPart(ui, root, "Dot2", "0.48 0.48", "0.56 0.57", color);
            AddIconPart(ui, root, "Dot3", "0.64 0.48", "0.72 0.57", color);
        }

        private static void AddGridIcon(CuiElementContainer ui, string root, string color)
        {
            AddIconPart(ui, root, "A", "0.22 0.56", "0.44 0.8", color);
            AddIconPart(ui, root, "B", "0.56 0.56", "0.78 0.8", color);
            AddIconPart(ui, root, "C", "0.22 0.2", "0.44 0.44", color);
            AddIconPart(ui, root, "D", "0.56 0.2", "0.78 0.44", color);
        }

        private static void AddIconPart(CuiElementContainer ui, string parent, string suffix, string anchorMin, string anchorMax, string color)
        {
            AddPanel(ui, parent, parent + "." + suffix, anchorMin, anchorMax, color);
        }

        private void RenderHeader(BasePlayer player, PlayerView view)
        {
            CuiHelper.DestroyUi(player, Header + ".Dynamic");
            var ui = new CuiElementContainer();
            AddPanel(ui, Header, Header + ".Dynamic", "0 0", "1 1", "0 0 0 0");

            MenuTab activeTab;
            var sectionTitle = tabs.TryGetValue(view.ActiveTab, out activeTab) && activeTab != null
                ? CleanText(activeTab.Title).ToUpperInvariant()
                : "МЕНЮ";
            AddLabel(ui, Header + ".Dynamic", Header + ".SectionTitle", "0.025 0.18", "0.90 0.84",
                sectionTitle, 20, TextMain, TextAnchor.MiddleLeft, FontBold);

            // Add last inside the dynamic header so later header renders cannot cover it.
            AddSpriteButton(ui, Header + ".Dynamic", Header + ".Close", "0.948 0.34", "0.978 0.66",
                "prostojmenu.ui close", "assets/icons/close.png", TextMain);

            CuiHelper.AddUi(player, ui);
        }

        private void RenderContent(BasePlayer player, PlayerView view)
        {
            CuiHelper.DestroyUi(player, Content + ".Body");
            var ui = new CuiElementContainer();
            AddPanel(ui, Content, Content + ".Body", "0 0", "1 1", "0 0 0 0");
            CuiHelper.AddUi(player, ui);

            MenuTab tab;
            if (!tabs.TryGetValue(view.ActiveTab, out tab) || !CanViewTab(player, tab))
            {
                view.ActiveTab = "calendar";
                tab = tabs[view.ActiveTab];
            }

            // Connected modules own their content and must remain usable even if the
            // aggregate profile API is temporarily unavailable (the store has its own API).
            if (tab.Owner != null)
            {
                try
                {
                    tab.Owner.Call("ProstojMenu_Render", player, Content + ".Body", view.Page);
                }
                catch (Exception exception)
                {
                    PrintWarning("Tab '" + tab.Key + "' failed: " + exception.Message);
                    RenderEmptyState(player, "МОДУЛЬ НЕДОСТУПЕН", "Плагин вкладки вернул ошибку.", Danger,
                        NavigationIconUrl(NormalizeIcon(tab.Key, tab.Glyph)));
                }
                return;
            }

            if (string.Equals(view.ActiveTab, "support", StringComparison.OrdinalIgnoreCase))
            {
                RenderSupport(player, view);
                return;
            }

            if (string.Equals(view.ActiveTab, "battlepass", StringComparison.OrdinalIgnoreCase))
            {
                RenderBattlePass(player, view);
                return;
            }

            if (view.Snapshot == null && view.Loading)
            {
                RenderEmptyState(player, "ЗАГРУЖАЕМ ДАННЫЕ", "Синхронизируем профиль, вайпы и рейтинги…", AccentWarm,
                    NavigationIconUrl(NormalizeIcon(view.ActiveTab, view.ActiveTab)));
                return;
            }
            if (view.Snapshot == null && !string.IsNullOrEmpty(view.Error))
            {
                RenderEmptyState(player, "ДАННЫЕ НЕДОСТУПНЫ", view.Error + "\nПопробуйте обновить меню через несколько секунд.", Danger,
                    NavigationIconUrl(NormalizeIcon(view.ActiveTab, view.ActiveTab)));
                return;
            }

            switch (view.ActiveTab)
            {
                case "calendar": RenderCalendar(player, view); break;
                case "stats": RenderStats(player, view.Snapshot); break;
                case "top": RenderLeaderboard(player, view.Snapshot); break;
                case "clans": RenderClans(player, view.Snapshot); break;
                case "store": RenderEmptyState(player, "КОРЗИНА ПОДКЛЮЧАЕТСЯ", "Установите или перезагрузите ProstojRUST — вкладка зарегистрируется автоматически.", AccentWarm, StoreCartImageUrl); break;
                default: RenderEmptyState(player, "МОДУЛЬ НЕ ПОДКЛЮЧЁН", "Плагин вкладки сейчас недоступен.", TextSecondary); break;
            }
        }

        private void RenderBattlePass(BasePlayer player, PlayerView view)
        {
            if (view.BattlePass == null && view.BattlePassLoading)
            {
                RenderEmptyState(player, "ЗАГРУЖАЕМ BATTLE PASS", "Получаем сезон, прогресс и награды…", AccentWarm,
                    ImageUrl("rust-menu/icons/nav-battlepass.png"));
                return;
            }
            if (view.BattlePass == null)
            {
                RenderEmptyState(player, "BATTLE PASS НЕДОСТУПЕН",
                    string.IsNullOrWhiteSpace(view.BattlePassError) ? "Активный сезон пока не найден." : view.BattlePassError,
                    Danger, ImageUrl("rust-menu/icons/nav-battlepass.png"));
                return;
            }

            var battlePass = view.BattlePass;
            var season = battlePass.Season ?? new BattlePassSeasonData();
            var progress = battlePass.Progress ?? new BattlePassProgressData();
            var tasks = GetBattlePassTasks(battlePass);
            var pageCount = Math.Max(1, (tasks.Count + 5) / 6);
            view.BattlePassPage = Mathf.Clamp(view.BattlePassPage, 0, pageCount - 1);
            var pageTasks = tasks.Skip(view.BattlePassPage * 6).Take(6).ToList();
            var ui = new CuiElementContainer();

            var hero = Content + ".BattlePass.Hero";
            AddPanel(ui, Content + ".Body", hero, "0 0.43", "1 0.995", BgSecondary);

            var seasonTitle = "SEASON " + Math.Max(1, season.SeasonNumber).ToString(CultureInfo.InvariantCulture);
            AddLabel(ui, hero, hero + ".Title", "0.035 0.68", "0.60 0.93", seasonTitle, 27, TextMain, TextAnchor.MiddleLeft, FontBold);
            AddLabel(ui, hero, hero + ".Description", "0.038 0.47", "0.58 0.70",
                WrapText(Safe(season.Description), 62, 3), 11, "0.78 0.74 0.82 1", TextAnchor.UpperLeft, FontRegular);

            var progressLabel = progress.IsCompleted ? "СЕЗОН ПРОЙДЕН" : "ПРОГРЕСС СЕЗОНА";
            AddLabel(ui, hero, hero + ".ProgressLabel", "0.038 0.34", "0.40 0.43", progressLabel, 9,
                progress.IsCompleted ? Success : TextSecondary, TextAnchor.MiddleLeft, FontBold);
            AddLabel(ui, hero, hero + ".ProgressPercent", "0.43 0.33", "0.55 0.44",
                Mathf.Clamp(progress.Percent, 0, 100).ToString(CultureInfo.InvariantCulture) + "%", 15, TextMain, TextAnchor.MiddleRight, FontBold);
            AddPanel(ui, hero, hero + ".ProgressTrack", "0.038 0.275", "0.55 0.315", "1 1 1 0.10");
            var progressWidth = 0.038f + 0.512f * (Mathf.Clamp(progress.Percent, 0, 100) / 100f);
            if (progressWidth > 0.039f)
                AddPanel(ui, hero, hero + ".ProgressFill", "0.038 0.275", F(progressWidth, 0.315f), progress.IsCompleted ? Success : AccentWarm);
            AddLabel(ui, hero, hero + ".ProgressCount", "0.038 0.17", "0.55 0.255",
                progress.Completed + " / " + progress.Total + " ОСНОВНЫХ ЗАДАНИЙ", 10, TextSecondary, TextAnchor.MiddleLeft, FontRegular);
            if (!battlePass.IsAuthenticated)
                AddLabel(ui, hero, hero + ".LoginHint", "0.038 0.065", "0.55 0.16",
                    "ВОЙДИТЕ НА PROSTOJ.STORE ЧЕРЕЗ STEAM, ЧТОБЫ СОХРАНЯТЬ ПРОГРЕСС", 9, Warning, TextAnchor.MiddleLeft, FontBold);

            if (season.Medal != null && !string.IsNullOrWhiteSpace(season.Medal.Image))
            {
                AddRawImage(ui, hero, hero + ".Medal", "0.82 0.48", "0.94 0.88", BattlePassImageUrl(season.Medal.Image), "1 1 1 1");
                AddLabel(ui, hero, hero + ".MedalLabel", "0.765 0.35", "0.995 0.47", "МЕДАЛЬ СЕЗОНА", 9, Gold, TextAnchor.MiddleCenter, FontBold);
                AddLabel(ui, hero, hero + ".MedalName", "0.765 0.24", "0.995 0.36",
                    CompactText(Safe(season.Medal.Name).ToUpperInvariant(), 28), 11, TextMain, TextAnchor.MiddleCenter, FontBold);
            }

            var track = Content + ".BattlePass.Track";
            AddPanel(ui, Content + ".Body", track, "0 0.005", "1 0.42", "0 0 0 0");
            AddLabel(ui, track, track + ".Title", "0.005 0.84", "0.56 0.985", "НАГРАДЫ ПО ПОРЯДКУ", 13, TextMain, TextAnchor.MiddleLeft, FontBold);
            AddLabel(ui, track, track + ".Subtitle", "0.005 0.75", "0.70 0.86",
                tasks.Count + " ЭТАПОВ  •  ОСНОВНОЙ ПУТЬ И ДОПОЛНИТЕЛЬНЫЕ VIP-НАГРАДЫ", 9, TextSecondary, TextAnchor.MiddleLeft, FontRegular);
            AddLabel(ui, track, track + ".Page", "0.76 0.84", "0.86 0.98",
                view.BattlePassChecking ? "ПРОВЕРЯЕМ…" : (view.BattlePassPage + 1) + " / " + pageCount,
                10, view.BattlePassChecking ? AccentWarm : TextSecondary, TextAnchor.MiddleCenter, FontBold);
            AddButton(ui, track, track + ".Prev", "0.865 0.84", "0.925 0.985",
                view.BattlePassPage > 0 ? BgTertiary : "0.18 0.102 0.231 0.35",
                "prostojmenu.ui battlepass_prev", "НАЗАД", 8, view.BattlePassPage > 0 ? TextMain : TextSecondary);
            AddButton(ui, track, track + ".Next", "0.932 0.84", "0.995 0.985",
                view.BattlePassPage + 1 < pageCount ? Accent : "0.18 0.102 0.231 0.35",
                "prostojmenu.ui battlepass_next", "ДАЛЕЕ", 8, view.BattlePassPage + 1 < pageCount ? TextMain : TextSecondary);

            for (var index = 0; index < pageTasks.Count; index++)
                AddBattlePassTaskCard(ui, track, pageTasks[index], index, progress.CurrentTaskId,
                    battlePass.IsAuthenticated && !view.BattlePassChecking);

            if (pageTasks.Count == 0)
                AddLabel(ui, track, track + ".Empty", "0.1 0.15", "0.9 0.72", "ЗАДАНИЯ СЕЗОНА ПОКА НЕ ОПУБЛИКОВАНЫ", 15, TextSecondary, TextAnchor.MiddleCenter, FontBold);

            CuiHelper.AddUi(player, ui);
        }

        private void AddBattlePassTaskCard(CuiElementContainer ui, string parent, BattlePassTaskData task, int index,
            int? currentTaskId, bool canCheck)
        {
            const float width = 0.155f;
            const float gap = 0.011f;
            var xMin = 0.005f + index * (width + gap);
            var xMax = xMin + width;
            var root = parent + ".Task." + task.Id;
            var status = task.UserStatus != null ? (task.UserStatus.Status ?? "locked").ToLowerInvariant() : "locked";
            var isCurrent = currentTaskId.HasValue && currentTaskId.Value == task.Id;
            var completed = status == "completed";
            var statusColor = completed ? Success : isCurrent ? AccentWarm : task.IsVipOnly ? Gold : TextSecondary;
            var surface = isCurrent ? BgRaised : BgSecondary;

            AddPanel(ui, parent, root, F(xMin, 0.035f), F(xMax, 0.72f), surface);
            AddLabel(ui, root, root + ".Type", "0.055 0.86", "0.58 0.965", task.IsVipOnly ? "VIP" : "ЗАДАНИЕ", 8, statusColor, TextAnchor.MiddleLeft, FontBold);
            var position = task.Position > 0 ? task.Position : task.Sort;
            AddLabel(ui, root, root + ".Number", "0.60 0.84", "0.94 0.975", position.ToString("00", CultureInfo.InvariantCulture), 17, TextMain, TextAnchor.MiddleRight, FontBold);

            // 60% of the card width equals 43.7% of its height at 1920x1080,
            // so item and medal artwork stays square instead of stretching.
            AddPanel(ui, root, root + ".RewardSurface", "0.20 0.39", "0.80 0.827", "0.031 0.008 0.141 0.58");
            if (task.RewardItem != null && task.RewardItem.ItemId.GetValueOrDefault() != 0)
                AddItemIcon(ui, root + ".RewardSurface", root + ".RewardItem", "0.08 0.08", "0.92 0.92", task.RewardItem.ItemId.Value,
                    completed ? "1 1 1 0.62" : "1 1 1 1");
            else if (task.RewardItem != null && !string.IsNullOrWhiteSpace(task.RewardItem.Image))
                AddRawImage(ui, root + ".RewardSurface", root + ".Reward", "0.08 0.08", "0.92 0.92", BattlePassImageUrl(task.RewardItem.Image), completed ? "1 1 1 0.62" : "1 1 1 1");
            else if (string.Equals(task.RewardType, "currency", StringComparison.OrdinalIgnoreCase))
                AddRawImage(ui, root + ".RewardSurface", root + ".RewardCoin", "0.20 0.20", "0.80 0.80", ImageUrl("rust-menu/coin-hd.png"), "1 1 1 1");
            else
                AddGridIcon(ui, root + ".RewardSurface", statusColor);

            var title = !string.IsNullOrWhiteSpace(task.ShortDescription) ? task.ShortDescription : task.Title;
            AddLabel(ui, root, root + ".Name", "0.06 0.255", "0.94 0.375", WrapText(Safe(title), 23, 2), 10, TextMain, TextAnchor.UpperLeft, FontBold);
            AddLabel(ui, root, root + ".RewardName", "0.06 0.18", "0.94 0.265",
                BattlePassRewardLabel(task), 8, task.IsVipOnly ? Gold : TextSecondary, TextAnchor.MiddleLeft, FontRegular);

            var progress = Math.Max(0, task.Progress ?? 0);
            var maxProgress = Math.Max(1, task.MaxProgress ?? 1);
            if (isCurrent && !completed)
            {
                // Reserve a stable right-hand column for long counters. The old 21%
                // label width clipped values while the progress bar occupied 66%.
                AddPanel(ui, root, root + ".ProgressTrack", "0.06 0.105", "0.56 0.13", "1 1 1 0.10");
                var progressMax = 0.06f + 0.50f * Mathf.Clamp01((float) progress / maxProgress);
                if (progressMax > 0.061f)
                    AddPanel(ui, root, root + ".ProgressFill", "0.06 0.105", F(progressMax, 0.13f), AccentWarm);
                var progressText = progress + " / " + maxProgress;
                var progressFontSize = progressText.Length > 15 ? 6 : progressText.Length > 11 ? 7 : 8;
                AddLabel(ui, root, root + ".ProgressText", "0.58 0.075", "0.95 0.16", progressText, progressFontSize, TextMain, TextAnchor.MiddleRight, FontBold);
                if (canCheck)
                    AddLabel(ui, root, root + ".ActionHint", "0.06 0.01", "0.94 0.075", "НАЖМИТЕ, ЧТОБЫ ПРОВЕРИТЬ", 7, AccentWarm, TextAnchor.MiddleLeft, FontBold);
            }
            else
            {
                AddLabel(ui, root, root + ".Status", "0.06 0.065", "0.94 0.155",
                    BattlePassStatusLabel(status, task.IsVipOnly), 8, statusColor, TextAnchor.MiddleLeft, FontBold);
            }

            if (isCurrent && !completed && canCheck)
                AddButton(ui, root, root + ".Check", "0 0", "1 1", "0 0 0 0",
                    "prostojmenu.ui battlepass_check " + task.Id, string.Empty, 1, TextMain);
        }

        private static string BattlePassRewardLabel(BattlePassTaskData task)
        {
            if (task.RewardItem != null)
            {
                var count = Math.Max(1, task.RewardItem.Count);
                return CompactText(Safe(task.RewardItem.Name), 20).ToUpperInvariant() + (count > 1 ? "  ×" + count : string.Empty);
            }
            if (string.Equals(task.RewardType, "currency", StringComparison.OrdinalIgnoreCase))
                return "+" + Math.Max(0d, task.RewardAmount ?? 0d).ToString("0.##", CultureInfo.InvariantCulture) + " К БАЛАНСУ";
            return "НАГРАДА СЕЗОНА";
        }

        private static string BattlePassStatusLabel(string status, bool vip)
        {
            switch (status)
            {
                case "completed": return "ВЫПОЛНЕНО";
                case "available": return "ДОСТУПНО";
                case "unavailable": return vip ? "ТРЕБУЕТСЯ VIP" : "НЕДОСТУПНО";
                default: return "ЗАКРЫТО";
            }
        }

        private void RenderCalendar(BasePlayer player, PlayerView view)
        {
            var snapshot = view.Snapshot;
            var now = DateTime.Now;
            var month = SelectedCalendarMonth(view);
            var serverId = snapshot != null && snapshot.Server != null ? snapshot.Server.Id : 0;
            var selectedMonthKey = serverId.ToString(CultureInfo.InvariantCulture) + "/" + month.ToString("yyyy-MM", CultureInfo.InvariantCulture);
            var monthData = string.Equals(view.CalendarMonthKey, selectedMonthKey, StringComparison.Ordinal)
                ? view.CalendarMonth
                : null;
            var snapshotEvents = snapshot != null && snapshot.Calendar != null
                ? snapshot.Calendar
                : new List<CalendarEvent>();
            var mondayOffset = ((int) month.DayOfWeek + 6) % 7;
            var firstGridDay = month.AddDays(-mondayOffset);
            var ui = new CuiElementContainer();

            AddPanel(ui, Content + ".Body", Content + ".Calendar.Toolbar", "0 0.895", "1 1", BgRaised);
            AddLabel(ui, Content + ".Body", Content + ".Calendar.Title", "0.018 0.91", "0.55 0.99", MonthName(month.Month) + " " + month.Year, 20, TextMain, TextAnchor.MiddleLeft, FontBold);
            var calendarSubtitle = view.CalendarLoading
                ? "Загружаем события месяца…"
                : !string.IsNullOrWhiteSpace(view.CalendarError)
                    ? view.CalendarError + " • показаны доступные данные"
                    : "Календарь сервера • вайпы, обновления и выходные";
            AddLabel(ui, Content + ".Body", Content + ".Calendar.Subtitle", "0.38 0.91", "0.86 0.985", calendarSubtitle, 9,
                !string.IsNullOrWhiteSpace(view.CalendarError) ? Warning : TextSecondary, TextAnchor.MiddleLeft, FontRegular);
            AddButton(ui, Content + ".Body", Content + ".Calendar.Prev", "0.88 0.91", "0.93 0.985", BgSecondary, "prostojmenu.ui calendar_prev", "‹", 20, TextMain);
            AddButton(ui, Content + ".Body", Content + ".Calendar.Next", "0.935 0.91", "0.985 0.985", BgSecondary, "prostojmenu.ui calendar_next", "›", 20, TextMain);

            var grid = Content + ".Calendar.Grid";
            AddPanel(ui, Content + ".Body", grid, "0 0.075", "1 0.89", Border);
            var weekdays = new[] { "ПН", "ВТ", "СР", "ЧТ", "ПТ", "СБ", "ВС" };
            // CUI anchors are normalized independently by axis. The calendar
            // is roughly twice as wide as it is tall, so equal anchor gaps make
            // horizontal separators half as many pixels and they disappear.
            const float columnGap = 0.003f;
            const float rowGap = 0.006f;
            var columnWidth = (1f - columnGap * 6f) / 7f;
            for (var column = 0; column < 7; column++)
            {
                var xMin = column * (columnWidth + columnGap);
                AddLabel(ui, grid, grid + ".Weekday." + column, F(xMin, 0.91f), F(xMin + columnWidth, 0.99f), weekdays[column], 10,
                    column >= 5 ? "0.98 0.52 0.50 1" : TextSecondary, TextAnchor.MiddleCenter, FontBold);
            }

            const float daysTop = 0.895f;
            const float daysBottom = 0.005f;
            var rowHeight = (daysTop - daysBottom - rowGap * 5f) / 6f;
            for (var index = 0; index < 42; index++)
            {
                var date = firstGridDay.AddDays(index);
                var column = index % 7;
                var row = index / 7;
                var xMin = column * (columnWidth + columnGap);
                var xMax = xMin + columnWidth;
                var yMax = daysTop - row * (rowHeight + rowGap);
                var yMin = yMax - rowHeight;
                var root = grid + ".Day." + index;
                var inMonth = date.Month == month.Month;
                var isToday = date.Date == now.Date;
                var dateKey = date.ToString("yyyy-MM-dd", CultureInfo.InvariantCulture);
                var dayEvents = new List<CalendarEvent>();
                List<CalendarMonthEvent> apiEvents;
                if (monthData != null && monthData.Events != null && monthData.Events.TryGetValue(dateKey, out apiEvents) && apiEvents != null)
                {
                    foreach (var apiEvent in apiEvents)
                    {
                        if (apiEvent == null) continue;
                        dayEvents.Add(new CalendarEvent
                        {
                            Id = apiEvent.Id,
                            Type = apiEvent.Type,
                            Label = CalendarEventLabel(apiEvent.Type),
                            EventAt = !string.IsNullOrWhiteSpace(apiEvent.Date) ? apiEvent.Date : dateKey
                        });
                    }
                }
                else if (monthData == null)
                {
                    dayEvents = snapshotEvents.Where(item => IsCalendarDay(item.EventAt, date)).ToList();
                }
                string highlight = null;
                var hasHighlight = monthData != null && monthData.Highlights != null &&
                                   monthData.Highlights.TryGetValue(dateKey, out highlight);
                var isHoliday = hasHighlight && string.Equals(highlight, "holiday", StringComparison.OrdinalIgnoreCase);
                var isWeekend = date.DayOfWeek == DayOfWeek.Saturday || date.DayOfWeek == DayOfWeek.Sunday ||
                                (hasHighlight && string.Equals(highlight, "weekend", StringComparison.OrdinalIgnoreCase));
                var primary = dayEvents.FirstOrDefault();
                var eventColor = primary != null ? EventColor(primary.Type) : Border;
                var isGlobalWipe = primary != null && string.Equals(primary.Type, "global_wipe", StringComparison.OrdinalIgnoreCase);
                var baseColor = inMonth ? BgRaised : "0.330 0.314 0.290 0.42";
                if (isWeekend) baseColor = inMonth ? "0.412 0.392 0.369 0.92" : "0.330 0.314 0.290 0.34";
                if (isHoliday) baseColor = inMonth ? "0.455 0.420 0.385 0.94" : "0.330 0.314 0.290 0.34";
                if (isGlobalWipe) baseColor = inMonth ? "0.560 0.205 0.105 0.94" : "0.430 0.165 0.095 0.62";
                if (isToday) baseColor = "0.145 0.330 0.505 0.96";

                AddPanel(ui, grid, root, F(xMin, yMin), F(xMax, yMax), baseColor);
                if (isToday || primary != null)
                    AddPanel(ui, root, root + ".StateBand", "0 0.955", "1 1", isToday ? "0.302 0.702 0.961 1" : eventColor);
                AddLabel(ui, root, root + ".Date", "0.08 0.58", "0.42 0.94", date.Day.ToString(CultureInfo.InvariantCulture), 13,
                    isToday || isGlobalWipe ? TextMain : isHoliday ? "1 0.45 0.43 1" : isWeekend ? "0.98 0.62 0.60 1" : inMonth ? TextMain : "0.42 0.40 0.46 1", TextAnchor.MiddleLeft, FontBold);

                if (primary != null)
                {
                    AddLabel(ui, root, root + ".Event", "0.08 0.08", "0.92 0.54", Safe(primary.Label).ToUpperInvariant(), 8, isGlobalWipe ? TextMain : eventColor, TextAnchor.LowerLeft, FontBold);
                    if (dayEvents.Count > 1)
                        AddLabel(ui, root, root + ".Count", "0.68 0.62", "0.92 0.92", "+" + (dayEvents.Count - 1), 9, TextSecondary, TextAnchor.MiddleRight, FontBold);
                }
            }

            AddCalendarLegend(ui, Content + ".Body", "0 0.005", "1 0.065");
            CuiHelper.AddUi(player, ui);
        }

        private void AddCalendarLegend(CuiElementContainer ui, string parent, string anchorMin, string anchorMax)
        {
            var root = Content + ".Calendar.Legend";
            AddPanel(ui, parent, root, anchorMin, anchorMax, BgSecondary);
            var labels = new[] { "ГЛОБАЛЬНЫЙ ВАЙП", "ОБНОВЛЕНИЕ", "ВАЙП КАРТЫ", "ВЫХОДНОЙ", "СЕГОДНЯ" };
            var colors = new[] { EventColor("global_wipe"), "0.302 0.702 0.961 1", Success, "0.98 0.52 0.50 1", "0.302 0.702 0.961 1" };
            for (var i = 0; i < labels.Length; i++)
            {
                var xMin = 0.015f + i * 0.197f;
                AddPanel(ui, root, root + ".Dot." + i, F(xMin, 0.34f), F(xMin + 0.012f, 0.66f), colors[i]);
                AddLabel(ui, root, root + ".Text." + i, F(xMin + 0.022f, 0f), F(xMin + 0.19f, 1f), labels[i], 8, TextSecondary, TextAnchor.MiddleLeft, FontBold);
            }
        }

        private void RenderStats(BasePlayer player, MenuSnapshot snapshot)
        {
            var stats = snapshot != null && snapshot.Player != null && snapshot.Player.Stats != null
                ? snapshot.Player.Stats
                : new PlayerStats();
            var ui = new CuiElementContainer();
            AddSectionHeading(ui, "СТАТИСТИКА ИГРОКА", "Боевой профиль и прогресс текущего вайпа", "LIVE PROFILE");

            var portrait = Content + ".Stats.Portrait";
            AddPanel(ui, Content + ".Body", portrait, "0 0.035", "0.278 0.90", BgSecondary);
            var playerProfile = snapshot != null ? snapshot.Player : null;
            var avatarSteamId = playerProfile != null && !string.IsNullOrWhiteSpace(playerProfile.SteamId)
                ? playerProfile.SteamId
                : player.UserIDString;
            var playerName = playerProfile != null ? Safe(playerProfile.Username).ToUpperInvariant() : "RUST PLAYER";
            var playerInitial = string.IsNullOrWhiteSpace(playerName) ? "?" : playerName.Substring(0, 1);
            AddSquarePanel(ui, portrait, portrait + ".AvatarFrame", "0.5 0.775", 30f, "0 0 0 0");
            AddOffsetPanel(ui, portrait + ".AvatarFrame", portrait + ".AvatarSurface", "0 0", "1 1", "2 2", "-2 -2", BgTertiary);
            if (!TryAddSteamAvatar(ui, portrait + ".AvatarSurface", portrait + ".AvatarImage", "0 0", "1 1", avatarSteamId, "1 1 1 1"))
                AddLabel(ui, portrait + ".AvatarSurface", portrait + ".AvatarInitial", "0 0", "1 1", playerInitial, 29, TextMain, TextAnchor.MiddleCenter, FontBold);
            AddOutline(ui, portrait + ".AvatarFrame", portrait + ".AvatarBorder", Border, 1f);
            AddLabel(ui, portrait, portrait + ".Name", "0.08 0.625", "0.92 0.71", CompactText(playerName, 20), 13, TextMain, TextAnchor.MiddleCenter, FontBold);
            AddLabel(ui, portrait, portrait + ".Status", "0.08 0.555", "0.92 0.63", "АКТИВНЫЙ ИГРОК", 9, AccentWarm, TextAnchor.MiddleCenter, FontBold);
            AddStatsProfileRow(ui, portrait, portrait + ".Kd", "0.09 0.405", "0.91 0.515", "stat-kd.png?v=2",
                "K/D", stats.Kd.ToString("0.00", CultureInfo.InvariantCulture), AccentWarm);
            AddStatsProfileRow(ui, portrait, portrait + ".Playtime", "0.09 0.265", "0.91 0.375", "stat-time.png?v=2",
                "ВРЕМЯ", FormatPlaytime(stats.Playtime), TextMain);
            AddStatsProfileRow(ui, portrait, portrait + ".Combat", "0.09 0.125", "0.91 0.235", "stat-combat.png?v=2", "БОЙ",
                FormatNumber(stats.Kills) + " / " + FormatNumber(stats.Deaths) + " / " + FormatNumber(stats.Scientists), TextMain);

            AddPanel(ui, Content + ".Body", Content + ".Stats.Found", "0.286 0.65", "1 0.90", BgSecondary);
            AddLabel(ui, Content + ".Stats.Found", Content + ".Stats.Found.Title", "0.025 0.75", "0.7 0.95", "НАЙДЕНО И СОБРАНО", 11, TextMain, TextAnchor.MiddleLeft, FontBold);
            var foundByKey = (stats.Found ?? new List<LootMetric>())
                .Where(item => item != null && !string.IsNullOrWhiteSpace(item.Key))
                .GroupBy(item => item.Key, StringComparer.OrdinalIgnoreCase)
                .ToDictionary(group => group.Key, group => group.First(), StringComparer.OrdinalIgnoreCase);
            var foundKeys = new[] { "diesel_barrel", "animal_fat", "leather", "scrap" };
            var foundLabels = new[] { "ДИЗЕЛЬНАЯ БОЧКА", "ЖИВОТНЫЙ ЖИР", "КОЖА", "СКРАП" };
            var foundFallbackImages = new[]
            {
                "https://storage.prostoj.store/uploads/drop64/296_d034f99297824b26e006fd4345f2fd9d.png",
                "https://storage.prostoj.store/uploads/drop64/298_911c5ed162dbd022146e25022957757d.png",
                "https://storage.prostoj.store/uploads/drop64/301_75d16b9d76868a7a833e6fb6942614ea.png",
                "https://storage.prostoj.store/uploads/drop64/305_6949c48b05801055b644b5938b1c427c.png"
            };
            for (var i = 0; i < foundKeys.Length; i++)
            {
                LootMetric foundItem;
                foundByKey.TryGetValue(foundKeys[i], out foundItem);
                var foundImage = foundItem != null && !string.IsNullOrWhiteSpace(foundItem.Image) ? foundItem.Image : foundFallbackImages[i];
                AddFoundMetricCard(ui, i, foundLabels[i], foundItem != null ? foundItem.Count : 0, foundImage);
            }

            AddPanel(ui, Content + ".Body", Content + ".Stats.Loot", "0.286 0.285", "1 0.645", BgSecondary);
            AddLabel(ui, Content + ".Stats.Loot", Content + ".Stats.Loot.Title", "0.025 0.82", "0.7 0.97", "НАЙДЕНО КОНТЕЙНЕРОВ", 11, TextMain, TextAnchor.MiddleLeft, FontBold);
            var lootByKey = (stats.Loot ?? new List<LootMetric>())
                .Where(item => item != null && !string.IsNullOrWhiteSpace(item.Key))
                .GroupBy(item => item.Key, StringComparer.OrdinalIgnoreCase)
                .ToDictionary(group => group.Key, group => group.First(), StringComparer.OrdinalIgnoreCase);
            var lootKeys = new[] { "crates", "crate_elite", "crate_normal", "crate_underwater_advanced", "crate_underwater_basic", "supply_drop", "barrel", "crate_open" };
            var lootLabels = new[] { "КРЕЙТЫ", "ЭЛИТНЫЙ ЯЩИК", "АРМЕЙСКИЙ ЯЩИК", "ПОДВОДНЫЙ\nПРОДВИНУТЫЙ", "ПОДВОДНЫЙ\nБАЗОВЫЙ", "АИРДРОП", "РАЗБИТО БОЧЕК", "ОБЫЧНЫЙ ЯЩИК" };
            var lootImageAspects = new[] { 1f, 1f, 64f / 39f, 1f, 1f, 64f / 58f, 38f / 64f, 1f };
            var lootFallbackImages = new[]
            {
                "https://storage.prostoj.store/uploads/drop64/1016_1_0dfd250d3e2c32bca8931e5543a7b96a.webp",
                "https://storage.prostoj.store/uploads/drop64/1018_1_3cb9b6c81067adf8bda075d4f3fc4ea6.webp",
                "https://storage.prostoj.store/uploads/drop64/1019_1_fcbdbbe5ea8a4ad0f379b41af6bc1f43.png",
                "https://storage.prostoj.store/uploads/drop64/1020_1_46817913f780ec6dee026b2ab96a5bac.webp",
                "https://storage.prostoj.store/uploads/drop64/1021_1_d667b074c9e038fd08f8c3f59f8ddadb.webp",
                "https://storage.prostoj.store/uploads/drop64/1022_1_5fe8a20475f1a4ebd47bca582e71b13b.png",
                "https://storage.prostoj.store/uploads/drop64/863_ca8a4487be04988f2814bc8841a7c425.png",
                "https://storage.prostoj.store/uploads/drop64/441_522b6e2b6c99a6e2e596db57ef080be6.png"
            };
            for (var i = 0; i < lootKeys.Length; i++)
            {
                LootMetric lootItem;
                lootByKey.TryGetValue(lootKeys[i], out lootItem);
                var lootImage = lootItem != null && !string.IsNullOrWhiteSpace(lootItem.Image) ? lootItem.Image : lootFallbackImages[i];
                AddLootMetricCard(ui, i % 4, i / 4, lootLabels[i], lootItem != null ? lootItem.Count : 0, lootImage, lootImageAspects[i]);
            }

            AddPanel(ui, Content + ".Body", Content + ".Stats.Resources", "0.286 0.035", "1 0.28", BgSecondary);
            AddLabel(ui, Content + ".Stats.Resources", Content + ".Stats.Resources.Title", "0.025 0.74", "0.5 0.94", "ДОБЫТО РЕСУРСОВ", 11, TextMain, TextAnchor.MiddleLeft, FontBold);
            var resources = stats.Resources ?? new ResourceData();
            var values = new[] { resources.Wood, resources.Stones, resources.Metal, resources.Sulfur };
            var labels = new[] { "ДЕРЕВО", "КАМЕНЬ", "МЕТАЛЛ", "СЕРА" };
            var images = new[] { ImageUrl("user-stats/wood.png"), ImageUrl("user-stats/stone.png"), ImageUrl("user-stats/iron_stone.png"), ImageUrl("user-stats/gold.png") };
            for (var i = 0; i < 4; i++)
            {
                var xMin = 0.025f + i * 0.243f;
                var xMax = xMin + 0.240f;
                var barRoot = Content + ".Stats.Resources." + i;
                AddPanel(ui, Content + ".Stats.Resources", barRoot, F(xMin, 0.12f), F(xMax, 0.69f), BgRaised);
                AddSquareRawImage(ui, barRoot, barRoot + ".Image", "0.20 0.5", 23f, images[i], "1 1 1 0.92");
                AddLabel(ui, barRoot, barRoot + ".Label", "0.40 0.53", "0.96 0.9", labels[i], 9, TextSecondary, TextAnchor.MiddleLeft, FontBold);
                AddLabel(ui, barRoot, barRoot + ".Value", "0.40 0.08", "0.96 0.58", FormatNumber(values[i]), 15, TextMain, TextAnchor.MiddleLeft, FontBold);
            }
            CuiHelper.AddUi(player, ui);
        }

        private void AddStatsProfileRow(CuiElementContainer ui, string parent, string root, string anchorMin, string anchorMax,
            string iconFile, string label, string value, string valueColor)
        {
            AddPanel(ui, parent, root, anchorMin, anchorMax, BgRaised);
            AddSquareRawImage(ui, root, root + ".Icon", "0.09 0.5", 9f,
                ImageUrl("rust-menu/icons/" + iconFile), valueColor);
            AddLabel(ui, root, root + ".Label", "0.17 0", "0.48 1", label, 8, TextMain, TextAnchor.MiddleLeft, FontBold);
            AddLabel(ui, root, root + ".Value", "0.48 0", "0.93 1", value, 9, valueColor, TextAnchor.MiddleRight, FontBold);
        }

        private void AddFoundMetricCard(CuiElementContainer ui, int column, string label, int value, string imageUrl)
        {
            var panel = Content + ".Stats.Found";
            var xMin = 0.025f + column * 0.243f;
            var xMax = xMin + 0.240f;
            var root = panel + ".Item." + column;
            AddPanel(ui, panel, root, F(xMin, 0.10f), F(xMax, 0.70f), BgRaised);
            AddSquareRawImage(ui, root, root + ".Image", "0.20 0.5", 24f, imageUrl, "1 1 1 0.94");
            AddLabel(ui, root, root + ".Value", "0.40 0.47", "0.95 0.90", FormatNumber(value), 15, TextMain, TextAnchor.MiddleLeft, FontBold);
            AddLabel(ui, root, root + ".Label", "0.40 0.08", "0.96 0.50", label, 8, TextSecondary, TextAnchor.MiddleLeft, FontBold);
        }

        private void AddLootMetricCard(CuiElementContainer ui, int column, int row, string label, int value, string imageUrl, float imageAspect)
        {
            var panel = Content + ".Stats.Loot";
            var xMin = 0.025f + column * 0.243f;
            var xMax = xMin + 0.240f;
            var yMax = 0.78f - row * 0.38f;
            var yMin = yMax - 0.32f;
            var root = panel + ".Item." + column + "." + row;
            AddPanel(ui, panel, root, F(xMin, yMin), F(xMax, yMax), BgRaised);
            var imageHalfWidth = imageAspect >= 1f ? 22f : 22f * imageAspect;
            var imageHalfHeight = imageAspect >= 1f ? 22f / imageAspect : 22f;
            AddFittedRawImage(ui, root, root + ".Image", "0.19 0.5", imageHalfWidth, imageHalfHeight, imageUrl, "1 1 1 0.94");
            AddLabel(ui, root, root + ".Value", "0.39 0.49", "0.95 0.92", FormatNumber(value), 14, TextMain, TextAnchor.MiddleLeft, FontBold);
            AddLabel(ui, root, root + ".Label", "0.39 0.06", "0.96 0.52", label, 8, TextSecondary, TextAnchor.MiddleLeft, FontBold);
        }

        private void RenderLeaderboard(BasePlayer player, MenuSnapshot snapshot)
        {
            var board = snapshot != null && snapshot.Leaderboard != null ? snapshot.Leaderboard : new LeaderboardData();
            var categories = board.Categories ?? new Dictionary<string, LeaderboardCategory>(StringComparer.OrdinalIgnoreCase);
            var ui = new CuiElementContainer();
            AddSectionHeading(ui, "ТОП ИГРОКОВ", "Восемь рейтингов текущего вайпа — как на странице статистики сервера", "8 КАТЕГОРИЙ");

            var keys = new[] { "reider", "killer", "peaceful", "playtime", "farmer", "fishing", "hunter", "fermer" };
            var labels = new[] { "ЛУЧШИЙ РЕЙДЕР", "ЛУЧШИЙ КИЛЛЕР", "ЛУЧШИЙ МИРНЫЙ", "ТОП ПО ОНЛАЙНУ", "ЛУЧШИЙ ФАРМЕР", "ЛУЧШИЙ РЫБАК", "ЛУЧШИЙ ОХОТНИК", "ЛУЧШИЙ ФЕРМЕР" };
            var accents = new[] { AccentWarm, Danger, Success, "0.302 0.702 0.961 1", Gold, "0.267 0.733 0.941 1", Bronze, "0.525 0.784 0.365 1" };

            for (var categoryIndex = 0; categoryIndex < keys.Length; categoryIndex++)
            {
                LeaderboardCategory category;
                categories.TryGetValue(keys[categoryIndex], out category);
                var items = category != null && category.Items != null ? category.Items : new List<LeaderboardRow>();
                var column = categoryIndex % 4;
                var gridRow = categoryIndex / 4;
                const float horizontalMargin = 0.005f;
                const float horizontalGap = 0.008f;
                const float cardWidth = 0.239f;
                var xMin = horizontalMargin + column * (cardWidth + horizontalGap);
                var xMax = xMin + cardWidth;
                // Give the heading a little more breathing room without changing
                // card height or disturbing the second leaderboard row.
                var yMax = gridRow == 0 ? 0.89f : 0.454f;
                var yMin = gridRow == 0 ? 0.456f : 0.02f;
                var card = Content + ".Top.Category." + keys[categoryIndex];

                AddPanel(ui, Content + ".Body", card, F(xMin, yMin), F(xMax, yMax), BgSecondary);
                AddPanel(ui, card, card + ".Header", "0 0.78", "1 1", BgRaised);
                AddOffsetPanel(ui, card + ".Header", card + ".Header.BottomBorder",
                    "0 0", "1 0", "0 0", "0 1", Border);
                var categoryLabel = category != null && !string.IsNullOrWhiteSpace(category.Label)
                    ? category.Label.ToUpperInvariant()
                    : labels[categoryIndex];
                AddLabel(ui, card, card + ".Title", "0.055 0.79", "0.945 0.97", CompactText(categoryLabel, 24), 11, TextMain, TextAnchor.MiddleLeft, FontBold);

                for (var playerIndex = 0; playerIndex < 3; playerIndex++)
                {
                    var rowMax = 0.775f - playerIndex * 0.255f;
                    var rowMin = rowMax - 0.245f;
                    var rowRoot = card + ".Player." + playerIndex;
                    AddPanel(ui, card, rowRoot, F(0f, rowMin), F(1f, rowMax), playerIndex % 2 == 0 ? BgRaised : "0.330 0.314 0.290 0.48");

                    if (playerIndex >= items.Count)
                    {
                        AddLabel(ui, rowRoot, rowRoot + ".Empty", "0.08 0", "0.92 1", "—", 12, TextSecondary, TextAnchor.MiddleCenter, FontRegular);
                        continue;
                    }

                    var row = items[playerIndex];
                    var position = row.Position > 0 ? row.Position : playerIndex + 1;
                    var medal = position == 1 ? Gold : position == 2 ? Silver : Bronze;
                    // Keep the portrait square at the reference canvas. The image
                    // uses a fixed two-pixel inset so every side of the frame has
                    // identical thickness after Rust rounds UI coordinates.
                    AddSquarePanel(ui, rowRoot, rowRoot + ".AvatarFrame", "0.115 0.5", 15f, "0 0 0 0");
                    AddOffsetPanel(ui, rowRoot + ".AvatarFrame", rowRoot + ".AvatarSurface",
                        "0 0", "1 1", "2 2", "-2 -2", BgTertiary);
                    if (!TryAddSteamAvatar(ui, rowRoot + ".AvatarSurface", rowRoot + ".Avatar", "0 0", "1 1", row.SteamId, "1 1 1 1"))
                        AddLabel(ui, rowRoot + ".AvatarSurface", rowRoot + ".Initial", "0 0", "1 1", PlayerInitial(row.Username), 13, TextMain, TextAnchor.MiddleCenter, FontBold);
                    AddOutline(ui, rowRoot + ".AvatarFrame", rowRoot + ".AvatarBorder", medal, 1f);
                    if (row.Status == true)
                        AddStatusDot(ui, rowRoot + ".AvatarFrame", rowRoot + ".Online");

                    AddLabel(ui, rowRoot, rowRoot + ".Place", "0.245 0.53", "0.34 0.91", "#" + position, 9, medal, TextAnchor.MiddleLeft, FontBold);
                    AddLabel(ui, rowRoot, rowRoot + ".Name", "0.245 0.12", "0.69 0.58", CompactText(Safe(row.Username), 14), 10, TextMain, TextAnchor.MiddleLeft, FontBold);
                    AddLabel(ui, rowRoot, rowRoot + ".Score", "0.72 0.16", "0.945 0.84", CompactText(Safe(row.Score), 12), 11, TextSecondary, TextAnchor.MiddleRight, FontBold);
                }
            }

            CuiHelper.AddUi(player, ui);
        }

        private void RenderClans(BasePlayer player, MenuSnapshot snapshot)
        {
            var clans = snapshot != null && snapshot.Clans != null ? snapshot.Clans : new ClansData();
            var ui = new CuiElementContainer();
            AddSectionHeading(ui, "КЛАНЫ", "Команды сервера, состав и прогресс", clans.MyClan != null ? "ВАШ КЛАН  [" + clans.MyClan.Tag + "]" : "COMMUNITY");

            var listTop = 0.82f;
            if (clans.MyClan != null)
            {
                var mine = clans.MyClan;
                AddPanel(ui, Content + ".Body", Content + ".Clans.Mine", "0 0.64", "1 0.82", "0.922 0.047 0.208 0.12");
                AddPanel(ui, Content + ".Clans.Mine", Content + ".Clans.Mine.Accent", "0 0", "0.012 1", Accent);
                AddLabel(ui, Content + ".Clans.Mine", Content + ".Clans.Mine.Tag", "0.035 0.22", "0.16 0.78", "[" + Safe(mine.Tag) + "]", 20, HexToCui(mine.TagColor, AccentWarm), TextAnchor.MiddleCenter, FontBold);
                AddLabel(ui, Content + ".Clans.Mine", Content + ".Clans.Mine.Name", "0.18 0.48", "0.64 0.82", Safe(mine.Name), 18, TextMain, TextAnchor.MiddleLeft, FontBold);
                AddLabel(ui, Content + ".Clans.Mine", Content + ".Clans.Mine.Motto", "0.18 0.15", "0.68 0.5", Safe(mine.Motto), 11, TextSecondary, TextAnchor.MiddleLeft, FontRegular);
                AddLabel(ui, Content + ".Clans.Mine", Content + ".Clans.Mine.Meta", "0.7 0.2", "0.96 0.8", "УРОВЕНЬ " + mine.Level + "   •   " + mine.Members + " УЧАСТНИКОВ", 11, AccentWarm, TextAnchor.MiddleRight, FontBold);
                listTop = 0.60f;
            }

            var items = clans.Items ?? new List<ClanData>();
            for (var i = 0; i < Math.Min(8, items.Count); i++)
            {
                var clan = items[i];
                var column = i % 2;
                var row = i / 2;
                var xMin = column == 0 ? 0f : 0.51f;
                var xMax = column == 0 ? 0.49f : 1f;
                var yMax = listTop - row * 0.137f;
                var yMin = yMax - 0.115f;
                var root = Content + ".Clans.Card." + i;
                AddPanel(ui, Content + ".Body", root, F(xMin, yMin), F(xMax, yMax), BgSecondary);
                AddPanel(ui, root, root + ".TagBg", "0.035 0.2", "0.22 0.8", BgTertiary);
                AddLabel(ui, root, root + ".Tag", "0.035 0.2", "0.22 0.8", "[" + Safe(clan.Tag) + "]", 13, HexToCui(clan.TagColor, AccentWarm), TextAnchor.MiddleCenter, FontBold);
                AddLabel(ui, root, root + ".Name", "0.255 0.49", "0.75 0.84", Safe(clan.Name), 14, TextMain, TextAnchor.MiddleLeft, FontBold);
                AddLabel(ui, root, root + ".Motto", "0.255 0.16", "0.75 0.51", Safe(clan.Motto), 10, TextSecondary, TextAnchor.MiddleLeft, FontRegular);
                AddLabel(ui, root, root + ".Meta", "0.76 0.14", "0.96 0.86", clan.Members + " чел.\nур. " + clan.Level, 10, TextSecondary, TextAnchor.MiddleRight, FontBold);
            }

            if (items.Count == 0)
                AddLabel(ui, Content + ".Body", Content + ".Clans.Empty", "0.1 0.2", "0.9 0.62", "На этом сервере пока нет кланов", 18, TextSecondary, TextAnchor.MiddleCenter, FontBold);
            CuiHelper.AddUi(player, ui);
        }

        private void RenderSupport(BasePlayer player, PlayerView view)
        {
            var ui = new CuiElementContainer();
            var support = view.Support;

            if (support == null)
            {
                AddPanel(ui, Content + ".Body", Content + ".Support.State", "0 0.08", "1 0.985", BgSecondary);
                AddSquareRawImage(ui, Content + ".Support.State", Content + ".Support.State.Icon", "0.5 0.64", 27f,
                    ImageUrl("rust-menu/icons/nav-support.png"), "1 1 1 0.55");
                AddLabel(ui, Content + ".Support.State", Content + ".Support.State.Title", "0.1 0.43", "0.9 0.58",
                    view.SupportError == null ? "ЗАГРУЖАЕМ ДИАЛОГ" : "ПОДДЕРЖКА НЕДОСТУПНА", 21, TextMain, TextAnchor.MiddleCenter, FontBold);
                AddLabel(ui, Content + ".Support.State", Content + ".Support.State.Text", "0.16 0.29", "0.84 0.44",
                    view.SupportError == null ? "Получаем обращения и новые ответы…" : CleanText(view.SupportError), 12, TextSecondary, TextAnchor.MiddleCenter, FontRegular);
                AddButton(ui, Content + ".Support.State", Content + ".Support.State.Refresh", "0.4 0.13", "0.6 0.23", Accent,
                    "prostojmenu.ui support_refresh", "ПОВТОРИТЬ", 11, TextMain);
                CuiHelper.AddUi(player, ui);
                return;
            }

            if (!support.Registered)
            {
                AddPanel(ui, Content + ".Body", Content + ".Support.Account", "0 0.08", "1 0.985", BgSecondary);
                AddPanel(ui, Content + ".Support.Account", Content + ".Support.Account.Icon", "0.445 0.59", "0.555 0.75", "1 0.38 0.204 0.13");
                AddSupportIcon(ui, Content + ".Support.Account.Icon", AccentWarm);
                AddLabel(ui, Content + ".Support.Account", Content + ".Support.Account.Title", "0.12 0.42", "0.88 0.57", "ПРИВЯЖИТЕ STEAM-ПРОФИЛЬ", 21, TextMain, TextAnchor.MiddleCenter, FontBold);
                AddLabel(ui, Content + ".Support.Account", Content + ".Support.Account.Text", "0.16 0.27", "0.84 0.43", Safe(support.Notice), 12, TextSecondary, TextAnchor.MiddleCenter, FontRegular);
                AddLabel(ui, Content + ".Support.Account", Content + ".Support.Account.Url", "0.25 0.14", "0.75 0.24", "PROSTOJ.STORE  •  ВОЙТИ ЧЕРЕЗ STEAM", 12, AccentWarm, TextAnchor.MiddleCenter, FontBold);
                CuiHelper.AddUi(player, ui);
                return;
            }

            var ticketsRoot = Content + ".Support.Tickets";
            AddPanel(ui, Content + ".Body", ticketsRoot, "0 0.035", "0.235 0.985", BgSecondary);
            AddLabel(ui, ticketsRoot, ticketsRoot + ".Title", "0.07 0.915", "0.93 0.98", "ОБРАЩЕНИЯ", 11, TextMain, TextAnchor.MiddleLeft, FontBold);
            AddLabel(ui, ticketsRoot, ticketsRoot + ".Hint", "0.07 0.86", "0.93 0.92", "ИСТОРИЯ ДИАЛОГОВ", 9, TextSecondary, TextAnchor.MiddleLeft, FontRegular);

            var tickets = support.Tickets ?? new List<SupportTicketData>();
            const int visibleTicketLimit = 7;
            for (var i = 0; i < Math.Min(visibleTicketLimit, tickets.Count); i++)
            {
                var ticket = tickets[i];
                var yMax = 0.825f - i * 0.115f;
                var yMin = yMax - 0.098f;
                var selected = !view.SupportComposeNew && support.ActiveTicket != null && support.ActiveTicket.Number == ticket.Number;
                var row = ticketsRoot + ".Row." + i;
                AddButton(ui, ticketsRoot, row, F(0f, yMin), F(1f, yMax), selected ? BgRaised : "0 0 0 0",
                    "prostojmenu.ui support_ticket " + ticket.Number, string.Empty, 1, TextMain);
                AddLabel(ui, row, row + ".Number", "0.07 0.49", "0.7 0.88", "#" + ticket.Number, 12, TextMain, TextAnchor.MiddleLeft, FontBold);
                AddLabel(ui, row, row + ".Meta", "0.07 0.12", "0.74 0.5",
                    string.Equals(ticket.Status, "open", StringComparison.OrdinalIgnoreCase) ? "ОТКРЫТО  •  " + FormatDate(ticket.UpdatedAt, "dd.MM") : "ЗАКРЫТО  •  " + FormatDate(ticket.UpdatedAt, "dd.MM"),
                    9, string.Equals(ticket.Status, "open", StringComparison.OrdinalIgnoreCase) ? Success : TextSecondary, TextAnchor.MiddleLeft, FontRegular);
                if (ticket.UnreadCount > 0)
                {
                    AddPanel(ui, row, row + ".Unread", "0.79 0.35", "0.93 0.72", Accent);
                    AddLabel(ui, row + ".Unread", row + ".Unread.Text", "0 0", "1 1", Math.Min(99, ticket.UnreadCount).ToString(CultureInfo.InvariantCulture), 9, TextMain, TextAnchor.MiddleCenter, FontBold);
                }
            }

            var dialogRoot = Content + ".Support.Dialog";
            AddPanel(ui, Content + ".Body", dialogRoot, "0.24 0.145", "1 0.985", BgSecondary);
            // The dialog remains shorter than the ticket column because the
            // composer sits below it; both columns now use the released header area.
            AddPanel(ui, dialogRoot, dialogRoot + ".Header", "0 0.83", "1 1", BgSecondary);
            var active = view.SupportComposeNew ? null : support.ActiveTicket;
            AddLabel(ui, dialogRoot + ".Header", dialogRoot + ".Header.Title", "0.035 0.42", "0.68 0.88",
                active != null ? "ОБРАЩЕНИЕ #" + active.Number : "НОВОЕ ОБРАЩЕНИЕ", 14, TextMain, TextAnchor.MiddleLeft, FontBold);
            AddLabel(ui, dialogRoot + ".Header", dialogRoot + ".Header.Meta", "0.035 0.08", "0.72 0.44",
                active != null ? (string.Equals(active.Status, "open", StringComparison.OrdinalIgnoreCase) ? "ДИАЛОГ ОТКРЫТ  •  ОТВЕТ ОБНОВЛЯЕТСЯ АВТОМАТИЧЕСКИ" : "ОБРАЩЕНИЕ ЗАКРЫТО") : "ОПИШИТЕ ВОПРОС ОДНИМ СООБЩЕНИЕМ",
                9, active == null || string.Equals(active.Status, "open", StringComparison.OrdinalIgnoreCase) ? Success : TextSecondary, TextAnchor.MiddleLeft, FontRegular);
            var refreshRoot = dialogRoot + ".Header.Refresh";
            AddPanel(ui, dialogRoot + ".Header", refreshRoot, "0.92 0.18", "0.985 0.82", "0 0 0 0");
            AddSquareRawImage(ui, refreshRoot, refreshRoot + ".Icon", "0.5 0.5", 10f,
                ImageUrl("rust-menu/icons/action-refresh.png"), view.SupportLoading ? "1 0.43 0.31 1" : "1 1 1 0.56");
            AddButton(ui, refreshRoot, refreshRoot + ".Hit", "0 0", "1 1", "0 0 0 0",
                "prostojmenu.ui support_refresh", string.Empty, 1, TextMain);
            if (active != null && string.Equals(active.Status, "open", StringComparison.OrdinalIgnoreCase))
                AddButton(ui, dialogRoot + ".Header", dialogRoot + ".Header.Close", "0.73 0.22", "0.90 0.78",
                    view.SupportClosing ? BgTertiary : "0.860 0.420 0.480 0.16", view.SupportClosing ? string.Empty : "prostojmenu.ui support_close",
                    view.SupportClosing ? "ЗАКРЫВАЕМ…" : "ЗАКРЫТЬ ТИКЕТ", 9, view.SupportClosing ? TextSecondary : Danger);

            var messages = view.SupportComposeNew ? new List<SupportMessageData>() : support.Messages ?? new List<SupportMessageData>();
            var visibleMessages = messages.Skip(Math.Max(0, messages.Count - 5)).Take(5).ToList();
            for (var i = 0; i < visibleMessages.Count; i++)
            {
                var message = visibleMessages[i];
                // Keep the first message aligned with the first ticket while the
                // slightly wider rhythm uses the newly extended dialog height.
                var yMax = 0.799f - i * 0.1605f;
                var yMin = yMax - 0.15f;
                var bubble = dialogRoot + ".Message." + i;
                var xMin = 0.10f;
                var xMax = 0.90f;
                var avatarXMin = message.IsOwn ? 0.914f : 0.018f;
                var avatarXMax = message.IsOwn ? 0.982f : 0.086f;
                var avatarSteamId = message.SteamId;
                if (message.IsOwn && string.IsNullOrWhiteSpace(avatarSteamId) && view.Snapshot != null && view.Snapshot.Player != null)
                    avatarSteamId = view.Snapshot.Player.SteamId;
                AddSquarePanel(ui, dialogRoot, bubble + ".AvatarFrame",
                    F((avatarXMin + avatarXMax) * 0.5f, (yMin + yMax) * 0.5f), 18f, "0 0 0 0");
                AddOffsetPanel(ui, bubble + ".AvatarFrame", bubble + ".AvatarSurface",
                    "0 0", "1 1", "2 2", "-2 -2", BgTertiary);
                if (!TryAddSteamAvatar(ui, bubble + ".AvatarSurface", bubble + ".Avatar", "0 0", "1 1", avatarSteamId, "1 1 1 1"))
                    AddLabel(ui, bubble + ".AvatarSurface", bubble + ".AvatarInitial", "0 0", "1 1", PlayerInitial(message.Author), 16, TextMain, TextAnchor.MiddleCenter, FontBold);
                AddOutline(ui, bubble + ".AvatarFrame", bubble + ".AvatarBorder", Border, 1f);
                AddPanel(ui, dialogRoot, bubble, F(xMin, yMin), F(xMax, yMax), message.IsOwn ? BgRaised : "0.330 0.314 0.290 0.48");
                AddLabel(ui, bubble, bubble + ".Author", "0.035 0.68", "0.78 0.94", Safe(message.Author).ToUpperInvariant(), 9,
                    message.IsOwn ? AccentWarm : Success, TextAnchor.MiddleLeft, FontBold);
                AddLabel(ui, bubble, bubble + ".Text", "0.035 0.18", "0.965 0.69", WrapText(CleanText(message.Message), 58, 2), 11, TextMain, TextAnchor.UpperLeft, FontRegular);
                AddLabel(ui, bubble, bubble + ".Time", "0.035 0.015", "0.965 0.2", FormatDate(message.CreatedAt, "dd.MM  HH:mm"), 8, TextSecondary, TextAnchor.MiddleRight, FontRegular);
            }

            if (visibleMessages.Count == 0)
            {
                AddLabel(ui, dialogRoot, dialogRoot + ".Empty.Title", "0.12 0.46", "0.88 0.6",
                    view.SupportComposeNew ? "НАЧНИТЕ ДИАЛОГ" : "СООБЩЕНИЙ ПОКА НЕТ", 18, TextMain, TextAnchor.MiddleCenter, FontBold);
                AddLabel(ui, dialogRoot, dialogRoot + ".Empty.Text", "0.16 0.34", "0.84 0.47",
                    view.SupportComposeNew ? "Опишите проблему — команда поддержки увидит сообщение на сайте." : "Ответ сотрудника появится здесь автоматически.",
                    11, TextSecondary, TextAnchor.MiddleCenter, FontRegular);
            }

            var composer = Content + ".Support.Composer";
            AddPanel(ui, Content + ".Body", composer, "0.24 0.035", "1 0.14", "0 0 0 0");
            var activeClosed = active != null && !string.Equals(active.Status, "open", StringComparison.OrdinalIgnoreCase);
            var hasOpenTicket = tickets.Any(ticket => string.Equals(ticket.Status, "open", StringComparison.OrdinalIgnoreCase));
            if (!support.CanWrite)
            {
                AddPanel(ui, composer, composer + ".Blocked", "0 0", "1 1", "0.945 0.420 0.478 0.12");
                AddLabel(ui, composer + ".Blocked", composer + ".Blocked.Text", "0.04 0", "0.96 1", Safe(support.Notice), 11, Danger, TextAnchor.MiddleLeft, FontBold);
            }
            else if (activeClosed && !view.SupportComposeNew)
            {
                AddButton(ui, composer, composer + ".ClosedAction", "0.22 0.12", "0.78 0.88", Accent, "prostojmenu.ui support_new",
                    hasOpenTicket ? "ПЕРЕЙТИ К ОТКРЫТОМУ ОБРАЩЕНИЮ" : "СОЗДАТЬ НОВОЕ ОБРАЩЕНИЕ", 11, TextMain);
            }
            else
            {
                AddPanel(ui, composer, composer + ".Field", "0 0", "0.78 1", BgSecondary);
                AddLabel(ui, composer + ".Field", composer + ".Caption", "0.035 0.68", "0.94 0.96", "СООБЩЕНИЕ", 8,
                    view.SupportSending ? Warning : TextSecondary, TextAnchor.MiddleLeft, FontBold);
                AddInputField(ui, composer + ".Field", composer + ".Input", "0.03 0.04", "0.97 0.72", view.SupportDraft,
                    "prostojmenu.ui support_input", 500, 13, TextMain);
                AddButton(ui, composer, composer + ".Send", "0.8 0", "1 1", view.SupportSending ? BgTertiary : Accent,
                    "prostojmenu.ui support_send", view.SupportSending ? "ОТПРАВКА…" : "ОТПРАВИТЬ", 12, TextMain);
            }

            if (!string.IsNullOrWhiteSpace(view.SupportError))
                AddLabel(ui, Content + ".Body", Content + ".Support.Error", "0.3 0.155", "0.99 0.18", CleanText(view.SupportError), 9, Danger, TextAnchor.MiddleRight, FontBold);

            CuiHelper.AddUi(player, ui);
        }

        private void AddSectionHeading(CuiElementContainer ui, string title, string subtitle, string badge)
        {
            AddPanel(ui, Content + ".Body", Content + ".Section.Bar", "0 0.90", "1 1", BgRaised);
            AddLabel(ui, Content + ".Body", Content + ".Section.Subtitle", "0.018 0.915", "0.76 0.985", subtitle, 9, TextSecondary, TextAnchor.MiddleLeft, FontRegular);
            AddPanel(ui, Content + ".Body", Content + ".Section.Badge", "0.80 0.915", "0.985 0.985", BgSecondary);
            AddLabel(ui, Content + ".Section.Badge", Content + ".Section.Badge.Text", "0.05 0", "0.95 1", badge, 8, TextMain, TextAnchor.MiddleCenter, FontBold);
        }

        private void RenderEmptyState(BasePlayer player, string title, string text, string color, string iconUrl = null)
        {
            var ui = new CuiElementContainer();
            AddPanel(ui, Content + ".Body", Content + ".Empty", "0 0.05", "1 0.90", BgSecondary);
            var stateIconUrl = string.IsNullOrWhiteSpace(iconUrl)
                ? ImageUrl("rust-menu/icons/action-refresh.png")
                : iconUrl;
            AddSquareRawImage(ui, Content + ".Empty", Content + ".Empty.Icon", "0.5 0.65", 27f, stateIconUrl, "1 1 1 0.55");
            AddLabel(ui, Content + ".Empty", Content + ".Empty.Title", "0.12 0.45", "0.88 0.61", title, 22, TextMain, TextAnchor.MiddleCenter, FontBold);
            AddLabel(ui, Content + ".Empty", Content + ".Empty.Text", "0.16 0.28", "0.84 0.47", CleanText(text), 13, TextSecondary, TextAnchor.UpperCenter, FontRegular);
            AddButton(ui, Content + ".Empty", Content + ".Empty.Refresh", "0.39 0.13", "0.61 0.23", BgRaised, "prostojmenu.ui refresh", "ОБНОВИТЬ", 12, TextMain);
            CuiHelper.AddUi(player, ui);
        }

        private void ShowToast(BasePlayer player, string message, string kind)
        {
            CuiHelper.DestroyUi(player, Toast);
            var color = kind == "success" ? Success : kind == "error" ? Danger : AccentWarm;
            var ui = new CuiElementContainer();
            AddPanel(ui, Frame, Toast, "0.36 0.024", "0.76 0.088", BgRaised);
            AddOutline(ui, Toast, Toast + ".Border", color, 1f);
            AddLabel(ui, Toast, Toast + ".Text", "0.05 0", "0.95 1", CleanText(message), 11, TextMain, TextAnchor.MiddleCenter, FontBold);
            CuiHelper.AddUi(player, ui);
            timer.Once(3.5f, () => { if (player != null && player.IsConnected) CuiHelper.DestroyUi(player, Toast); });
        }

        private void PreloadMenuImages()
        {
            // The shell is intentionally artwork-free; avoid downloading the
            // retired full-window background on every fresh installation.
            EnsureImage(ImageUrl("rust-menu/coin-hd.png"));
            EnsureImage(ImageUrl("rust-menu/icons/nav-cart.png"), true);
            EnsureImage(ImageUrl("rust-menu/icons/nav-shop.png?v=2"), true);
            EnsureImage(ImageUrl("rust-menu/icons/nav-battlepass.png"), true);
            EnsureImage(ImageUrl("rust-menu/icons/nav-calendar.png"), true);
            EnsureImage(ImageUrl("rust-menu/icons/nav-stats.png"), true);
            EnsureImage(ImageUrl("rust-menu/icons/nav-top.png"), true);
            EnsureImage(ImageUrl("rust-menu/icons/nav-support.png"), true);
            EnsureImage(ImageUrl("rust-menu/icons/nav-tournament.png"), true);
            EnsureImage(ImageUrl("rust-menu/icons/nav-skindrops.png"), true);
            EnsureImage(ImageUrl("rust-menu/icons/nav-notifications.png"), true);
            EnsureImage(ImageUrl("rust-menu/icons/action-refresh.png"));
            EnsureImage(ImageUrl("rust-menu/icons/stat-kd.png?v=2"));
            EnsureImage(ImageUrl("rust-menu/icons/stat-time.png?v=2"));
            EnsureImage(ImageUrl("rust-menu/icons/stat-combat.png?v=2"));
            EnsureImage(StoreCartImageUrl);
            EnsureImage(ImageUrl("battlepass/season-1-medal-v5.png"));
            EnsureImage(ImageUrl("user-stats/wood.png"));
            EnsureImage(ImageUrl("user-stats/stone.png"));
            EnsureImage(ImageUrl("user-stats/iron_stone.png"));
            EnsureImage(ImageUrl("user-stats/gold.png"));
        }

        private void StartImageCache()
        {
            if (imageCacheUnloading || imageCacheReady)
                return;

            var remainingStartupGuard = (float)(imageCacheAllowedAtUtc - DateTime.UtcNow).TotalSeconds;
            if (remainingStartupGuard > 0f)
            {
                if (!imageCacheDeferredStartupScheduled)
                {
                    imageCacheDeferredStartupScheduled = true;
                    timer.Once(remainingStartupGuard + 1f, () =>
                    {
                        imageCacheDeferredStartupScheduled = false;
                        StartImageCache();
                    });
                }
                return;
            }

            imageCacheReady = true;
            EnsureImageCacheDirectory();
            PreloadMenuImages();
            PumpImageQueue();
        }

        private string ResolveImage(string url)
        {
            var image = EnsureImage(url);
            return image != null && image.Status == CachedImageStatus.Loaded ? image.PngId : null;
        }

        private CachedImage EnsureImage(string url, bool requiresShellRefresh = false)
        {
            url = (url ?? string.Empty).Trim();
            if (string.IsNullOrEmpty(url)) return null;

            Uri remoteUri;
            if (!Uri.TryCreate(url, UriKind.Absolute, out remoteUri) ||
                (remoteUri.Scheme != Uri.UriSchemeHttp && remoteUri.Scheme != Uri.UriSchemeHttps))
            {
                PrintWarning("Image cache accepts only absolute HTTP(S) URLs: " + url);
                return null;
            }

            CachedImage image;
            if (cachedImages.TryGetValue(url, out image))
            {
                if (requiresShellRefresh) image.RequiresShellRefresh = true;
                return image;
            }

            image = new CachedImage
            {
                Url = url,
                LocalPath = GetImageCachePath(url),
                Status = CachedImageStatus.Pending,
                RequiresShellRefresh = requiresShellRefresh
            };
            cachedImages[url] = image;

            // Do not even call a method that can reach FileStorage during the
            // startup guard. Mono may run beforefieldinit static constructors
            // while JIT-compiling the call, before StorePng's own guard runs.
            if (imageCacheReady && DateTime.UtcNow >= imageCacheAllowedAtUtc && TryLoadImageFromDisk(image))
                return image;

            pendingImages.Enqueue(image);
            PumpImageQueue();
            return image;
        }

        private void PumpImageQueue()
        {
            if (!imageCacheReady || imageCacheUnloading || ServerMgr.Instance == null) return;

            while (activeImageDownloads < MaxConcurrentImageDownloads && pendingImages.Count > 0)
            {
                var image = pendingImages.Dequeue();
                if (image == null || image.Status != CachedImageStatus.Pending) continue;

                if (TryLoadImageFromDisk(image))
                {
                    ScheduleImageRefresh(image.RequiresShellRefresh);
                    continue;
                }

                image.Status = CachedImageStatus.Loading;
                activeImageDownloads++;
                ServerMgr.Instance.StartCoroutine(DownloadImage(image));
            }
        }

        private IEnumerator DownloadImage(CachedImage image)
        {
            using (var request = UnityWebRequestTexture.GetTexture(image.Url))
            {
                yield return request.SendWebRequest();

                if (imageCacheUnloading)
                {
                    FinishImageDownload(image, false);
                    yield break;
                }

                if (request.result != UnityWebRequest.Result.Success)
                {
                    PrintWarning("Image download failed: " + image.Url + " (" + request.error + ")");
                    FinishImageDownload(image, false);
                    yield break;
                }

                var texture = DownloadHandlerTexture.GetContent(request);
                if (texture == null)
                {
                    PrintWarning("Image decode failed: " + image.Url);
                    FinishImageDownload(image, false);
                    yield break;
                }

                try
                {
                    var bytes = texture.EncodeToPNG();
                    if (bytes == null || bytes.Length == 0)
                    {
                        PrintWarning("Image PNG conversion failed: " + image.Url);
                        FinishImageDownload(image, false);
                        yield break;
                    }

                    image.Width = Math.Max(1, texture.width);
                    image.Height = Math.Max(1, texture.height);
                    SaveImageToDisk(image.LocalPath, bytes);
                    image.PngId = StorePng(bytes);
                    FinishImageDownload(image, !string.IsNullOrEmpty(image.PngId));
                }
                catch (Exception exception)
                {
                    PrintWarning("Image cache failed for " + image.Url + ": " + exception.Message);
                    FinishImageDownload(image, false);
                }
                finally
                {
                    UnityEngine.Object.DestroyImmediate(texture);
                }
            }
        }

        private void FinishImageDownload(CachedImage image, bool loaded)
        {
            if (image != null)
            {
                image.Status = loaded ? CachedImageStatus.Loaded : CachedImageStatus.Failed;
                if (loaded)
                {
                    image.FailureCount = 0;
                    image.RetryAtUtc = DateTime.MinValue;
                }
                else
                {
                    image.FailureCount++;
                    var retryDelay = Mathf.Min(60f, 2f * Mathf.Pow(2f, Math.Min(5, image.FailureCount - 1)));
                    image.RetryAtUtc = DateTime.UtcNow.AddSeconds(retryDelay);
                    if (!imageCacheUnloading && image.FailureCount <= MaxAutomaticImageRetries)
                    {
                        timer.Once(retryDelay, () => RetryImage(image));
                    }
                }
            }
            activeImageDownloads = Math.Max(0, activeImageDownloads - 1);

            if (loaded) ScheduleImageRefresh(image != null && image.RequiresShellRefresh);
            PumpImageQueue();
        }

        private void RetryImage(CachedImage image)
        {
            if (imageCacheUnloading || image == null || image.Status != CachedImageStatus.Failed ||
                DateTime.UtcNow < image.RetryAtUtc)
                return;

            image.Status = CachedImageStatus.Pending;
            pendingImages.Enqueue(image);
            PumpImageQueue();
        }

        private bool TryLoadImageFromDisk(CachedImage image)
        {
            if (!imageCacheReady || DateTime.UtcNow < imageCacheAllowedAtUtc ||
                image == null || string.IsNullOrEmpty(image.LocalPath) || !File.Exists(image.LocalPath))
                return false;

            try
            {
                var bytes = File.ReadAllBytes(image.LocalPath);
                if (!IsPng(bytes))
                {
                    PrintWarning("Removing invalid cached image: " + image.LocalPath);
                    File.Delete(image.LocalPath);
                    return false;
                }

                int width;
                int height;
                if (TryReadPngDimensions(bytes, out width, out height))
                {
                    image.Width = width;
                    image.Height = height;
                }
                image.PngId = StorePng(bytes);
                image.Status = string.IsNullOrEmpty(image.PngId) ? CachedImageStatus.Pending : CachedImageStatus.Loaded;
                return image.Status == CachedImageStatus.Loaded;
            }
            catch (Exception exception)
            {
                PrintWarning("Cannot read cached image " + image.LocalPath + ": " + exception.Message);
                return false;
            }
        }

        private string StorePng(byte[] bytes)
        {
            // Keep this readiness check before the first FileStorage reference:
            // merely reading FileStorage.server runs its static initializer.
            if (DateTime.UtcNow < imageCacheAllowedAtUtc ||
                !imageCacheReady || bytes == null || bytes.Length == 0)
                return null;

            if (FileStorage.server == null)
                return null;

            return FileStorage.server.Store(bytes, FileStorage.Type.png, CommunityEntity.ServerInstance.net.ID).ToString(CultureInfo.InvariantCulture);
        }

        private void SaveImageToDisk(string path, byte[] bytes)
        {
            EnsureImageCacheDirectory();
            var temporaryPath = path + ".tmp";
            File.WriteAllBytes(temporaryPath, bytes);
            if (File.Exists(path)) File.Delete(path);
            File.Move(temporaryPath, path);
        }

        private void EnsureImageCacheDirectory()
        {
            if (string.IsNullOrEmpty(Interface.Oxide.DataDirectory))
                throw new InvalidOperationException("Oxide data directory is not available.");
            var path = Path.Combine(Interface.Oxide.DataDirectory, ImageCacheDirectory.Replace('/', Path.DirectorySeparatorChar));
            if (!Directory.Exists(path)) Directory.CreateDirectory(path);
        }

        private string GetImageCachePath(string url)
        {
            var hash = HashUrl(url);
            var fileName = "image";
            Uri uri;
            if (Uri.TryCreate(url, UriKind.Absolute, out uri))
            {
                var candidate = Path.GetFileNameWithoutExtension(uri.AbsolutePath);
                if (!string.IsNullOrWhiteSpace(candidate)) fileName = SanitizeFileName(candidate);
            }

            var directory = Path.Combine(Interface.Oxide.DataDirectory, ImageCacheDirectory.Replace('/', Path.DirectorySeparatorChar));
            return Path.Combine(directory, hash + "_" + fileName + ".png");
        }

        private static string HashUrl(string url)
        {
            using (var sha = SHA256.Create())
            {
                var bytes = sha.ComputeHash(Encoding.UTF8.GetBytes(url ?? string.Empty));
                var builder = new StringBuilder(24);
                for (var i = 0; i < 12; i++) builder.Append(bytes[i].ToString("x2", CultureInfo.InvariantCulture));
                return builder.ToString();
            }
        }

        private static string SanitizeFileName(string value)
        {
            var invalid = new HashSet<char>(Path.GetInvalidFileNameChars());
            var builder = new StringBuilder();
            foreach (var character in value ?? string.Empty)
            {
                if (!invalid.Contains(character) && (char.IsLetterOrDigit(character) || character == '-' || character == '_'))
                    builder.Append(character);
            }
            if (builder.Length == 0) return "image";
            return builder.Length > 48 ? builder.ToString(0, 48) : builder.ToString();
        }

        private static bool IsPng(byte[] bytes)
        {
            return bytes != null && bytes.Length > 8 &&
                   bytes[0] == 0x89 && bytes[1] == 0x50 && bytes[2] == 0x4E && bytes[3] == 0x47 &&
                   bytes[4] == 0x0D && bytes[5] == 0x0A && bytes[6] == 0x1A && bytes[7] == 0x0A;
        }

        private static bool TryReadPngDimensions(byte[] bytes, out int width, out int height)
        {
            width = 0;
            height = 0;
            if (!IsPng(bytes) || bytes.Length < 24) return false;

            width = (bytes[16] << 24) | (bytes[17] << 16) | (bytes[18] << 8) | bytes[19];
            height = (bytes[20] << 24) | (bytes[21] << 16) | (bytes[22] << 8) | bytes[23];
            return width > 0 && height > 0 && width <= 16384 && height <= 16384;
        }

        private void ScheduleImageRefresh(bool requiresShellRefresh = false)
        {
            if (requiresShellRefresh) imageShellRefreshRequired = true;
            if (imageRefreshScheduled || imageCacheUnloading) return;
            imageRefreshScheduled = true;
            timer.Once(0.5f, () =>
            {
                imageRefreshScheduled = false;
                if (imageCacheUnloading) return;
                var redrawShell = imageShellRefreshRequired;
                imageShellRefreshRequired = false;
                foreach (var entry in views.ToArray())
                {
                    if (!entry.Value.Open) continue;
                    var player = BasePlayer.FindByID(entry.Key);
                    if (player == null || !player.IsConnected) continue;
                    if (redrawShell)
                    {
                        DrawShell(player, entry.Value);
                    }
                    else
                    {
                        RenderHeader(player, entry.Value);
                        RenderContent(player, entry.Value);
                    }
                }
            });
        }

        private string ImageUrl(string relativePath)
        {
            if (string.IsNullOrWhiteSpace(settings.ImagesBaseUrl)) return string.Empty;
            return settings.ImagesBaseUrl + "/" + (relativePath ?? string.Empty).TrimStart('/');
        }

        private string BattlePassImageUrl(string value)
        {
            value = (value ?? string.Empty).Trim();
            if (string.IsNullOrEmpty(value)) return string.Empty;

            const string medalWebpPath = "/images/battlepass/season-1-medal-v5.webp";
            if (value.EndsWith(medalWebpPath, StringComparison.OrdinalIgnoreCase) ||
                string.Equals(value, medalWebpPath, StringComparison.OrdinalIgnoreCase))
                return ImageUrl("battlepass/season-1-medal-v5.png");

            Uri absolute;
            if (Uri.TryCreate(value, UriKind.Absolute, out absolute)) return value;
            const string imagesPrefix = "/images/";
            if (value.StartsWith(imagesPrefix, StringComparison.OrdinalIgnoreCase))
                return ImageUrl(value.Substring(imagesPrefix.Length));
            return value;
        }

        private static string MonthName(int month)
        {
            var months = new[] { "ЯНВАРЬ", "ФЕВРАЛЬ", "МАРТ", "АПРЕЛЬ", "МАЙ", "ИЮНЬ", "ИЮЛЬ", "АВГУСТ", "СЕНТЯБРЬ", "ОКТЯБРЬ", "НОЯБРЬ", "ДЕКАБРЬ" };
            return month >= 1 && month <= 12 ? months[month - 1] : string.Empty;
        }

        private static bool IsCalendarDay(string value, DateTime date)
        {
            DateTimeOffset parsed;
            return DateTimeOffset.TryParse(value, CultureInfo.InvariantCulture, DateTimeStyles.AllowWhiteSpaces, out parsed) && parsed.Date == date.Date;
        }

        private static string EventSurface(string type)
        {
            switch ((type ?? string.Empty).ToLowerInvariant())
            {
                case "global_wipe": return "0.32 0.105 0.07 0.82";
                case "game_update": return "0.055 0.16 0.27 0.82";
                case "map_wipe": return "0.055 0.20 0.13 0.82";
                default: return "0.16 0.09 0.23 0.82";
            }
        }

        private static void AddOutline(CuiElementContainer ui, string parent, string name, string color, float thicknessPixels)
        {
            var pixels = Mathf.Max(1f, thicknessPixels).ToString("0.###", CultureInfo.InvariantCulture);
            AddOffsetPanel(ui, parent, name + ".Left", "0 0", "0 1", "0 0", pixels + " 0", color);
            AddOffsetPanel(ui, parent, name + ".Right", "1 0", "1 1", "-" + pixels + " 0", "0 0", color);
            AddOffsetPanel(ui, parent, name + ".Top", "0 1", "1 1", "0 -" + pixels, "0 0", color);
            AddOffsetPanel(ui, parent, name + ".Bottom", "0 0", "1 0", "0 0", "0 " + pixels, color);
        }

        private static void AddOffsetPanel(CuiElementContainer container, string parent, string name, string anchorMin, string anchorMax,
            string offsetMin, string offsetMax, string color)
        {
            container.Add(new CuiPanel
            {
                Image = { Color = ThemeColor(color) },
                RectTransform = { AnchorMin = anchorMin, AnchorMax = anchorMax, OffsetMin = offsetMin, OffsetMax = offsetMax }
            }, parent, name, name);
        }

        private static void AddSquarePanel(CuiElementContainer container, string parent, string name,
            string centerAnchor, float halfSizePixels, string color)
        {
            var half = Mathf.Max(1f, halfSizePixels).ToString("0.###", CultureInfo.InvariantCulture);
            AddOffsetPanel(container, parent, name, centerAnchor, centerAnchor,
                "-" + half + " -" + half, half + " " + half, color);
        }

        private bool AddRawImage(CuiElementContainer container, string parent, string name, string anchorMin, string anchorMax, string url, string color)
        {
            var png = ResolveImage(url);
            if (string.IsNullOrWhiteSpace(png)) return false;
            container.Add(new CuiElement
            {
                Name = name,
                Parent = parent,
                Components =
                {
                    new CuiRawImageComponent { Png = png, Color = ThemeColor(color) },
                    new CuiRectTransformComponent { AnchorMin = anchorMin, AnchorMax = anchorMax }
                }
            });
            return true;
        }

        private static bool TryAddSteamAvatar(CuiElementContainer container, string parent, string name,
            string anchorMin, string anchorMax, string steamId, string color)
        {
            ulong parsed;
            if (string.IsNullOrWhiteSpace(steamId) || !ulong.TryParse(steamId, out parsed) || parsed == 0) return false;
            container.Add(new CuiElement
            {
                Name = name,
                Parent = parent,
                Components =
                {
                    new CuiRawImageComponent { SteamId = parsed.ToString(CultureInfo.InvariantCulture), Color = ThemeColor(color) },
                    new CuiRectTransformComponent { AnchorMin = anchorMin, AnchorMax = anchorMax }
                }
            });
            return true;
        }

        private void AddSquareRawImage(CuiElementContainer container, string parent, string name, string anchor, float halfSizePixels, string url, string color)
        {
            AddFittedRawImage(container, parent, name, anchor, halfSizePixels, halfSizePixels, url, color);
        }

        private void AddFittedRawImage(CuiElementContainer container, string parent, string name, string anchor,
            float halfWidthPixels, float halfHeightPixels, string url, string color)
        {
            var png = ResolveImage(url);
            if (string.IsNullOrWhiteSpace(png)) return;

            var halfWidth = Mathf.Max(1f, halfWidthPixels).ToString("0.###", CultureInfo.InvariantCulture);
            var halfHeight = Mathf.Max(1f, halfHeightPixels).ToString("0.###", CultureInfo.InvariantCulture);
            container.Add(new CuiElement
            {
                Name = name,
                Parent = parent,
                Components =
                {
                    new CuiRawImageComponent { Png = png, Color = ThemeColor(color) },
                    new CuiRectTransformComponent
                    {
                        AnchorMin = anchor,
                        AnchorMax = anchor,
                        OffsetMin = "-" + halfWidth + " -" + halfHeight,
                        OffsetMax = halfWidth + " " + halfHeight
                    }
                }
            });
        }

        private static void AddSpriteButton(CuiElementContainer container, string parent, string name, string anchorMin, string anchorMax,
            string command, string sprite, string color)
        {
            AddButton(container, parent, name, anchorMin, anchorMax, "0 0 0 0", command, string.Empty, 1, color);
            container.Add(new CuiElement
            {
                Name = name + ".Icon",
                Parent = name,
                Components =
                {
                    new CuiImageComponent { Sprite = sprite, Color = ThemeColor(color) },
                    // Keep the sprite pixel-square even when its transparent
                    // button hitbox is stretched by a non-16:9 resolution.
                    new CuiRectTransformComponent
                    {
                        AnchorMin = "0.5 0.5",
                        AnchorMax = "0.5 0.5",
                        OffsetMin = "-12 -12",
                        OffsetMax = "12 12"
                    }
                }
            });
        }

        private static void AddItemIcon(CuiElementContainer container, string parent, string name, string anchorMin, string anchorMax,
            int itemId, string color)
        {
            if (itemId == 0) return;
            container.Add(new CuiElement
            {
                Name = name,
                Parent = parent,
                Components =
                {
                    new CuiImageComponent { ItemId = itemId, Color = ThemeColor(color) },
                    new CuiRectTransformComponent { AnchorMin = anchorMin, AnchorMax = anchorMax }
                }
            });
        }

        private static void AddStatusDot(CuiElementContainer container, string parent, string name)
        {
            container.Add(new CuiElement
            {
                Name = name,
                Parent = parent,
                Components =
                {
                    new CuiImageComponent { Sprite = "assets/icons/circle_closed.png", Color = ThemeColor(Success) },
                    new CuiRectTransformComponent
                    {
                        AnchorMin = "1 0",
                        AnchorMax = "1 0",
                        OffsetMin = "-3 -3",
                        OffsetMax = "5 5"
                    }
                }
            });
        }

        private static void AddPanel(CuiElementContainer container, string parent, string name, string anchorMin, string anchorMax, string color)
        {
            container.Add(new CuiPanel
            {
                Image = { Color = ThemeColor(color) },
                RectTransform = { AnchorMin = anchorMin, AnchorMax = anchorMax }
            }, parent, name, name);
        }

        private static void AddLabel(CuiElementContainer container, string parent, string name, string anchorMin, string anchorMax,
            string text, int fontSize, string color, TextAnchor align, string font)
        {
            container.Add(new CuiLabel
            {
                Text = { Text = text ?? string.Empty, FontSize = fontSize, Color = ThemeColor(color), Align = align, Font = font },
                RectTransform = { AnchorMin = anchorMin, AnchorMax = anchorMax }
            }, parent, name, name);
        }

        private static void AddButton(CuiElementContainer container, string parent, string name, string anchorMin, string anchorMax,
            string color, string command, string text, int fontSize, string textColor)
        {
            container.Add(new CuiButton
            {
                Button = { Color = ThemeColor(color), Command = command },
                Text = { Text = text ?? string.Empty, FontSize = fontSize, Color = ThemeColor(textColor), Align = TextAnchor.MiddleCenter, Font = FontBold },
                RectTransform = { AnchorMin = anchorMin, AnchorMax = anchorMax }
            }, parent, name, name);
        }

        private static void AddInputField(CuiElementContainer container, string parent, string name, string anchorMin, string anchorMax,
            string text, string command, int charsLimit, int fontSize, string color)
        {
            container.Add(new CuiElement
            {
                Name = name,
                Parent = parent,
                Components =
                {
                    new CuiInputFieldComponent
                    {
                        Text = text ?? string.Empty,
                        Command = command,
                        CharsLimit = charsLimit,
                        FontSize = fontSize,
                        Font = FontRegular,
                        Align = TextAnchor.MiddleLeft,
                        Color = ThemeColor(color),
                        NeedsKeyboard = true
                    },
                    new CuiRectTransformComponent { AnchorMin = anchorMin, AnchorMax = anchorMax }
                }
            });
        }

        private static string NormalizeKey(string value)
        {
            if (string.IsNullOrWhiteSpace(value)) return string.Empty;
            return new string(value.Trim().ToLowerInvariant().Where(character => char.IsLetterOrDigit(character) || character == '_' || character == '-').ToArray());
        }

        private static string NormalizeVisualTheme(string value)
        {
            var normalized = NormalizeKey(value);
            return normalized == "moscow" || normalized == "moscow77" || normalized == "emerald"
                ? "moscow77"
                : "prostoj";
        }

        private static string ThemeColor(string color)
        {
            if (string.IsNullOrWhiteSpace(color)) return color;

            if (useMoscowVisualTheme && StartsWithRgb(color, "0.860 0.220 0.330"))
                return ReplaceRgb(color, "0.000 0.718 0.494");

            // Normalize legacy purple surfaces at the primitive boundary. This
            // keeps all existing screens and modules on the same neutral theme
            // without duplicating style decisions throughout their layouts.
            if (StartsWithRgb(color, "0.031 0.008 0.141")) return ReplaceRgb(color, "0.259 0.247 0.220");
            if (StartsWithRgb(color, "0.098 0.063 0.176")) return ReplaceRgb(color, "0.259 0.247 0.220");
            if (StartsWithRgb(color, "0.18 0.102 0.231") || StartsWithRgb(color, "0.180 0.102 0.231")) return ReplaceRgb(color, "0.330 0.314 0.290");
            if (StartsWithRgb(color, "0.133 0.086 0.216")) return ReplaceRgb(color, "0.412 0.392 0.369");
            if (StartsWithRgb(color, "1 0.38 0.204")) return ReplaceRgb(color, "0.520 0.495 0.455");
            if (StartsWithRgb(color, "0.922 0.047 0.208")) return ReplaceRgb(color, "0.412 0.392 0.369");
            if (color == "0.925 0.894 0.953 1") return ThemeColor(TextMain);
            if (color == "0.561 0.561 0.561 1") return ThemeColor(TextSecondary);
            if (color == "0.72 0.69 0.75 1") return TextMain;
            if (color == "0.62 0.59 0.66 1") return TextSecondary;
            return color;
        }

        private static bool StartsWithRgb(string color, string rgb)
        {
            return color.StartsWith(rgb + " ", StringComparison.Ordinal);
        }

        private static string ReplaceRgb(string color, string rgb)
        {
            var alphaIndex = color.LastIndexOf(' ');
            return alphaIndex > 0 ? rgb + color.Substring(alphaIndex) : color;
        }

        private static string F(float x, float y) => x.ToString("0.####", CultureInfo.InvariantCulture) + " " + y.ToString("0.####", CultureInfo.InvariantCulture);
        private static string CleanText(string value) => (value ?? string.Empty).Replace("<", "‹").Replace(">", "›");
        private static string Safe(string value) => string.IsNullOrWhiteSpace(value) ? "—" : CleanText(value.Trim());

        private static string PlayerInitial(string value)
        {
            var clean = CleanText(value).Trim();
            return string.IsNullOrEmpty(clean) ? "?" : clean.Substring(0, 1).ToUpperInvariant();
        }
        private static string CompactText(string value, int maxLength)
        {
            var clean = (value ?? string.Empty).Trim();
            if (maxLength < 2 || clean.Length <= maxLength) return clean;
            return clean.Substring(0, maxLength - 1).TrimEnd() + "…";
        }
        private static string FormatNumber(long value) => value.ToString("N0", CultureInfo.InvariantCulture).Replace(",", " ");

        private static string WrapText(string value, int lineLength, int maxLines)
        {
            value = (value ?? string.Empty).Replace("\r", " ").Replace("\n", " ").Trim();
            if (value.Length == 0 || lineLength < 8 || maxLines < 1) return value;
            var words = value.Split(new[] { ' ' }, StringSplitOptions.RemoveEmptyEntries);
            var lines = new List<string>();
            var current = string.Empty;
            foreach (var word in words)
            {
                var candidate = current.Length == 0 ? word : current + " " + word;
                if (candidate.Length <= lineLength)
                {
                    current = candidate;
                    continue;
                }
                if (current.Length > 0) lines.Add(current);
                current = word.Length > lineLength ? word.Substring(0, lineLength) : word;
                if (lines.Count >= maxLines) break;
            }
            if (lines.Count < maxLines && current.Length > 0) lines.Add(current);
            var result = string.Join("\n", lines.Take(maxLines).ToArray());
            var shownLength = result.Replace("\n", " ").Length;
            return shownLength + 2 < value.Length ? result.TrimEnd('.', ' ') + "…" : result;
        }

        private static string FormatPlaytime(int minutes)
        {
            if (minutes <= 0) return "0 ч";
            return minutes >= 60 ? (minutes / 60) + " ч " + (minutes % 60) + " м" : minutes + " м";
        }

        private static string FormatDate(string value, string format)
        {
            DateTimeOffset date;
            return DateTimeOffset.TryParse(value, CultureInfo.InvariantCulture, DateTimeStyles.AllowWhiteSpaces, out date)
                ? date.ToString(format, CultureInfo.GetCultureInfo("ru-RU"))
                : "—";
        }

        private static string EventColor(string type)
        {
            switch ((type ?? string.Empty).ToLowerInvariant())
            {
                case "global_wipe": return "0.960 0.340 0.180 1";
                case "game_update": return "0.302 0.702 0.961 1";
                case "map_wipe": return Success;
                default: return "0.718 0.580 0.957 1";
            }
        }

        private static string CalendarEventLabel(string type)
        {
            switch ((type ?? string.Empty).ToLowerInvariant())
            {
                case "global_wipe": return "Глобальный вайп";
                case "game_update": return "Обновление Rust";
                case "map_wipe": return "Вайп карты";
                default: return "Событие";
            }
        }

        private static string HexToCui(string hex, string fallback)
        {
            if (string.IsNullOrWhiteSpace(hex)) return fallback;
            var value = hex.Trim().TrimStart('#');
            if (value.Length != 6) return fallback;
            int red, green, blue;
            if (!int.TryParse(value.Substring(0, 2), NumberStyles.HexNumber, CultureInfo.InvariantCulture, out red) ||
                !int.TryParse(value.Substring(2, 2), NumberStyles.HexNumber, CultureInfo.InvariantCulture, out green) ||
                !int.TryParse(value.Substring(4, 2), NumberStyles.HexNumber, CultureInfo.InvariantCulture, out blue))
                return fallback;
            return (red / 255f).ToString("0.###", CultureInfo.InvariantCulture) + " " +
                   (green / 255f).ToString("0.###", CultureInfo.InvariantCulture) + " " +
                   (blue / 255f).ToString("0.###", CultureInfo.InvariantCulture) + " 1";
        }
    }
}
#pragma warning restore 0649
